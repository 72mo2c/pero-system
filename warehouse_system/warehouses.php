<?php
/**
 * Warehouses Management
 * Created: 2025-10-16
 */

require_once '../config/config.php';

// Initialize security and session
Security::initialize();
Session::initialize();

// Check authentication
Session::requireTenantLogin();

// Check admin/manager permissions
if (!in_array(Session::getUserRole(), ['admin', 'manager'])) {
    Session::setFlash('error', 'ليس لديك صلاحية للوصول إلى هذه الصفحة');
    header('Location: dashboard.php');
    exit;
}

// Get database connection
$pdo = DatabaseConfig::getTenantConnection(Session::getTenantId());

// Handle AJAX requests
if (Utils::isAjax()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_warehouse':
            $id = (int)($_GET['id'] ?? 0);
            if ($id) {
                $stmt = $pdo->prepare("
                    SELECT w.*, u.full_name as manager_name 
                    FROM warehouses w
                    LEFT JOIN users u ON w.manager_id = u.id
                    WHERE w.id = ?
                ");
                $stmt->execute([$id]);
                $warehouse = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($warehouse) {
                    Utils::sendJsonResponse(['success' => true, 'warehouse' => $warehouse]);
                } else {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'المخزن غير موجود']);
                }
            }
            break;
            
        case 'toggle_status':
            Session::requirePermission('warehouses_edit');
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $pdo->prepare("UPDATE warehouses SET is_active = NOT is_active WHERE id = ?");
                if ($stmt->execute([$id])) {
                    Utils::logActivity($pdo, Session::getUserId(), 'toggle_warehouse_status', 'warehouses', $id);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'تم تحديث حالة المخزن']);
                } else {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'فشل في تحديث حالة المخزن']);
                }
            }
            break;
            
        case 'delete':
            Session::requirePermission('warehouses_delete');
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                // Check if warehouse is used in any transactions
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count FROM (
                        SELECT warehouse_id FROM sales_orders WHERE warehouse_id = ?
                        UNION ALL
                        SELECT warehouse_id FROM purchase_orders WHERE warehouse_id = ?
                        UNION ALL
                        SELECT warehouse_id FROM stock_movements WHERE warehouse_id = ?
                    ) as usage
                ");
                $stmt->execute([$id, $id, $id]);
                $usage = $stmt->fetch()['count'];
                
                if ($usage > 0) {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'لا يمكن حذف المخزن لأنه مستخدم في معاملات أخرى']);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM warehouses WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        Utils::logActivity($pdo, Session::getUserId(), 'delete_warehouse', 'warehouses', $id);
                        Utils::sendJsonResponse(['success' => true, 'message' => 'تم حذف المخزن بنجاح']);
                    } else {
                        Utils::sendJsonResponse(['success' => false, 'message' => 'فشل في حذف المخزن']);
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
            Session::requirePermission('warehouses_add');
        } else {
            Session::requirePermission('warehouses_edit');
        }
        
        // Validate CSRF token
        if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'انتهت صلاحية الجلسة. يرجى المحاولة مرة أخرى.');
            header('Location: warehouses.php');
            exit;
        }
        
        // Sanitize and validate input
        $data = [
            'code' => Security::sanitizeInput($_POST['code'] ?? ''),
            'name' => Security::sanitizeInput($_POST['name'] ?? ''),
            'name_en' => Security::sanitizeInput($_POST['name_en'] ?? ''),
            'description' => Security::sanitizeInput($_POST['description'] ?? '') ?: null,
            'address' => Security::sanitizeInput($_POST['address'] ?? '') ?: null,
            'city' => Security::sanitizeInput($_POST['city'] ?? '') ?: null,
            'phone' => Security::sanitizeInput($_POST['phone'] ?? '') ?: null,
            'manager_id' => (int)($_POST['manager_id'] ?? 0) ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        $errors = [];
        
        // Validation
        if (empty($data['code'])) {
            $errors[] = 'رمز المخزن مطلوب';
        }
        if (empty($data['name'])) {
            $errors[] = 'اسم المخزن مطلوب';
        }
        
        // Check for duplicate code
        $id = (int)($_POST['id'] ?? 0);
        $check_sql = "SELECT id FROM warehouses WHERE code = ?";
        $check_params = [$data['code']];
        
        if ($action === 'edit' && $id) {
            $check_sql .= " AND id != ?";
            $check_params[] = $id;
        }
        
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute($check_params);
        if ($stmt->fetch()) {
            $errors[] = 'رمز المخزن موجود مسبقاً';
        }
        
        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $data['created_by'] = Session::getUserId();
                    $sql = "INSERT INTO warehouses (" . implode(', ', array_keys($data)) . ") VALUES (:" . implode(', :', array_keys($data)) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($data);
                    $warehouse_id = $pdo->lastInsertId();
                    
                    Utils::logActivity($pdo, Session::getUserId(), 'add_warehouse', 'warehouses', $warehouse_id, null, $data);
                    Session::setFlash('success', 'تم إضافة المخزن بنجاح');
                } else {
                    $sql = "UPDATE warehouses SET " . implode(' = ?, ', array_keys($data)) . " = ? WHERE id = ?";
                    $params = array_values($data);
                    $params[] = $id;
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    Utils::logActivity($pdo, Session::getUserId(), 'edit_warehouse', 'warehouses', $id, null, $data);
                    Session::setFlash('success', 'تم تحديث المخزن بنجاح');
                }
                
                header('Location: warehouses.php');
                exit;
            } catch (Exception $e) {
                error_log('Warehouse save error: ' . $e->getMessage());
                Session::setFlash('error', 'حدث خطأ أثناء حفظ المخزن');
            }
        } else {
            Session::setFlash('error', implode('<br>', $errors));
        }
    }
}

