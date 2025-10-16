<?php
/**
 * Developer Portal - Tenants Management
 * Manage all tenant companies
 * Created: 2025-10-16
 */

require_once '../config/config.php';
require_once 'includes/auth_check.php';

$action = $_GET['action'] ?? 'list';
$tenant_id = $_GET['id'] ?? null;
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? $action;
    
    try {
        $pdo = DatabaseConfig::getMainConnection();
        
        switch ($action) {
            case 'add':
                // Validate input
                $validation_rules = [
                    'tenant_id' => ['required' => true, 'min_length' => 3, 'max_length' => 50],
                    'company_name' => ['required' => true, 'min_length' => 2, 'max_length' => 255],
                    'contact_person' => ['required' => true, 'min_length' => 2, 'max_length' => 255],
                    'email' => ['required' => true, 'email' => true],
                    'subscription_plan' => ['required' => true, 'in' => ['basic', 'professional', 'enterprise']]
                ];
                
                $validation_errors = Security::validateInput($_POST, $validation_rules);
                
                if (empty($validation_errors)) {
                    // Check if tenant_id or email already exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE tenant_id = ? OR email = ?");
                    $stmt->execute([$_POST['tenant_id'], $_POST['email']]);
                    
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'معرف الشركة أو البريد الإلكتروني موجود مسبقاً';
                    } else {
                        // Create tenant record
                        $tenant_id = Security::sanitizeInput($_POST['tenant_id']);
                        $company_name = Security::sanitizeInput($_POST['company_name']);
                        $company_name_en = Security::sanitizeInput($_POST['company_name_en'] ?? '');
                        $contact_person = Security::sanitizeInput($_POST['contact_person']);
                        $email = Security::sanitizeInput($_POST['email'], 'email');
                        $phone = Security::sanitizeInput($_POST['phone'] ?? '');
                        $address = Security::sanitizeInput($_POST['address'] ?? '');
                        $city = Security::sanitizeInput($_POST['city'] ?? '');
                        $subscription_plan = $_POST['subscription_plan'];
                        $subscription_start = $_POST['subscription_start'] ?? date('Y-m-d');
                        $subscription_end = $_POST['subscription_end'] ?? date('Y-m-d', strtotime('+1 year'));
                        $is_approved = isset($_POST['is_approved']) ? 1 : 0;
                        $database_name = DatabaseConfig::getTenantDatabaseName($tenant_id);
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO tenants (
                                tenant_id, company_name, company_name_en, contact_person, email, phone, 
                                address, city, subscription_plan, subscription_start, subscription_end, 
                                is_approved, database_name, created_by
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $tenant_id, $company_name, $company_name_en, $contact_person, $email, $phone,
                            $address, $city, $subscription_plan, $subscription_start, $subscription_end,
                            $is_approved, $database_name, Session::getUserId()
                        ]);
                        
                        // Create tenant database if approved
                        if ($is_approved) {
                            if (DatabaseConfig::createTenantDatabase($tenant_id)) {
                                $message = 'تم إضافة الشركة وإنشاء قاعدة بياناتها بنجاح';
                            } else {
                                $message = 'تم إضافة الشركة ولكن فشل في إنشاء قاعدة البيانات';
                            }
                        } else {
                            $message = 'تم إضافة الشركة بنجاح (بانتظار الموافقة)';
                        }
                        
                        $action = 'list';
                    }
                } else {
                    $error = 'بعض البيانات غير صحيحة';
                }
                break;
                
            case 'approve':
                $tenant_id = $_POST['tenant_id'];
                $stmt = $pdo->prepare("SELECT tenant_id FROM tenants WHERE id = ? AND is_approved = 0");
                $stmt->execute([$tenant_id]);
                $tenant_data = $stmt->fetch();
                
                if ($tenant_data) {
                    // Approve tenant
                    $stmt = $pdo->prepare("UPDATE tenants SET is_approved = 1, is_active = 1 WHERE id = ?");
                    $stmt->execute([$tenant_id]);
                    
                    // Create tenant database
                    if (DatabaseConfig::createTenantDatabase($tenant_data['tenant_id'])) {
                        $message = 'تمت موافقة الشركة وإنشاء قاعدة بياناتها بنجاح';
                    } else {
                        $message = 'تمت موافقة الشركة ولكن فشل في إنشاء قاعدة البيانات';
                    }
                } else {
                    $error = 'لم يتم العثور على الشركة';
                }
                break;
                
            case 'toggle_status':
                $tenant_id = $_POST['tenant_id'];
                $stmt = $pdo->prepare("UPDATE tenants SET is_active = !is_active WHERE id = ?");
                $stmt->execute([$tenant_id]);
                $message = 'تم تغيير حالة الشركة بنجاح';
                break;
        }
    } catch (Exception $e) {
        error_log('Tenants management error: ' . $e->getMessage());
        $error = 'حدث خطأ في النظام. يرجى المحاولة لاحقاً';
    }
}

