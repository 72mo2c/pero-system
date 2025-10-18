<?php
/**
 * Suppliers Management
 * Created: 2025-10-16
 */

require_once '../config/config.php';

// Initialize security and session
Security::initialize();
Session::initialize();

// Check authentication
Session::requireTenantLogin();

// Get database connection
$pdo = DatabaseConfig::getTenantConnection(Session::getTenantId());

// Handle AJAX requests
if (Utils::isAjax()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_supplier':
            $id = (int)($_GET['id'] ?? 0);
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
                $stmt->execute([$id]);
                $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($supplier) {
                    Utils::sendJsonResponse(['success' => true, 'supplier' => $supplier]);
                } else {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'المورد غير موجود']);
                }
            }
            break;
            
        case 'toggle_status':
            Session::requirePermission('suppliers_edit');
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $pdo->prepare("UPDATE suppliers SET is_active = NOT is_active WHERE id = ?");
                if ($stmt->execute([$id])) {
                    Utils::logActivity($pdo, Session::getUserId(), 'toggle_supplier_status', 'suppliers', $id);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'تم تحديث حالة المورد']);
                } else {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'فشل في تحديث حالة المورد']);
                }
            }
            break;
            
        case 'delete':
            Session::requirePermission('suppliers_delete');
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                // Check if supplier is used in any purchase orders
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM purchase_orders WHERE supplier_id = ?");
                $stmt->execute([$id]);
                $usage = $stmt->fetch()['count'];
                
                if ($usage > 0) {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'لا يمكن حذف المورد لأنه مستخدم في عمليات شراء']);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        Utils::logActivity($pdo, Session::getUserId(), 'delete_supplier', 'suppliers', $id);
                        Utils::sendJsonResponse(['success' => true, 'message' => 'تم حذف المورد بنجاح']);
                    } else {
                        Utils::sendJsonResponse(['success' => false, 'message' => 'فشل في حذف المورد']);
                    }
                }
            }
            break;
    }
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Utils::isAjax()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        // Check permissions
        if ($action === 'add') {
            Session::requirePermission('suppliers_add');
        } else {
            Session::requirePermission('suppliers_edit');
        }
        
        // Validate CSRF token
        if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'انتهت صلاحية الجلسة. يرجى المحاولة مرة أخرى.');
            header('Location: suppliers.php');
            exit;
        }
        
        // Sanitize and validate input
        $data = [
            'name' => Security::sanitizeInput($_POST['name'] ?? ''),
            'name_en' => Security::sanitizeInput($_POST['name_en'] ?? ''),
            'contact_person' => Security::sanitizeInput($_POST['contact_person'] ?? '') ?: null,
            'email' => Security::sanitizeInput($_POST['email'] ?? '') ?: null,
            'phone' => Security::sanitizeInput($_POST['phone'] ?? '') ?: null,
            'mobile' => Security::sanitizeInput($_POST['mobile'] ?? '') ?: null,
            'address' => Security::sanitizeInput($_POST['address'] ?? '') ?: null,
            'city' => Security::sanitizeInput($_POST['city'] ?? '') ?: null,
            'country' => Security::sanitizeInput($_POST['country'] ?? 'Saudi Arabia'),
            'tax_number' => Security::sanitizeInput($_POST['tax_number'] ?? '') ?: null,
            'commercial_registration' => Security::sanitizeInput($_POST['commercial_registration'] ?? '') ?: null,
            'payment_terms' => Security::sanitizeInput($_POST['payment_terms'] ?? '') ?: null,
            'credit_limit' => (float)($_POST['credit_limit'] ?? 0),
            'supplier_type' => Security::sanitizeInput($_POST['supplier_type'] ?? 'company'),
            'specialization' => Security::sanitizeInput($_POST['specialization'] ?? '') ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'notes' => Security::sanitizeInput($_POST['notes'] ?? '') ?: null
        ];
        
        $errors = [];
        
        // Validation
        if (empty($data['name'])) {
            $errors[] = 'اسم المورد مطلوب';
        }
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'البريد الإلكتروني غير صحيح';
        }
        if ($data['credit_limit'] < 0) {
            $errors[] = 'الحد الائتماني لا يمكن أن يكون سالباً';
        }
        if (!in_array($data['supplier_type'], ['individual', 'company'])) {
            $errors[] = 'نوع المورد غير صحيح';
        }
        
        // Generate supplier code if adding new supplier
        if ($action === 'add') {
            $code_prefix = 'SUP';
            $last_code = $pdo->query("SELECT code FROM suppliers WHERE code LIKE '{$code_prefix}%' ORDER BY id DESC LIMIT 1")->fetch();
            
            if ($last_code) {
                $last_number = (int)substr($last_code['code'], 3);
                $new_number = $last_number + 1;
            } else {
                $new_number = 1;
            }
            
            $data['code'] = $code_prefix . str_pad($new_number, 6, '0', STR_PAD_LEFT);
        }
        
        // Check for duplicate code (if editing)
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'edit' && $id) {
            $check_sql = "SELECT id FROM suppliers WHERE code = ? AND id != ?";
            $stmt = $pdo->prepare($check_sql);
            $stmt->execute([$data['code'] ?? '', $id]);
            if ($stmt->fetch()) {
                $errors[] = 'رمز المورد موجود مسبقاً';
            }
        }
        
        // Check for duplicate email (if provided)
        if (!empty($data['email'])) {
            $check_sql = "SELECT id FROM suppliers WHERE email = ?";
            $check_params = [$data['email']];
            
            if ($action === 'edit' && $id) {
                $check_sql .= " AND id != ?";
                $check_params[] = $id;
            }
            
            $stmt = $pdo->prepare($check_sql);
            $stmt->execute($check_params);
            if ($stmt->fetch()) {
                $errors[] = 'البريد الإلكتروني موجود مسبقاً';
            }
        }
        
        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $data['created_by'] = Session::getUserId();
                    $sql = "INSERT INTO suppliers (" . implode(', ', array_keys($data)) . ") VALUES (:" . implode(', :', array_keys($data)) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($data);
                    $supplier_id = $pdo->lastInsertId();
                    
                    Utils::logActivity($pdo, Session::getUserId(), 'add_supplier', 'suppliers', $supplier_id, null, $data);
                    Session::setFlash('success', 'تم إضافة المورد بنجاح - رمز المورد: ' . $data['code']);
                } else {
                    unset($data['code']); // Don't update code when editing
                    $sql = "UPDATE suppliers SET " . implode(' = ?, ', array_keys($data)) . " = ? WHERE id = ?";
                    $params = array_values($data);
                    $params[] = $id;
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    Utils::logActivity($pdo, Session::getUserId(), 'edit_supplier', 'suppliers', $id, null, $data);
                    Session::setFlash('success', 'تم تحديث بيانات المورد بنجاح');
                }
                
                header('Location: suppliers.php');
                exit;
            } catch (Exception $e) {
                error_log('Supplier save error: ' . $e->getMessage());
                Session::setFlash('error', 'حدث خطأ أثناء حفظ بيانات المورد');
            }
        } else {
            Session::setFlash('error', implode('<br>', $errors));
        }
    }
}

