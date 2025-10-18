<?php
/**
 * Products Management
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
        case 'get_product':
            $id = (int)($_GET['id'] ?? 0);
            if ($id) {
                $stmt = $pdo->prepare("
                    SELECT p.*, pc.name as category_name 
                    FROM products p
                    LEFT JOIN product_categories pc ON p.category_id = pc.id
                    WHERE p.id = ?
                ");
                $stmt->execute([$id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    // Get stock information
                    $stmt = $pdo->prepare("
                        SELECT ps.*, w.name as warehouse_name
                        FROM product_stock ps
                        JOIN warehouses w ON ps.warehouse_id = w.id
                        WHERE ps.product_id = ?
                    ");
                    $stmt->execute([$id]);
                    $product['stock'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    Utils::sendJsonResponse(['success' => true, 'product' => $product]);
                } else {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'المنتج غير موجود']);
                }
            }
            break;
            
        case 'toggle_status':
            Session::requirePermission('products_edit');
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $pdo->prepare("UPDATE products SET is_active = NOT is_active WHERE id = ?");
                if ($stmt->execute([$id])) {
                    Utils::logActivity($pdo, Session::getUserId(), 'toggle_product_status', 'products', $id);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'تم تحديث حالة المنتج']);
                } else {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'فشل في تحديث حالة المنتج']);
                }
            }
            break;
            
        case 'delete':
            Session::requirePermission('products_delete');
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                // Check if product is used in any transactions
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count FROM (
                        SELECT product_id FROM purchase_order_items WHERE product_id = ?
                        UNION ALL
                        SELECT product_id FROM sales_order_items WHERE product_id = ?
                        UNION ALL
                        SELECT product_id FROM stock_movements WHERE product_id = ?
                    ) as usage
                ");
                $stmt->execute([$id, $id, $id]);
                $usage = $stmt->fetch()['count'];
                
                if ($usage > 0) {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'لا يمكن حذف المنتج لأنه مستخدم في معاملات أخرى']);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        Utils::logActivity($pdo, Session::getUserId(), 'delete_product', 'products', $id);
                        Utils::sendJsonResponse(['success' => true, 'message' => 'تم حذف المنتج بنجاح']);
                    } else {
                        Utils::sendJsonResponse(['success' => false, 'message' => 'فشل في حذف المنتج']);
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
            Session::requirePermission('products_add');
        } else {
            Session::requirePermission('products_edit');
        }
        
        // Validate CSRF token
        if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'انتهت صلاحية الجلسة. يرجى المحاولة مرة أخرى.');
            header('Location: products.php');
            exit;
        }
        
        // Sanitize and validate input
        $data = [
            'sku' => Security::sanitizeInput($_POST['sku'] ?? ''),
            'barcode' => Security::sanitizeInput($_POST['barcode'] ?? ''),
            'name' => Security::sanitizeInput($_POST['name'] ?? ''),
            'name_en' => Security::sanitizeInput($_POST['name_en'] ?? ''),
            'description' => Security::sanitizeInput($_POST['description'] ?? ''),
            'category_id' => (int)($_POST['category_id'] ?? 0) ?: null,
            'unit' => Security::sanitizeInput($_POST['unit'] ?? 'piece'),
            'cost_price' => (float)($_POST['cost_price'] ?? 0),
            'selling_price' => (float)($_POST['selling_price'] ?? 0),
            'min_stock_level' => (int)($_POST['min_stock_level'] ?? 0),
            'max_stock_level' => (int)($_POST['max_stock_level'] ?? 0) ?: null,
            'weight' => (float)($_POST['weight'] ?? 0) ?: null,
            'dimensions' => Security::sanitizeInput($_POST['dimensions'] ?? '') ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_trackable' => isset($_POST['is_trackable']) ? 1 : 0,
            'notes' => Security::sanitizeInput($_POST['notes'] ?? '') ?: null
        ];
        
        $errors = [];
        
        // Validation
        if (empty($data['sku'])) {
            $errors[] = 'رمز المنتج مطلوب';
        }
        if (empty($data['name'])) {
            $errors[] = 'اسم المنتج مطلوب';
        }
        if ($data['cost_price'] < 0) {
            $errors[] = 'سعر التكلفة لا يمكن أن يكون سالباً';
        }
        if ($data['selling_price'] < 0) {
            $errors[] = 'سعر البيع لا يمكن أن يكون سالباً';
        }
        if ($data['min_stock_level'] < 0) {
            $errors[] = 'الحد الأدنى للمخزون لا يمكن أن يكون سالباً';
        }
        
        // Check for duplicate SKU
        $id = (int)($_POST['id'] ?? 0);
        $check_sql = "SELECT id FROM products WHERE sku = ?";
        $check_params = [$data['sku']];
        
        if ($action === 'edit' && $id) {
            $check_sql .= " AND id != ?";
            $check_params[] = $id;
        }
        
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute($check_params);
        if ($stmt->fetch()) {
            $errors[] = 'رمز المنتج موجود مسبقاً';
        }
        
        // Check for duplicate barcode (if provided)
        if (!empty($data['barcode'])) {
            $check_sql = "SELECT id FROM products WHERE barcode = ?";
            $check_params = [$data['barcode']];
            
            if ($action === 'edit' && $id) {
                $check_sql .= " AND id != ?";
                $check_params[] = $id;
            }
            
            $stmt = $pdo->prepare($check_sql);
            $stmt->execute($check_params);
            if ($stmt->fetch()) {
                $errors[] = 'الباركود موجود مسبقاً';
            }
        }
        
        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $data['created_by'] = Session::getUserId();
                    $sql = "INSERT INTO products (" . implode(', ', array_keys($data)) . ") VALUES (:" . implode(', :', array_keys($data)) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($data);
                    $product_id = $pdo->lastInsertId();
                    
                    Utils::logActivity($pdo, Session::getUserId(), 'add_product', 'products', $product_id, null, $data);
                    Session::setFlash('success', 'تم إضافة المنتج بنجاح');
                } else {
                    $sql = "UPDATE products SET " . implode(' = ?, ', array_keys($data)) . " = ? WHERE id = ?";
                    $params = array_values($data);
                    $params[] = $id;
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    Utils::logActivity($pdo, Session::getUserId(), 'edit_product', 'products', $id, null, $data);
                    Session::setFlash('success', 'تم تحديث المنتج بنجاح');
                }
                
                header('Location: products.php');
                exit;
            } catch (Exception $e) {
                error_log('Product save error: ' . $e->getMessage());
                Session::setFlash('error', 'حدث خطأ أثناء حفظ المنتج');
            }
        } else {
            Session::setFlash('error', implode('<br>', $errors));
        }
    }
}

// Get filters
$search = Security::sanitizeInput($_GET['search'] ?? '');
$category_filter = (int)($_GET['category'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$stock_filter = $_GET['stock'] ?? '';

// Build query
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "p.is_active = ?";
    $params[] = $status_filter;
}

if ($stock_filter === 'low') {
    $where_conditions[] = "EXISTS (SELECT 1 FROM product_stock ps WHERE ps.product_id = p.id AND ps.available_quantity <= p.min_stock_level)";
} elseif ($stock_filter === 'out') {
    $where_conditions[] = "NOT EXISTS (SELECT 1 FROM product_stock ps WHERE ps.product_id = p.id AND ps.available_quantity > 0)";
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(DISTINCT p.id) as total FROM products p WHERE $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;

// Get products
$sql = "
    SELECT p.*, 
           pc.name as category_name,
           COALESCE(SUM(ps.available_quantity), 0) as total_stock,
           COUNT(DISTINCT ps.warehouse_id) as warehouse_count
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    LEFT JOIN product_stock ps ON p.id = ps.product_id
    WHERE $where_clause
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $pdo->query("SELECT id, name FROM product_categories WHERE is_active = 1 ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'إدارة المنتجات';
$current_page = 'products';
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
        .product-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .product-image {
            height: 150px;
            background: linear-gradient(135deg, #008040ff 0%, #0ca559ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }
        
        .stock-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }
        
        .price-tag {
            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        .stock-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 5px;
        }
        
        .stock-normal { background-color: #28a745; }
        .stock-low { background-color: #ffc107; }
        .stock-out { background-color: #dc3545; }
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
                            <i class="fas fa-boxes me-2"></i>
                            إدارة المنتجات
                        </h2>
                        
                        <?php if (Session::hasPermission('products_add')): ?>
                        <div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal">
                                <i class="fas fa-plus me-2"></i>
                                إضافة منتج جديد
                            </button>
                            <button type="button" class="btn btn-outline-primary">
                                <i class="fas fa-upload me-2"></i>
                                استيراد منتجات
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
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="اسم المنتج، الرمز، أو الباركود">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">الفئة</label>
                                <select class="form-select" name="category">
                                    <option value="">جميع الفئات</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
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
                                <label class="form-label">حالة المخزون</label>
                                <select class="form-select" name="stock">
                                    <option value="">جميع المخزونات</option>
                                    <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>مخزون منخفض</option>
                                    <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>نفد المخزون</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>
                                        بحث
                                    </button>
                                    <a href="products.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo me-1"></i>
                                        إعادة تعيين
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Products Table -->
                    <div class="table-card">
                        <div class="table-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                قائمة المنتجات (<?php echo number_format($total_records); ?> منتج)
                            </h5>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>الصورة</th>
                                        <th>اسم المنتج</th>
                                        <th>الرمز</th>
                                        <th>الفئة</th>
                                        <th>سعر التكلفة</th>
                                        <th>سعر البيع</th>
                                        <th>المخزون</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-5 text-muted">
                                                <i class="fas fa-box-open fa-3x mb-3 d-block"></i>
                                                لا توجد منتجات لعرضها
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td>
                                                    <div class="product-image-sm">
                                                        <?php if ($product['image_path']): ?>
                                                            <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="صورة المنتج" class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; border-radius: 8px;">
                                                                <i class="fas fa-box text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                        <?php if ($product['name_en']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($product['name_en']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                
                                                <td>
                                                    <code><?php echo htmlspecialchars($product['sku']); ?></code>
                                                    <?php if ($product['barcode']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($product['barcode']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td><?php echo htmlspecialchars($product['category_name'] ?: 'غير محدد'); ?></td>
                                                
                                                <td><?php echo Utils::formatCurrency($product['cost_price']); ?></td>
                                                
                                                <td>
                                                    <span class="price-tag"><?php echo Utils::formatCurrency($product['selling_price']); ?></span>
                                                </td>
                                                
                                                <td>
                                                    <?php
                                                    $stock_class = 'stock-normal';
                                                    if ($product['total_stock'] <= 0) {
                                                        $stock_class = 'stock-out';
                                                    } elseif ($product['total_stock'] <= $product['min_stock_level']) {
                                                        $stock_class = 'stock-low';
                                                    }
                                                    ?>
                                                    <span class="stock-indicator <?php echo $stock_class; ?>"></span>
                                                    <?php echo number_format($product['total_stock']); ?>
                                                    <?php if ($product['warehouse_count'] > 1): ?>
                                                        <small class="text-muted">(<?php echo $product['warehouse_count']; ?> مخازن)</small>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <?php if ($product['is_active']): ?>
                                                        <span class="status-badge bg-success">نشط</span>
                                                    <?php else: ?>
                                                        <span class="status-badge bg-danger">غير نشط</span>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary btn-action" onclick="viewProduct(<?php echo $product['id']; ?>)" title="عرض">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <?php if (Session::hasPermission('products_edit')): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-warning btn-action" onclick="editProduct(<?php echo $product['id']; ?>)" title="تعديل">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            
                                                            <button type="button" class="btn btn-sm btn-outline-<?php echo $product['is_active'] ? 'danger' : 'success'; ?> btn-action" onclick="toggleStatus(<?php echo $product['id']; ?>)" title="<?php echo $product['is_active'] ? 'إلغاء تفعيل' : 'تفعيل'; ?>">
                                                                <i class="fas fa-<?php echo $product['is_active'] ? 'times' : 'check'; ?>"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (Session::hasPermission('products_delete')): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger btn-action" onclick="deleteProduct(<?php echo $product['id']; ?>)" title="حذف">
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
                                <?php echo Utils::generatePagination($page, $total_pages, 'products.php', $_GET); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة منتج جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST" id="productForm">
                    <?php echo Security::getCSRFInput(); ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id" id="product_id">
                    
                    <div class="modal-body">
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sku" class="form-label">رمز المنتج <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="sku" name="sku" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="barcode" class="form-label">الباركود</label>
                                    <input type="text" class="form-control" id="barcode" name="barcode">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">اسم المنتج <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="name_en" class="form-label">اسم المنتج (بالإنجليزية)</label>
                                    <input type="text" class="form-control" id="name_en" name="name_en">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">الفئة</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="">اختر الفئة</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Pricing & Stock -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="unit" class="form-label">الوحدة</label>
                                    <select class="form-select" id="unit" name="unit">
                                        <option value="piece">قطعة</option>
                                        <option value="kg">كيلوجرام</option>
                                        <option value="liter">لتر</option>
                                        <option value="meter">متر</option>
                                        <option value="box">صندوق</option>
                                        <option value="pack">عبوة</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="cost_price" class="form-label">سعر التكلفة</label>
                                    <input type="number" class="form-control" id="cost_price" name="cost_price" step="0.01" min="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="selling_price" class="form-label">سعر البيع</label>
                                    <input type="number" class="form-control" id="selling_price" name="selling_price" step="0.01" min="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="min_stock_level" class="form-label">الحد الأدنى للمخزون</label>
                                    <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" min="0" value="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="max_stock_level" class="form-label">الحد الأقصى للمخزون</label>
                                    <input type="number" class="form-control" id="max_stock_level" name="max_stock_level" min="0">
                                </div>
                            </div>
                            
                            <!-- Additional Info -->
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="description" class="form-label">الوصف</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="weight" class="form-label">الوزن (كجم)</label>
                                            <input type="number" class="form-control" id="weight" name="weight" step="0.001" min="0">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="dimensions" class="form-label">الأبعاد</label>
                                            <input type="text" class="form-control" id="dimensions" name="dimensions" placeholder="الطول × العرض × الارتفاع">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="notes" class="form-label">ملاحظات</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                </div>
                                
                                <!-- Checkboxes -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                            <label class="form-check-label" for="is_active">نشط</label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_trackable" name="is_trackable" checked>
                                            <label class="form-check-label" for="is_trackable">تتبع المخزون</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                        <button type="submit" class="btn btn-primary">حفظ المنتج</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Product Modal -->
    <div class="modal fade" id="viewProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">عرض تفاصيل المنتج</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body" id="productDetails">
                    <!-- Product details will be loaded here -->
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
        // Edit product
        function editProduct(id) {
            fetch(`products.php?action=get_product&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const product = data.product;
                        
                        // Fill form fields
                        document.getElementById('product_id').value = product.id;
                        document.querySelector('input[name="action"]').value = 'edit';
                        document.querySelector('.modal-title').textContent = 'تعديل المنتج';
                        
                        // Fill all form fields
                        Object.keys(product).forEach(key => {
                            const field = document.getElementById(key);
                            if (field) {
                                if (field.type === 'checkbox') {
                                    field.checked = product[key] == 1;
                                } else {
                                    field.value = product[key] || '';
                                }
                            }
                        });
                        
                        // Show modal
                        new bootstrap.Modal(document.getElementById('productModal')).show();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء تحميل بيانات المنتج');
                });
        }
        
        // View product
        function viewProduct(id) {
            fetch(`products.php?action=get_product&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const product = data.product;
                        
                        let html = '
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>المعلومات الأساسية</h6>
                                    <table class="table table-borderless">
                                        <tr><td><strong>رمز المنتج:</strong></td><td>${product.sku}</td></tr>
                                        <tr><td><strong>الباركود:</strong></td><td>${product.barcode || 'غير محدد'}</td></tr>
                                        <tr><td><strong>اسم المنتج:</strong></td><td>${product.name}</td></tr>
                                        <tr><td><strong>الاسم الإنجليزي:</strong></td><td>${product.name_en || 'غير محدد'}</td></tr>
                                        <tr><td><strong>الفئة:</strong></td><td>${product.category_name || 'غير محدد'}</td></tr>
                                        <tr><td><strong>الوحدة:</strong></td><td>${product.unit}</td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6>التسعير والمخزون</h6>
                                    <table class="table table-borderless">
                                        <tr><td><strong>سعر التكلفة:</strong></td><td>${parseFloat(product.cost_price).toFixed(2)} ر.س</td></tr>
                                        <tr><td><strong>سعر البيع:</strong></td><td>${parseFloat(product.selling_price).toFixed(2)} ر.س</td></tr>
                                        <tr><td><strong>الحد الأدنى:</strong></td><td>${product.min_stock_level}</td></tr>
                                        <tr><td><strong>الحد الأقصى:</strong></td><td>${product.max_stock_level || 'غير محدد'}</td></tr>
                                        <tr><td><strong>الوزن:</strong></td><td>${product.weight || 'غير محدد'} كجم</td></tr>
                                        <tr><td><strong>الأبعاد:</strong></td><td>${product.dimensions || 'غير محدد'}</td></tr>
                                    </table>
                                </div>
                            </div>
                        ';
                        
                        if (product.description) {
                            html += '<div class="mt-3"><h6>الوصف</h6><p>${product.description}</p></div>';
                        }
                        
                        if (product.stock && product.stock.length > 0) {
                            html += '
                                <div class="mt-3">
                                    <h6>المخزون حسب المخزن</h6>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>المخزن</th>
                                                <th>الكمية الإجمالية</th>
                                                <th>الكمية المحجوزة</th>
                                                <th>الكمية المتاحة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                            ';
                            
                            product.stock.forEach(stock => {
                                html += '
                                    <tr>
                                        <td>${stock.warehouse_name}</td>
                                        <td>${stock.quantity}</td>
                                        <td>${stock.reserved_quantity}</td>
                                        <td>${stock.available_quantity}</td>
                                    </tr>
                                ';
                            });
                            
                            html += '</tbody></table></div>';
                        }
                        
                        document.getElementById('productDetails').innerHTML = html;
                        new bootstrap.Modal(document.getElementById('viewProductModal')).show();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء تحميل بيانات المنتج');
                });
        }
        
        // Toggle product status
        function toggleStatus(id) {
            if (confirm('هل أنت متأكد من تغيير حالة المنتج؟')) {
                fetch('products.php', {
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
                    alert('حدث خطأ أثناء تحديث حالة المنتج');
                });
            }
        }
        
        // Delete product
        function deleteProduct(id) {
            if (confirm('هل أنت متأكد من حذف هذا المنتج؟ لا يمكن التراجع عن هذا الإجراء.')) {
                fetch('products.php', {
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
                    alert('حدث خطأ أثناء حذف المنتج');
                });
            }
        }
        
        // Reset form when modal is hidden
        document.getElementById('productModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('productForm').reset();
            document.getElementById('product_id').value = '';
            document.querySelector('input[name="action"]').value = 'add';
            document.querySelector('.modal-title').textContent = 'إضافة منتج جديد';
        });
        
        // Auto-generate SKU if empty
        document.getElementById('name').addEventListener('input', function() {
            const skuField = document.getElementById('sku');
            if (!skuField.value) {
                const name = this.value.replace(/\s+/g, '').toUpperCase();
                const timestamp = Date.now().toString().slice(-4);
                skuField.value = name.substring(0, 4) + timestamp;
            }
        });
    </script>
</body>
</html>