// Get filters
$search = Security::sanitizeInput($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$city_filter = Security::sanitizeInput($_GET['city'] ?? '');

// Build query
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(w.name LIKE ? OR w.code LIKE ? OR w.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter !== '') {
    $where_conditions[] = "w.is_active = ?";
    $params[] = $status_filter;
}

if (!empty($city_filter)) {
    $where_conditions[] = "w.city LIKE ?";
    $params[] = "%$city_filter%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM warehouses w WHERE $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;

// Get warehouses
$sql = "
    SELECT w.*, 
           u.full_name as manager_name,
           COUNT(DISTINCT ps.product_id) as total_products,
           COALESCE(SUM(ps.available_quantity), 0) as total_stock_value
    FROM warehouses w
    LEFT JOIN users u ON w.manager_id = u.id
    LEFT JOIN product_stock ps ON w.id = ps.warehouse_id
    WHERE $where_clause
    GROUP BY w.id
    ORDER BY w.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get managers for form
$stmt = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'manager') AND is_active = 1 ORDER BY full_name");
$managers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get cities for filter
$stmt = $pdo->query("SELECT DISTINCT city FROM warehouses WHERE city IS NOT NULL AND city != '' ORDER BY city");
$cities = $stmt->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'إدارة المخازن';
$current_page = 'warehouses';
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
        .warehouse-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .warehouse-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .warehouse-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #20c997 0%, #198754 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
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
            background: linear-gradient(135deg, #20c997 0%, #198754 100%);
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
        
        .warehouse-stats {
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
            color: #20c997;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .warehouse-code {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #495057;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
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
                            <i class="fas fa-warehouse me-2"></i>
                            إدارة المخازن
                        </h2>
                        
                        <?php if (Session::hasPermission('warehouses_add')): ?>
                        <div>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#warehouseModal">
                                <i class="fas fa-plus me-2"></i>
                                إضافة مخزن جديد
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
                            <div class="col-md-4">
                                <label class="form-label">البحث</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="اسم المخزن، الرمز، أو رقم الهاتف">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">الحالة</label>
                                <select class="form-select" name="status">
                                    <option value="">جميع الحالات</option>
                                    <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>نشط</option>
                                    <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>غير نشط</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
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
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>
                                        بحث
                                    </button>
                                    <a href="warehouses.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo me-1"></i>
                                        إعادة تعيين
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Warehouses Table -->
                    <div class="table-card">
                        <div class="table-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                قائمة المخازن (<?php echo number_format($total_records); ?> مخزن)
                            </h5>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>المخزن</th>
                                        <th>المدير</th>
                                        <th>الموقع</th>
                                        <th>معلومات الاتصال</th>
                                        <th>إحصائيات</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($warehouses)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="fas fa-warehouse fa-3x mb-3 d-block"></i>
                                                لا توجد مخازن لعرضها
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($warehouses as $warehouse): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="warehouse-icon me-3">
                                                            <i class="fas fa-warehouse"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($warehouse['name']); ?></strong>
                                                            <br><span class="warehouse-code"><?php echo htmlspecialchars($warehouse['code']); ?></span>
                                                            <?php if ($warehouse['name_en']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($warehouse['name_en']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                
                                                <td>
                                                    <?php if ($warehouse['manager_name']): ?>
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($warehouse['manager_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">لا يوجد مدير</span>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <?php if ($warehouse['city']): ?>
                                                        <div><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($warehouse['city']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($warehouse['address']): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($warehouse['address']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <?php if ($warehouse['phone']): ?>
                                                        <div><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($warehouse['phone']); ?></div>
                                                    <?php else: ?>
                                                        <span class="text-muted">لا يوجد</span>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <div class="warehouse-stats">
                                                        <div class="stat-item">
                                                            <div class="stat-number"><?php echo number_format($warehouse['total_products']); ?></div>
                                                            <div class="stat-label">منتجات</div>
                                                        </div>
                                                        <div class="stat-item">
                                                            <div class="stat-number"><?php echo number_format($warehouse['total_stock_value']); ?></div>
                                                            <div class="stat-label">قطعة</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                
                                                <td>
                                                    <?php if ($warehouse['is_active']): ?>
                                                        <span class="status-badge bg-success">نشط</span>
                                                    <?php else: ?>
                                                        <span class="status-badge bg-danger">غير نشط</span>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary btn-action" onclick="viewWarehouse(<?php echo $warehouse['id']; ?>)" title="عرض">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <?php if (Session::hasPermission('warehouses_edit')): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-warning btn-action" onclick="editWarehouse(<?php echo $warehouse['id']; ?>)" title="تعديل">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            
                                                            <button type="button" class="btn btn-sm btn-outline-<?php echo $warehouse['is_active'] ? 'danger' : 'success'; ?> btn-action" onclick="toggleStatus(<?php echo $warehouse['id']; ?>)" title="<?php echo $warehouse['is_active'] ? 'إلغاء تفعيل' : 'تفعيل'; ?>">
                                                                <i class="fas fa-<?php echo $warehouse['is_active'] ? 'times' : 'check'; ?>"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (Session::hasPermission('warehouses_delete')): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger btn-action" onclick="deleteWarehouse(<?php echo $warehouse['id']; ?>)" title="حذف">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <a href="warehouse_stock.php?warehouse_id=<?php echo $warehouse['id']; ?>" class="btn btn-sm btn-outline-info btn-action" title="المخزون">
                                                            <i class="fas fa-boxes"></i>
                                                        </a>
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
                                <?php echo Utils::generatePagination($page, $total_pages, 'warehouses.php', $_GET); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Warehouse Modal -->
    <div class="modal fade" id="warehouseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة مخزن جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST" id="warehouseForm">
                    <?php echo Security::getCSRFInput(); ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id" id="warehouse_id">
                    
                    <div class="modal-body">
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="code" class="form-label">رمز المخزن <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="code" name="code" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">اسم المخزن <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="name_en" class="form-label">اسم المخزن (بالإنجليزية)</label>
                                    <input type="text" class="form-control" id="name_en" name="name_en">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="manager_id" class="form-label">مدير المخزن</label>
                                    <select class="form-select" id="manager_id" name="manager_id">
                                        <option value="">اختر مدير المخزن</option>
                                        <?php foreach ($managers as $manager): ?>
                                            <option value="<?php echo $manager['id']; ?>"><?php echo htmlspecialchars($manager['full_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Location & Contact -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="city" class="form-label">المدينة</label>
                                    <input type="text" class="form-control" id="city" name="city">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">رقم الهاتف</label>
                                    <input type="tel" class="form-control" id="phone" name="phone">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">العنوان</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="description" class="form-label">الوصف</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                    <label class="form-check-label" for="is_active">
                                        مخزن نشط
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-success">حفظ المخزن</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Warehouse Modal -->
    <div class="modal fade" id="viewWarehouseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تفاصيل المخزن</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="warehouseDetails">
                    <!-- Warehouse details will be loaded here -->
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
        // Edit warehouse
        function editWarehouse(id) {
            fetch('warehouses.php?action=get_warehouse&id=${id}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const warehouse = data.warehouse;
                        
                        // Fill form fields
                        document.getElementById('warehouse_id').value = warehouse.id;
                        document.querySelector('input[name="action"]').value = 'edit';
                        document.querySelector('.modal-title').textContent = 'تعديل المخزن';
                        
                        // Fill all form fields
                        Object.keys(warehouse).forEach(key => {
                            const field = document.getElementById(key);
                            if (field) {
                                if (field.type === 'checkbox') {
                                    field.checked = warehouse[key] == 1;
                                } else {
                                    field.value = warehouse[key] || '';
                                }
                            }
                        });
                        
                        // Show modal
                        new bootstrap.Modal(document.getElementById('warehouseModal')).show();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء تحميل بيانات المخزن');
                });
        }
        
        // View warehouse
        function viewWarehouse(id) {
            fetch('warehouses.php?action=get_warehouse&id=${id}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const warehouse = data.warehouse;
                        
                        let html = '
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>المعلومات الأساسية</h6>
                                    <table class="table table-borderless">
                                        <tr><td><strong>رمز المخزن:</strong></td><td>${warehouse.code}</td></tr>
                                        <tr><td><strong>اسم المخزن:</strong></td><td>${warehouse.name}</td></tr>
                                        <tr><td><strong>الاسم الإنجليزي:</strong></td><td>${warehouse.name_en || 'غير محدد'}</td></tr>
                                        <tr><td><strong>مدير المخزن:</strong></td><td>${warehouse.manager_name || 'لا يوجد مدير'}</td></tr>
                                        <tr><td><strong>الحالة:</strong></td><td>${warehouse.is_active == 1 ? 'نشط' : 'غير نشط'}</td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6>معلومات الاتصال والموقع</h6>
                                    <table class="table table-borderless">
                                        <tr><td><strong>المدينة:</strong></td><td>${warehouse.city || 'غير محدد'}</td></tr>
                                        <tr><td><strong>رقم الهاتف:</strong></td><td>${warehouse.phone || 'غير محدد'}</td></tr>
                                        <tr><td><strong>تاريخ الإنشاء:</strong></td><td>${new Date(warehouse.created_at).toLocaleDateString('ar-SA')}</td></tr>
                                    </table>
                                </div>
                            </div>
                        ';
                        
                        if (warehouse.address) {
                            html += '<div class="mt-3"><h6>العنوان</h6><p>${warehouse.address}</p></div>';
                        }
                        
                        if (warehouse.description) {
                            html += '<div class="mt-3"><h6>الوصف</h6><p>${warehouse.description}</p></div>';
                        }
                        
                        document.getElementById('warehouseDetails').innerHTML = html;
                        new bootstrap.Modal(document.getElementById('viewWarehouseModal')).show();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء تحميل بيانات المخزن');
                });
        }
        
        // Toggle warehouse status
        function toggleStatus(id) {
            if (confirm('هل أنت متأكد من تغيير حالة المخزن؟')) {
                fetch('warehouses.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=toggle_status&id=${id}'
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
                    alert('حدث خطأ أثناء تحديث حالة المخزن');
                });
            }
        }
        
        // Delete warehouse
        function deleteWarehouse(id) {
            if (confirm('هل أنت متأكد من حذف هذا المخزن؟ لا يمكن التراجع عن هذا الإجراء.')) {
                fetch('warehouses.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=delete&id=${id}'
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
                    alert('حدث خطأ أثناء حذف المخزن');
                });
            }
        }
        
        // Reset form when modal is hidden
        document.getElementById('warehouseModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('warehouseForm').reset();
            document.getElementById('warehouse_id').value = '';
            document.querySelector('input[name="action"]').value = 'add';
            document.querySelector('.modal-title').textContent = 'إضافة مخزن جديد';
            document.getElementById('is_active').checked = true;
        });
        
        // Auto-generate warehouse code when name is entered
        document.getElementById('name').addEventListener('input', function() {
            const codeField = document.getElementById('code');
            if (!codeField.value) {
                const name = this.value.replace(/\s+/g, '').toUpperCase();
                const timestamp = Date.now().toString().slice(-4);
                codeField.value = 'WH-' + name.substring(0, 3) + timestamp;
            }
        });
    </script>
</body>
</html>