// Get filters
$search = Security::sanitizeInput($_GET['search'] ?? '');
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$city_filter = Security::sanitizeInput($_GET['city'] ?? '');
$specialization_filter = Security::sanitizeInput($_GET['specialization'] ?? '');

// Build query
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR code LIKE ? OR phone LIKE ? OR mobile LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($type_filter !== '') {
    $where_conditions[] = "supplier_type = ?";
    $params[] = $type_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "is_active = ?";
    $params[] = $status_filter;
}

if (!empty($city_filter)) {
    $where_conditions[] = "city LIKE ?";
    $params[] = "%$city_filter%";
}

if (!empty($specialization_filter)) {
    $where_conditions[] = "specialization LIKE ?";
    $params[] = "%$specialization_filter%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM suppliers WHERE $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;

// Get suppliers
$sql = "
    SELECT s.*, 
           COUNT(po.id) as total_orders,
           COALESCE(SUM(po.total_amount), 0) as total_purchases,
           COALESCE(SUM(po.total_amount - po.paid_amount), 0) as outstanding_balance
    FROM suppliers s
    LEFT JOIN purchase_orders po ON s.id = po.supplier_id
    WHERE $where_clause
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get cities for filter
$stmt = $pdo->query("SELECT DISTINCT city FROM suppliers WHERE city IS NOT NULL AND city != '' ORDER BY city");
$cities = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get specializations for filter
$stmt = $pdo->query("SELECT DISTINCT specialization FROM suppliers WHERE specialization IS NOT NULL AND specialization != '' ORDER BY specialization");
$specializations = $stmt->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'إدارة الموردين';
$current_page = 'suppliers';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo Session::getTenantName(); ?></title>
    
    <!-- CSS Files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        .supplier-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .supplier-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .supplier-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #fd7e14 0%, #e55a4e 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .filters-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .table-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .table-header {
            background: linear-gradient(135deg, #fd7e14 0%, #e55a4e 100%);
            color: white;
            padding: 20px;
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.875rem;
            margin: 2px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .supplier-type-badge {
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .supplier-stats {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-weight: bold;
            font-size: 1.1rem;
            color: #fd7e14;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .specialization-badge {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <?php include '../shared/includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'includes/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content p-4">
                    <!-- Page Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="page-title">
                            <i class="fas fa-truck-loading me-2"></i>
                            إدارة الموردين
                        </h2>
                        
                        <?php if (Session::hasPermission('suppliers_add')): ?>
                        <div>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#supplierModal">
                                <i class="fas fa-plus me-2"></i>
                                إضافة مورد جديد
                            </button>
                            <button type="button" class="btn btn-outline-warning">
                                <i class="fas fa-upload me-2"></i>
                                استيراد موردين
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Flash Messages -->
                    <?php if (Session::hasFlash('success')): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo Session::getFlash('success'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (Session::hasFlash('error')): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo Session::getFlash('error'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Filters -->
                    <div class="filters-card">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">البحث</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="اسم المورد، الرمز، الهاتف، أو البريد">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">نوع المورد</label>
                                <select class="form-select" name="type">
                                    <option value="">جميع الأنواع</option>
                                    <option value="individual" <?php echo $type_filter === 'individual' ? 'selected' : ''; ?>>فرد</option>
                                    <option value="company" <?php echo $type_filter === 'company' ? 'selected' : ''; ?>>شركة</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">الحالة</label>
                                <select class="form-select" name="status">
                                    <option value="">جميع الحالات</option>
                                    <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>نشط</option>
                                    <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>غير نشط</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">المدينة</label>
                                <select class="form-select" name="city">
                                    <option value="">جميع المدن</option>
                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?php echo htmlspecialchars($city); ?>" <?php echo $city_filter === $city ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($city); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">التخصص</label>
                                <select class="form-select" name="specialization">
                                    <option value="">جميع التخصصات</option>
                                    <?php foreach ($specializations as $spec): ?>
                                        <option value="<?php echo htmlspecialchars($spec); ?>" <?php echo $specialization_filter === $spec ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($spec); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>
                                        بحث
                                    </button>
                                    <a href="suppliers.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo me-1"></i>
                                        إعادة تعيين
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Suppliers Table -->
                    <div class="table-card">
                        <div class="table-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                قائمة الموردين (<?php echo number_format($total_records); ?> مورد)
                            </h5>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>المورد</th>
                                        <th>النوع/التخصص</th>
                                        <th>معلومات الاتصال</th>
                                        <th>المدينة</th>
                                        <th>إحصائيات</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($suppliers)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="fas fa-truck-loading fa-3x mb-3 d-block"></i>
                                                لا يوجد موردين لعرضهم
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="supplier-avatar me-3">
                                                            <?php echo strtoupper(substr($supplier['name'], 0, 2)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($supplier['name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($supplier['code']); ?></small>
                                                            <?php if ($supplier['name_en']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($supplier['name_en']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                
                                                <td>
                                                    <span class="supplier-type-badge bg-<?php echo $supplier['supplier_type'] === 'company' ? 'primary' : 'secondary'; ?>">
                                                        <?php echo $supplier['supplier_type'] === 'company' ? 'شركة' : 'فرد'; ?>
                                                    </span>
                                                    <?php if ($supplier['specialization']): ?>
                                                        <br><span class="specialization-badge mt-1"><?php echo htmlspecialchars($supplier['specialization']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <?php if ($supplier['phone']): ?>
                                                        <div><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($supplier['phone']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($supplier['mobile']): ?>
                                                        <div><i class="fas fa-mobile-alt me-1"></i> <?php echo htmlspecialchars($supplier['mobile']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($supplier['email']): ?>
                                                        <div><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($supplier['email']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td><?php echo htmlspecialchars($supplier['city'] ?: 'غير محدد'); ?></td>
                                                
                                                <td>
                                                    <div class="supplier-stats">
                                                        <div class="stat-item">
                                                            <div class="stat-number"><?php echo number_format($supplier['total_orders']); ?></div>
                                                            <div class="stat-label">طلبات</div>
                                                        </div>
                                                        <div class="stat-item">
                                                            <div class="stat-number"><?php echo Utils::formatCurrency($supplier['total_purchases']); ?></div>
                                                            <div class="stat-label">إجمالي المشتريات</div>
                                                        </div>
                                                        <?php if ($supplier['outstanding_balance'] > 0): ?>
                                                        <div class="stat-item">
                                                            <div class="stat-number text-warning"><?php echo Utils::formatCurrency($supplier['outstanding_balance']); ?></div>
                                                            <div class="stat-label">مستحق</div>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                
                                                <td>
                                                    <?php if ($supplier['is_active']): ?>
                                                        <span class="status-badge bg-success">نشط</span>
                                                    <?php else: ?>
                                                        <span class="status-badge bg-danger">غير نشط</span>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary btn-action" onclick="viewSupplier(<?php echo $supplier['id']; ?>)" title="عرض">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <?php if (Session::hasPermission('suppliers_edit')): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-warning btn-action" onclick="editSupplier(<?php echo $supplier['id']; ?>)" title="تعديل">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            
                                                            <button type="button" class="btn btn-sm btn-outline-<?php echo $supplier['is_active'] ? 'danger' : 'success'; ?> btn-action" onclick="toggleStatus(<?php echo $supplier['id']; ?>)" title="<?php echo $supplier['is_active'] ? 'إلغاء تفعيل' : 'تفعيل'; ?>">
                                                                <i class="fas fa-<?php echo $supplier['is_active'] ? 'times' : 'check'; ?>"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (Session::hasPermission('suppliers_delete')): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger btn-action" onclick="deleteSupplier(<?php echo $supplier['id']; ?>)" title="حذف">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="p-3 border-top">
                                <?php echo Utils::generatePagination($page, $total_pages, 'suppliers.php', $_GET); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Supplier Modal -->
    <div class="modal fade" id="supplierModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة مورد جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST" id="supplierForm">
                    <?php echo Security::getCSRFInput(); ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id" id="supplier_id">
                    
                    <div class="modal-body">
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">اسم المورد <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="name_en" class="form-label">اسم المورد (بالإنجليزية)</label>
                                    <input type="text" class="form-control" id="name_en" name="name_en">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="supplier_type" class="form-label">نوع المورد</label>
                                    <select class="form-select" id="supplier_type" name="supplier_type">
                                        <option value="individual">فرد</option>
                                        <option value="company" selected>شركة</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="specialization" class="form-label">التخصص</label>
                                    <input type="text" class="form-control" id="specialization" name="specialization" placeholder="مثال: مواد غذائية، أجهزة كهربائية">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="contact_person" class="form-label">الشخص المسؤول</label>
                                    <input type="text" class="form-control" id="contact_person" name="contact_person">
                                </div>
                            </div>
                            
                            <!-- Contact Information -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">رقم الهاتف</label>
                                    <input type="tel" class="form-control" id="phone" name="phone">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mobile" class="form-label">رقم الجوال</label>
                                    <input type="tel" class="form-control" id="mobile" name="mobile">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">البريد الإلكتروني</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="city" class="form-label">المدينة</label>
                                    <input type="text" class="form-control" id="city" name="city">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="country" class="form-label">الدولة</label>
                                    <input type="text" class="form-control" id="country" name="country" value="Saudi Arabia">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="address" class="form-label">العنوان</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="credit_limit" class="form-label">الحد الائتماني</label>
                                    <input type="number" class="form-control" id="credit_limit" name="credit_limit" step="0.01" min="0" value="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="payment_terms" class="form-label">شروط الدفع</label>
                                    <input type="text" class="form-control" id="payment_terms" name="payment_terms" placeholder="مثال: خلال 30 يوم">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="tax_number" class="form-label">الرقم الضريبي</label>
                                    <input type="text" class="form-control" id="tax_number" name="tax_number">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="commercial_registration" class="form-label">السجل التجاري</label>
                                    <input type="text" class="form-control" id="commercial_registration" name="commercial_registration">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="notes" class="form-label">ملاحظات</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="4"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                    <label class="form-check-label" for="is_active">
                                        مورد نشط
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-warning">حفظ المورد</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Supplier Modal -->
    <div class="modal fade" id="viewSupplierModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تفاصيل المورد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="supplierDetails">
                    <!-- Supplier details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    
    <script>
        // Edit supplier
        function editSupplier(id) {
            fetch(`suppliers.php?action=get_supplier&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const supplier = data.supplier;
                        
                        // Fill form fields
                        document.getElementById('supplier_id').value = supplier.id;
                        document.querySelector('input[name="action"]').value = 'edit';
                        document.querySelector('.modal-title').textContent = 'تعديل المورد';
                        
                        // Fill all form fields
                        Object.keys(supplier).forEach(key => {
                            const field = document.getElementById(key);
                            if (field) {
                                if (field.type === 'checkbox') {
                                    field.checked = supplier[key] == 1;
                                } else {
                                    field.value = supplier[key] || '';
                                }
                            }
                        });
                        
                        // Show modal
                        new bootstrap.Modal(document.getElementById('supplierModal')).show();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء تحميل بيانات المورد');
                });
        }
        
        // View supplier
        function viewSupplier(id) {
            fetch(`suppliers.php?action=get_supplier&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const supplier = data.supplier;
                        
                        let html = '
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>المعلومات الأساسية</h6>
                                    <table class="table table-borderless">
                                        <tr><td><strong>رمز المورد:</strong></td><td>${supplier.code}</td></tr>
                                        <tr><td><strong>اسم المورد:</strong></td><td>${supplier.name}</td></tr>
                                        <tr><td><strong>الاسم الإنجليزي:</strong></td><td>${supplier.name_en || 'غير محدد'}</td></tr>
                                        <tr><td><strong>نوع المورد:</strong></td><td>${supplier.supplier_type === 'company' ? 'شركة' : 'فرد'}</td></tr>
                                        <tr><td><strong>التخصص:</strong></td><td>${supplier.specialization || 'غير محدد'}</td></tr>
                                        <tr><td><strong>الشخص المسؤول:</strong></td><td>${supplier.contact_person || 'غير محدد'}</td></tr>
                                        <tr><td><strong>الحالة:</strong></td><td>${supplier.is_active == 1 ? 'نشط' : 'غير نشط'}</td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6>معلومات الاتصال</h6>
                                    <table class="table table-borderless">
                                        <tr><td><strong>رقم الهاتف:</strong></td><td>${supplier.phone || 'غير محدد'}</td></tr>
                                        <tr><td><strong>رقم الجوال:</strong></td><td>${supplier.mobile || 'غير محدد'}</td></tr>
                                        <tr><td><strong>البريد الإلكتروني:</strong></td><td>${supplier.email || 'غير محدد'}</td></tr>
                                        <tr><td><strong>المدينة:</strong></td><td>${supplier.city || 'غير محدد'}</td></tr>
                                        <tr><td><strong>الدولة:</strong></td><td>${supplier.country || 'غير محدد'}</td></tr>
                                    </table>
                                </div>
                            </div>
                        ';
                        
                        if (supplier.address) {
                            html += '<div class="mt-3"><h6>العنوان</h6><p>${supplier.address}</p></div>';
                        }
                        
                        if (supplier.tax_number || supplier.commercial_registration) {
                            html += '
                                <div class="mt-3">
                                    <h6>المعلومات التجارية</h6>
                                    <table class="table table-borderless">
                                        <tr><td><strong>الرقم الضريبي:</strong></td><td>${supplier.tax_number || 'غير محدد'}</td></tr>
                                        <tr><td><strong>السجل التجاري:</strong></td><td>${supplier.commercial_registration || 'غير محدد'}</td></tr>
                                        <tr><td><strong>شروط الدفع:</strong></td><td>${supplier.payment_terms || 'غير محدد'}</td></tr>
                                        <tr><td><strong>الحد الائتماني:</strong></td><td>${parseFloat(supplier.credit_limit).toFixed(2)} ر.س</td></tr>
                                        <tr><td><strong>الرصيد الحالي:</strong></td><td>${parseFloat(supplier.current_balance).toFixed(2)} ر.س</td></tr>
                                    </table>
                                </div>
                            ';
                        }
                        
                        if (supplier.notes) {
                            html += '<div class="mt-3"><h6>الملاحظات</h6><p>${supplier.notes}</p></div>';
                        }
                        
                        document.getElementById('supplierDetails').innerHTML = html;
                        new bootstrap.Modal(document.getElementById('viewSupplierModal')).show();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء تحميل بيانات المورد');
                });
        }
        
        // Toggle supplier status
        function toggleStatus(id) {
            if (confirm('هل أنت متأكد من تغيير حالة المورد؟')) {
                fetch('suppliers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=toggle_status&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء تحديث حالة المورد');
                });
            }
        }
        
        // Delete supplier
        function deleteSupplier(id) {
            if (confirm('هل أنت متأكد من حذف هذا المورد؟ لا يمكن التراجع عن هذا الإجراء.')) {
                fetch('suppliers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=delete&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء حذف المورد');
                });
            }
        }
        
        // Reset form when modal is hidden
        document.getElementById('supplierModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('supplierForm').reset();
            document.getElementById('supplier_id').value = '';
            document.querySelector('input[name="action"]').value = 'add';
            document.querySelector('.modal-title').textContent = 'إضافة مورد جديد';
            document.getElementById('is_active').checked = true;
            document.getElementById('supplier_type').value = 'company';
            document.getElementById('country').value = 'Saudi Arabia';
        });
    </script>
</body>
</html>