// Get tenants list
if ($action === 'list') {
    try {
        $pdo = DatabaseConfig::getMainConnection();
        
        // Search and filtering
        $search = $_GET['search'] ?? '';
        $status_filter = $_GET['status'] ?? '';
        $plan_filter = $_GET['plan'] ?? '';
        
        $where_conditions = [];
        $params = [];
        
        if (!empty($search)) {
            $where_conditions[] = "(company_name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR tenant_id LIKE ?)";
            $search_param = '%' . $search . '%';
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        }
        
        if ($status_filter === 'active') {
            $where_conditions[] = "status = 'active'";
        } elseif ($status_filter === 'inactive') {
            $where_conditions[] = "status = 'suspended'";
        } elseif ($status_filter === 'pending') {
            $where_conditions[] = "status = 'pending'";
        }
        
        if (!empty($plan_filter)) {
            $where_conditions[] = "subscription_plan = ?";
            $params[] = $plan_filter;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Pagination
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM tenants $where_clause";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total_records = $stmt->fetchColumn();
        $total_pages = ceil($total_records / $per_page);
        
        // Get tenants
        $sql = "
            SELECT id, tenant_id, company_name, contact_person, email, phone, 
                   subscription_plan, subscription_end, status, created_at
            FROM tenants 
            $where_clause
            ORDER BY created_at DESC 
            LIMIT $per_page OFFSET $offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tenants = $stmt->fetchAll();
        
    } catch (Exception $e) {
        // Log detailed error for debugging
        error_log('Tenants list error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
        $tenants = [];
        $total_pages = 1;
        
        // Provide specific error messages
        if (strpos($e->getMessage(), 'Connection refused') !== false) {
            $error = 'لا يمكن الاتصال بقاعدة البيانات. تأكد من تشغيل خادم MySQL';
        } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
            $error = 'خطأ في صلاحيات قاعدة البيانات. تحقق من اسم المستخدم وكلمة المرور';
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            $error = 'قاعدة البيانات غير موجودة. <a href="../quick_setup.php" target="_blank">انقر هنا لإعداد قاعدة البيانات</a>';
        } elseif (strpos($e->getMessage(), "Table 'warehouse_saas_main.tenants' doesn't exist") !== false) {
            $error = 'جدول الشركات غير موجود. <a href="../database_check.php" target="_blank">انقر هنا للتحقق من قاعدة البيانات</a>';
        } else {
            $error = 'حدث خطأ في تحميل قائمة الشركات: ' . $e->getMessage();
        }
    }
}

$page_title = 'إدارة الشركات';
include 'includes/header.php';
?>

<div class="main-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="fas fa-building me-3"></i>إدارة الشركات</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">لوحة التحكم</a></li>
                                <li class="breadcrumb-item active">إدارة الشركات</li>
                            </ol>
                        </nav>
                    </div>
                    <?php if ($action === 'list'): ?>
                        <a href="?action=add" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>إضافة شركة جديدة
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Display Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($action === 'add'): ?>
                <!-- Add Tenant Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-plus me-2"></i>إضافة شركة جديدة
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add">
                            <?php echo Security::getCSRFInput(); ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tenant_id" class="form-label">معرف الشركة *</label>
                                        <input type="text" class="form-control" id="tenant_id" name="tenant_id" 
                                               value="<?php echo htmlspecialchars($_POST['tenant_id'] ?? ''); ?>" required>
                                        <small class="form-text text-muted">معرف فريد للشركة (أحرف وأرقام فقط)</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="subscription_plan" class="form-label">باقة الاشتراك *</label>
                                        <select class="form-select" id="subscription_plan" name="subscription_plan" required>
                                            <option value="">اختر الباقة</option>
                                            <option value="basic" <?php echo ($_POST['subscription_plan'] ?? '') === 'basic' ? 'selected' : ''; ?>>أساسي</option>
                                            <option value="professional" <?php echo ($_POST['subscription_plan'] ?? '') === 'professional' ? 'selected' : ''; ?>>محترف</option>
                                            <option value="enterprise" <?php echo ($_POST['subscription_plan'] ?? '') === 'enterprise' ? 'selected' : ''; ?>>مؤسسي</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="company_name" class="form-label">اسم الشركة *</label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" 
                                               value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="company_name_en" class="form-label">اسم الشركة (إنجليزي)</label>
                                        <input type="text" class="form-control" id="company_name_en" name="company_name_en" 
                                               value="<?php echo htmlspecialchars($_POST['company_name_en'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="contact_person" class="form-label">شخص الاتصال *</label>
                                        <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                               value="<?php echo htmlspecialchars($_POST['contact_person'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">البريد الإلكتروني *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">رقم الهاتف</label>
                                        <input type="text" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="city" class="form-label">المدينة</label>
                                        <input type="text" class="form-control" id="city" name="city" 
                                               value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="address" class="form-label">العنوان</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="subscription_start" class="form-label">تاريخ بدء الاشتراك</label>
                                        <input type="date" class="form-control" id="subscription_start" name="subscription_start" 
                                               value="<?php echo $_POST['subscription_start'] ?? date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="subscription_end" class="form-label">تاريخ انتهاء الاشتراك</label>
                                        <input type="date" class="form-control" id="subscription_end" name="subscription_end" 
                                               value="<?php echo $_POST['subscription_end'] ?? date('Y-m-d', strtotime('+1 year')); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_approved" name="is_approved" 
                                                   <?php echo isset($_POST['is_approved']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_approved">
                                                موافقة فورية وإنشاء قاعدة البيانات
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="tenants.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-right me-2"></i>رجوع
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>حفظ الشركة
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Tenants List -->
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0">
                                    <i class="fas fa-list me-2"></i>قائمة الشركات
                                </h5>
                            </div>
                            <div class="col-md-6">
                                <form method="GET" class="row g-2">
                                    <div class="col">
                                        <input type="text" class="form-control form-control-sm" name="search" 
                                               placeholder="بحث في الشركات..." 
                                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                                    </div>
                                    <div class="col-auto">
                                        <select class="form-select form-select-sm" name="status">
                                            <option value="">جميع الحالات</option>
                                            <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>نشط</option>
                                            <option value="inactive" <?php echo ($_GET['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>غير نشط</option>
                                            <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>بالانتظار</option>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>معرف الشركة</th>
                                        <th>اسم الشركة</th>
                                        <th>شخص الاتصال</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>الباقة</th>
                                        <th>انتهاء الاشتراك</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tenants)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="fas fa-info-circle me-2"></i>لا توجد شركات مطابقة للبحث
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($tenants as $index => $tenant): ?>
                                            <tr>
                                                <td><?php echo ($page - 1) * $per_page + $index + 1; ?></td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($tenant['tenant_id']); ?></span>
                                                </td>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($tenant['company_name']); ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($tenant['contact_person']); ?></td>
                                                <td><?php echo htmlspecialchars($tenant['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $tenant['subscription_plan'] === 'enterprise' ? 'primary' : 
                                                             ($tenant['subscription_plan'] === 'professional' ? 'success' : 'secondary');
                                                    ?>">
                                                        <?php 
                                                        $plans = [
                                                            'basic' => 'أساسي',
                                                            'professional' => 'محترف',
                                                            'enterprise' => 'مؤسسي'
                                                        ];
                                                        echo $plans[$tenant['subscription_plan']] ?? $tenant['subscription_plan'];
                                                        ?>
                                                    </span>
                                                </td>
                                                <td class="small">
                                                    <?php echo Utils::formatDate($tenant['subscription_end']); ?>
                                                </td>
                                                <td>
                                                    <?php if (!$tenant['is_approved']): ?>
                                                        <span class="badge bg-warning">بالانتظار</span>
                                                    <?php elseif ($tenant['is_active']): ?>
                                                        <span class="badge bg-success">نشط</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">موقف</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <?php if (!$tenant['is_approved']): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="approve">
                                                                <input type="hidden" name="tenant_id" value="<?php echo $tenant['id']; ?>">
                                                                <?php echo Security::getCSRFInput(); ?>
                                                                <button type="submit" class="btn btn-success btn-sm" 
                                                                        title="موافقة" 
                                                                        onclick="return confirm('هل أنت متأكد من موافقة هذه الشركة؟')">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="tenant_id" value="<?php echo $tenant['id']; ?>">
                                                            <?php echo Security::getCSRFInput(); ?>
                                                            <button type="submit" class="btn btn-<?php echo $tenant['is_active'] ? 'warning' : 'success'; ?> btn-sm" 
                                                                    title="<?php echo $tenant['is_active'] ? 'إيقاف' : 'تفعيل'; ?>" 
                                                                    onclick="return confirm('هل أنت متأكد من تغيير حالة الشركة؟')">
                                                                <i class="fas fa-<?php echo $tenant['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <a href="tenant_details.php?id=<?php echo $tenant['id']; ?>" 
                                                           class="btn btn-info btn-sm" title="عرض التفاصيل">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <a href="tenant_edit.php?id=<?php echo $tenant['id']; ?>" 
                                                           class="btn btn-primary btn-sm" title="تعديل">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer">
                            <?php echo Utils::generatePagination($page, $total_pages, 'tenants.php', $_GET); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>