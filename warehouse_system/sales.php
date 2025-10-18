<?php
/**
 * Sales Management
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
        case 'get_sale':
            $id = (int)($_GET['id'] ?? 0);
            if ($id) {
                $stmt = $pdo->prepare("
                    SELECT so.*, c.name as customer_name, c.phone as customer_phone, 
                           w.name as warehouse_name, u.full_name as creator_name
                    FROM sales_orders so
                    LEFT JOIN customers c ON so.customer_id = c.id
                    LEFT JOIN warehouses w ON so.warehouse_id = w.id
                    LEFT JOIN users u ON so.created_by = u.id
                    WHERE so.id = ?
                ");
                $stmt->execute([$id]);
                $sale = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($sale) {
                    // Get sale items
                    $stmt = $pdo->prepare("
                        SELECT soi.*, p.name as product_name, p.sku as product_sku
                        FROM sales_order_items soi
                        JOIN products p ON soi.product_id = p.id
                        WHERE soi.sales_order_id = ?
                    ");
                    $stmt->execute([$id]);
                    $sale['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    Utils::sendJsonResponse(['success' => true, 'sale' => $sale]);
                } else {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'عملية البيع غير موجودة']);
                }
            }
            break;
            
        case 'get_customer_info':
            $customer_id = (int)($_GET['customer_id'] ?? 0);
            if ($customer_id) {
                $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND is_active = 1");
                $stmt->execute([$customer_id]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($customer) {
                    Utils::sendJsonResponse(['success' => true, 'customer' => $customer]);
                } else {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'العميل غير موجود']);
                }
            }
            break;
            
        case 'get_product_price':
            $product_id = (int)($_GET['product_id'] ?? 0);
            if ($product_id) {
                $stmt = $pdo->prepare("SELECT selling_price, name FROM products WHERE id = ? AND is_active = 1");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    Utils::sendJsonResponse(['success' => true, 'product' => $product]);
                } else {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'المنتج غير موجود']);
                }
            }
            break;
            
        case 'update_status':
            Session::requirePermission('sales_edit');
            $id = (int)($_POST['id'] ?? 0);
            $status = Security::sanitizeInput($_POST['status'] ?? '');
            
            $valid_statuses = ['draft', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
            
            if ($id && in_array($status, $valid_statuses)) {
                $stmt = $pdo->prepare("UPDATE sales_orders SET status = ? WHERE id = ?");
                if ($stmt->execute([$status, $id])) {
                    Utils::logActivity($pdo, Session::getUserId(), 'update_sale_status', 'sales_orders', $id, null, ['status' => $status]);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'تم تحديث حالة المبيعات']);
                } else {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'فشل في تحديث حالة المبيعات']);
                }
            } else {
                Utils::sendJsonResponse(['success' => false, 'message' => 'بيانات غير صحيحة']);
            }
            break;
            
        case 'delete':
            Session::requirePermission('sales_delete');
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                // Check if sale can be deleted (only draft orders)
                $stmt = $pdo->prepare("SELECT status FROM sales_orders WHERE id = ?");
                $stmt->execute([$id]);
                $sale = $stmt->fetch();
                
                if (!$sale) {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'عملية البيع غير موجودة']);
                } elseif ($sale['status'] !== 'draft') {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'لا يمكن حذف عملية بيع مؤكدة']);
                } else {
                    $pdo->beginTransaction();
                    try {
                        // Delete sale items first
                        $stmt = $pdo->prepare("DELETE FROM sales_order_items WHERE sales_order_id = ?");
                        $stmt->execute([$id]);
                        
                        // Delete sale order
                        $stmt = $pdo->prepare("DELETE FROM sales_orders WHERE id = ?");
                        $stmt->execute([$id]);
                        
                        $pdo->commit();
                        Utils::logActivity($pdo, Session::getUserId(), 'delete_sale', 'sales_orders', $id);
                        Utils::sendJsonResponse(['success' => true, 'message' => 'تم حذف عملية البيع بنجاح']);
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        Utils::sendJsonResponse(['success' => false, 'message' => 'فشل في حذف عملية البيع']);
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
            Session::requirePermission('sales_add');
        } else {
            Session::requirePermission('sales_edit');
        }
        
        // Validate CSRF token
        if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'انتهت صلاحية الجلسة. يرجى المحاولة مرة أخرى.');
            header('Location: sales.php');
            exit;
        }
        
        // Sanitize and validate input
        $data = [
            'customer_id' => (int)($_POST['customer_id'] ?? 0),
            'warehouse_id' => (int)($_POST['warehouse_id'] ?? 0),
            'so_date' => Security::sanitizeInput($_POST['so_date'] ?? ''),
            'delivery_date' => Security::sanitizeInput($_POST['delivery_date'] ?? '') ?: null,
            'shipping_address' => Security::sanitizeInput($_POST['shipping_address'] ?? '') ?: null,
            'notes' => Security::sanitizeInput($_POST['notes'] ?? '') ?: null,
            'terms_conditions' => Security::sanitizeInput($_POST['terms_conditions'] ?? '') ?: null
        ];
        
        $items = $_POST['items'] ?? [];
        
        $errors = [];
        
        // Validation
        if (empty($data['customer_id'])) {
            $errors[] = 'يرجى اختيار العميل';
        }
        if (empty($data['warehouse_id'])) {
            $errors[] = 'يرجى اختيار المخزن';
        }
        if (empty($data['so_date'])) {
            $errors[] = 'تاريخ البيع مطلوب';
        }
        if (empty($items)) {
            $errors[] = 'يرجى إضافة منتج واحد على الأقل';
        }
        
        // Validate items
        $subtotal = 0;
        foreach ($items as $index => $item) {
            if (empty($item['product_id']) || empty($item['quantity']) || empty($item['unit_price'])) {
                $errors[] = "بيانات المنتج رقم " . ($index + 1) . " غير مكتملة";
            } else {
                $quantity = (int)$item['quantity'];
                $unit_price = (float)$item['unit_price'];
                $discount_amount = (float)($item['discount_amount'] ?? 0);
                
                if ($quantity <= 0) {
                    $errors[] = "كمية المنتج رقم " . ($index + 1) . " يجب أن تكون أكبر من صفر";
                }
                if ($unit_price <= 0) {
                    $errors[] = "سعر المنتج رقم " . ($index + 1) . " يجب أن يكون أكبر من صفر";
                }
                
                $line_total = ($quantity * $unit_price) - $discount_amount;
                $subtotal += $line_total;
            }
        }
        
        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                $id = (int)($_POST['id'] ?? 0);
                
                if ($action === 'add') {
                    // Generate SO number
                    $so_number = 'SO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Check if SO number exists
                    $stmt = $pdo->prepare("SELECT id FROM sales_orders WHERE so_number = ?");
                    $stmt->execute([$so_number]);
                    while ($stmt->fetch()) {
                        $so_number = 'SO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $stmt->execute([$so_number]);
                    }
                    
                    $data['so_number'] = $so_number;
                    $data['subtotal'] = $subtotal;
                    $data['total_amount'] = $subtotal; // Can be modified later with taxes/shipping
                    $data['status'] = 'draft';
                    $data['payment_status'] = 'pending';
                    $data['created_by'] = Session::getUserId();
                    
                    $sql = "INSERT INTO sales_orders (" . implode(', ', array_keys($data)) . ") VALUES (:" . implode(', :', array_keys($data)) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($data);
                    $sale_id = $pdo->lastInsertId();
                    
                    Utils::logActivity($pdo, Session::getUserId(), 'add_sale', 'sales_orders', $sale_id, null, $data);
                    Session::setFlash('success', 'تم إضافة عملية البيع بنجاح - رقم الفاتورة: ' . $so_number);
                } else {
                    // Update existing sale (only if still draft)
                    $stmt = $pdo->prepare("SELECT status FROM sales_orders WHERE id = ?");
                    $stmt->execute([$id]);
                    $current_sale = $stmt->fetch();
                    
                    if (!$current_sale) {
                        throw new Exception('عملية البيع غير موجودة');
                    }
                    if ($current_sale['status'] !== 'draft') {
                        throw new Exception('لا يمكن تعديل عملية بيع مؤكدة');
                    }
                    
                    $data['subtotal'] = $subtotal;
                    $data['total_amount'] = $subtotal;
                    
                    $sql = "UPDATE sales_orders SET " . implode(' = ?, ', array_keys($data)) . " = ? WHERE id = ?";
                    $params = array_values($data);
                    $params[] = $id;
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $sale_id = $id;
                    
                    // Delete existing items
                    $stmt = $pdo->prepare("DELETE FROM sales_order_items WHERE sales_order_id = ?");
                    $stmt->execute([$sale_id]);
                    
                    Utils::logActivity($pdo, Session::getUserId(), 'edit_sale', 'sales_orders', $sale_id, null, $data);
                    Session::setFlash('success', 'تم تحديث عملية البيع بنجاح');
                }
                
                // Insert sale items
                foreach ($items as $item) {
                    $quantity = (int)$item['quantity'];
                    $unit_price = (float)$item['unit_price'];
                    $discount_amount = (float)($item['discount_amount'] ?? 0);
                    $total_price = ($quantity * $unit_price) - $discount_amount;
                    
                    $item_data = [
                        'sales_order_id' => $sale_id,
                        'product_id' => (int)$item['product_id'],
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'discount_amount' => $discount_amount,
                        'total_price' => $total_price,
                        'notes' => Security::sanitizeInput($item['notes'] ?? '') ?: null
                    ];
                    
                    $sql = "INSERT INTO sales_order_items (" . implode(', ', array_keys($item_data)) . ") VALUES (:" . implode(', :', array_keys($item_data)) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($item_data);
                }
                
                $pdo->commit();
                header('Location: sales.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Sale save error: ' . $e->getMessage());
                Session::setFlash('error', $e->getMessage());
            }
        } else {
            Session::setFlash('error', implode('<br>', $errors));
        }
    }
}

// Get filters
$search = Security::sanitizeInput($_GET['search'] ?? '');
$customer_filter = (int)($_GET['customer'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';
$date_from = Security::sanitizeInput($_GET['date_from'] ?? '');
$date_to = Security::sanitizeInput($_GET['date_to'] ?? '');

// Build query
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(so.so_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($customer_filter) {
    $where_conditions[] = "so.customer_id = ?";
    $params[] = $customer_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "so.status = ?";
    $params[] = $status_filter;
}

if ($payment_filter !== '') {
    $where_conditions[] = "so.payment_status = ?";
    $params[] = $payment_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "so.so_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "so.so_date <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(DISTINCT so.id) as total FROM sales_orders so LEFT JOIN customers c ON so.customer_id = c.id WHERE $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;

// Get sales orders
$sql = "
    SELECT so.*, 
           c.name as customer_name,
           c.phone as customer_phone,
           w.name as warehouse_name,
           u.full_name as creator_name
    FROM sales_orders so
    LEFT JOIN customers c ON so.customer_id = c.id
    LEFT JOIN warehouses w ON so.warehouse_id = w.id
    LEFT JOIN users u ON so.created_by = u.id
    WHERE $where_clause
    ORDER BY so.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get customers for filter and form
$stmt = $pdo->query("SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get warehouses for form
$stmt = $pdo->query("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name");
$warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products for form
$stmt = $pdo->query("SELECT id, name, sku, selling_price FROM products WHERE is_active = 1 ORDER BY name");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'إدارة المبيعات';
$current_page = 'sales';
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
        .sale-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .sale-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
        
        .amount-display {
            font-weight: 700;
            font-size: 1.1rem;
            color: #28a745;
        }
        
        .sale-number {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #495057;
        }
        
        .product-row {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        
        .product-row:last-child {
            border-bottom: none;
        }
        
        .remove-product {
            color: #dc3545;
            cursor: pointer;
        }
        
        .add-product {
            color: #28a745;
            cursor: pointer;
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
                            <i class="fas fa-shopping-cart me-2"></i>
                            إدارة المبيعات
                        </h2>
                        
                        <?php if (Session::hasPermission('sales_add')): ?>
                        <div>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#saleModal">
                                <i class="fas fa-plus me-2"></i>
                                إضافة عملية بيع جديدة
                            </button>
                            <button type="button" class="btn btn-outline-success">
                                <i class="fas fa-file-export me-2"></i>
                                تصدير المبيعات
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
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="رقم الفاتورة، اسم العميل، أو رقم الهاتف">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">العميل</label>
                                <select class="form-select" name="customer">
                                    <option value="">جميع العملاء</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>" <?php echo $customer_filter == $customer['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($customer['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">حالة الطلب</label>
                                <select class="form-select" name="status">
                                    <option value="">جميع الحالات</option>
                                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>مسودة</option>
                                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>مؤكد</option>
                                    <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>قيد التنفيذ</option>
                                    <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>تم الشحن</option>
                                    <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>تم التسليم</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>ملغي</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">حالة الدفع</label>
                                <select class="form-select" name="payment">
                                    <option value="">جميع حالات الدفع</option>
                                    <option value="pending" <?php echo $payment_filter === 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                                    <option value="partial" <?php echo $payment_filter === 'partial' ? 'selected' : ''; ?>>دفع جزئي</option>
                                    <option value="paid" <?php echo $payment_filter === 'paid' ? 'selected' : ''; ?>>مدفوع</option>
                                </select>
                            </div>
                            
                            <div class="col-md-1-5">
                                <label class="form-label">من تاريخ</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            
                            <div class="col-md-1-5">
                                <label class="form-label">إلى تاريخ</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            
                            <div class="col-md-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>
                                        بحث
                                    </button>
                                    <a href="sales.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo me-1"></i>
                                        إعادة تعيين
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Sales Table -->
                    <div class="table-card">
                        <div class="table-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                قائمة المبيعات (<?php echo number_format($total_records); ?> عملية بيع)
                            </h5>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>رقم الفاتورة</th>
                                        <th>العميل</th>
                                        <th>المخزن</th>
                                        <th>تاريخ البيع</th>
                                        <th>المبلغ الإجمالي</th>
                                        <th>حالة الطلب</th>
                                        <th>حالة الدفع</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sales)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-5 text-muted">
                                                <i class="fas fa-shopping-cart fa-3x mb-3 d-block"></i>
                                                لا توجد عمليات بيع لعرضها
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($sales as $sale): ?>
                                            <tr>
                                                <td>
                                                    <span class="sale-number"><?php echo htmlspecialchars($sale['so_number']); ?></span>
                                                    <br><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($sale['created_at'])); ?></small>
                                                </td>
                                                
                                                <td>
                                                    <strong><?php echo htmlspecialchars($sale['customer_name']); ?></strong>
                                                    <?php if ($sale['customer_phone']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($sale['customer_phone']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td><?php echo htmlspecialchars($sale['warehouse_name']); ?></td>
                                                
                                                <td>
                                                    <?php echo date('d/m/Y', strtotime($sale['so_date'])); ?>
                                                    <?php if ($sale['delivery_date']): ?>
                                                        <br><small class="text-muted">التسليم: <?php echo date('d/m/Y', strtotime($sale['delivery_date'])); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <span class="amount-display"><?php echo Utils::formatCurrency($sale['total_amount']); ?></span>
                                                    <?php if ($sale['paid_amount'] > 0): ?>
                                                        <br><small class="text-success">مدفوع: <?php echo Utils::formatCurrency($sale['paid_amount']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <?php
                                                    $status_colors = [
                                                        'draft' => 'secondary',
                                                        'confirmed' => 'primary',
                                                        'processing' => 'warning',
                                                        'shipped' => 'info',
                                                        'delivered' => 'success',
                                                        'cancelled' => 'danger'
                                                    ];
                                                    $status_labels = [
                                                        'draft' => 'مسودة',
                                                        'confirmed' => 'مؤكد',
                                                        'processing' => 'قيد التنفيذ',
                                                        'shipped' => 'تم الشحن',
                                                        'delivered' => 'تم التسليم',
                                                        'cancelled' => 'ملغي'
                                                    ];
                                                    ?>
                                                    <span class="status-badge bg-<?php echo $status_colors[$sale['status']]; ?>">
                                                        <?php echo $status_labels[$sale['status']]; ?>
                                                    </span>
                                                </td>
                                                
                                                <td>
                                                    <?php
                                                    $payment_colors = [
                                                        'pending' => 'warning',
                                                        'partial' => 'info',
                                                        'paid' => 'success'
                                                    ];
                                                    $payment_labels = [
                                                        'pending' => 'قيد الانتظار',
                                                        'partial' => 'دفع جزئي',
                                                        'paid' => 'مدفوع'
                                                    ];
                                                    ?>
                                                    <span class="status-badge bg-<?php echo $payment_colors[$sale['payment_status']]; ?>">
                                                        <?php echo $payment_labels[$sale['payment_status']]; ?>
                                                    </span>
                                                </td>
                                                
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary btn-action" onclick="viewSale(<?php echo $sale['id']; ?>)" title="عرض">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <?php if (Session::hasPermission('sales_edit') && $sale['status'] === 'draft'): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-warning btn-action" onclick="editSale(<?php echo $sale['id']; ?>)" title="تعديل">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (Session::hasPermission('sales_edit')): ?>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-sm btn-outline-info btn-action dropdown-toggle" data-bs-toggle="dropdown" title="تغيير الحالة">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <ul class="dropdown-menu">
                                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $sale['id']; ?>, 'confirmed')">تأكيد</a></li>
                                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $sale['id']; ?>, 'processing')">قيد التنفيذ</a></li>
                                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $sale['id']; ?>, 'shipped')">تم الشحن</a></li>
                                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $sale['id']; ?>, 'delivered')">تم التسليم</a></li>
                                                                    <li><hr class="dropdown-divider"></li>
                                                                    <li><a class="dropdown-item text-danger" href="#" onclick="updateStatus(<?php echo $sale['id']; ?>, 'cancelled')">إلغاء</a></li>
                                                                </ul>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (Session::hasPermission('sales_delete') && $sale['status'] === 'draft'): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger btn-action" onclick="deleteSale(<?php echo $sale['id']; ?>)" title="حذف">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-action" onclick="printSale(<?php echo $sale['id']; ?>)" title="طباعة">
                                                            <i class="fas fa-print"></i>
                                                        </button>
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
                                <?php echo Utils::generatePagination($page, $total_pages, 'sales.php', $_GET); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sale Modal -->
    <div class="modal fade" id="saleModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة عملية بيع جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST" id="saleForm">
                    <?php echo Security::getCSRFInput(); ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id" id="sale_id">
                    
                    <div class="modal-body">
                        <div class="row">
                            <!-- Sale Header -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="customer_id" class="form-label">العميل <span class="text-danger">*</span></label>
                                    <select class="form-select" id="customer_id" name="customer_id" required>
                                        <option value="">اختر العميل</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="warehouse_id" class="form-label">المخزن <span class="text-danger">*</span></label>
                                    <select class="form-select" id="warehouse_id" name="warehouse_id" required>
                                        <option value="">اختر المخزن</option>
                                        <?php foreach ($warehouses as $warehouse): ?>
                                            <option value="<?php echo $warehouse['id']; ?>"><?php echo htmlspecialchars($warehouse['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="so_date" class="form-label">تاريخ البيع <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="so_date" name="so_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="delivery_date" class="form-label">تاريخ التسليم</label>
                                    <input type="date" class="form-control" id="delivery_date" name="delivery_date">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="shipping_address" class="form-label">عنوان التسليم</label>
                                    <textarea class="form-control" id="shipping_address" name="shipping_address" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Products Section -->
                        <div class="row">
                            <div class="col-12">
                                <h6 class="mb-3">
                                    <i class="fas fa-boxes me-2"></i>
                                    المنتجات
                                    <button type="button" class="btn btn-sm btn-success ms-2" onclick="addProductRow()">
                                        <i class="fas fa-plus me-1"></i>
                                        إضافة منتج
                                    </button>
                                </h6>
                                
                                <div id="products-container">
                                    <!-- Product rows will be added here -->
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-8"></div>
                                    <div class="col-md-4">
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>المجموع الفرعي:</strong></td>
                                                <td class="text-end"><span id="subtotal-display">0.00 ر.س</span></td>
                                            </tr>
                                            <tr>
                                                <td><strong>المجموع الإجمالي:</strong></td>
                                                <td class="text-end"><span id="total-display">0.00 ر.س</span></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="notes" class="form-label">ملاحظات</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="terms_conditions" class="form-label">الشروط والأحكام</label>
                                    <textarea class="form-control" id="terms_conditions" name="terms_conditions" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-success">حفظ عملية البيع</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Sale Modal -->
    <div class="modal fade" id="viewSaleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تفاصيل عملية البيع</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="saleDetails">
                    <!-- Sale details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-primary" onclick="printSaleModal()">طباعة</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    
    <script>
        let productRowIndex = 0;
        const products = <?php echo json_encode($products); ?>;
        
        // Add product row
        function addProductRow() {
            const container = document.getElementById('products-container');
            const productRow = document.createElement('div');
            productRow.className = 'product-row';
            productRow.innerHTML = `
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">المنتج</label>
                        <select class="form-select" name="items[${productRowIndex}][product_id]" onchange="updateProductPrice(this, ${productRowIndex})" required>
                            <option value="">اختر المنتج</option>
                            ${products.map(p => `<option value="${p.id}" data-price="${p.selling_price}">${p.name} (${p.sku})</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">الكمية</label>
                        <input type="number" class="form-control" name="items[${productRowIndex}][quantity]" min="1" value="1" onchange="calculateRowTotal(${productRowIndex})" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">سعر الوحدة</label>
                        <input type="number" class="form-control" name="items[${productRowIndex}][unit_price]" step="0.01" min="0" onchange="calculateRowTotal(${productRowIndex})" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">خصم</label>
                        <input type="number" class="form-control" name="items[${productRowIndex}][discount_amount]" step="0.01" min="0" value="0" onchange="calculateRowTotal(${productRowIndex})">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">المجموع</label>
                        <input type="text" class="form-control" id="row-total-${productRowIndex}" readonly>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeProductRow(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <input type="text" class="form-control form-control-sm" name="items[${productRowIndex}][notes]" placeholder="ملاحظات على المنتج (اختياري)">
                    </div>
                </div>
            `;
            
            container.appendChild(productRow);
            productRowIndex++;
        }
        
        // Remove product row
        function removeProductRow(button) {
            const row = button.closest('.product-row');
            row.remove();
            calculateGrandTotal();
        }
        
        // Update product price when product is selected
        function updateProductPrice(select, index) {
            const selectedOption = select.options[select.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            const priceInput = select.closest('.product-row').querySelector('input[name*="[unit_price]"]');
            
            if (price && priceInput) {
                priceInput.value = parseFloat(price).toFixed(2);
                calculateRowTotal(index);
            }
        }
        
        // Calculate row total
        function calculateRowTotal(index) {
            const row = document.querySelector(`input[name="items[${index}][quantity]"]`).closest('.product-row');
            const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
            const unitPrice = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
            const discount = parseFloat(row.querySelector('input[name*="[discount_amount]"]').value) || 0;
            
            const total = (quantity * unitPrice) - discount;
            const totalField = row.querySelector('input[id*="row-total"]');
            
            if (totalField) {
                totalField.value = total.toFixed(2) + ' ر.س';
            }
            
            calculateGrandTotal();
        }
        
        // Calculate grand total
        function calculateGrandTotal() {
            let subtotal = 0;
            
            document.querySelectorAll('.product-row').forEach(row => {
                const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
                const unitPrice = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
                const discount = parseFloat(row.querySelector('input[name*="[discount_amount]"]').value) || 0;
                
                const rowTotal = (quantity * unitPrice) - discount;
                if (rowTotal > 0) {
                    subtotal += rowTotal;
                }
            });
            
            document.getElementById('subtotal-display').textContent = subtotal.toFixed(2) + ' ر.س';
            document.getElementById('total-display').textContent = subtotal.toFixed(2) + ' ر.س';
        }
        
        // View sale details
        function viewSale(id) {
            fetch(`sales.php?action=get_sale&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const sale = data.sale;
                        
                        let html = `
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>معلومات عملية البيع</h6>
                                    <table class="table table-borderless">
                                        <tr><td><strong>رقم الفاتورة:</strong></td><td>${sale.so_number}</td></tr>
                                        <tr><td><strong>العميل:</strong></td><td>${sale.customer_name}</td></tr>
                                        <tr><td><strong>المخزن:</strong></td><td>${sale.warehouse_name}</td></tr>
                                        <tr><td><strong>تاريخ البيع:</strong></td><td>${sale.so_date}</td></tr>
                                        <tr><td><strong>تاريخ التسليم:</strong></td><td>${sale.delivery_date || 'غير محدد'}</td></tr>
                                        <tr><td><strong>حالة الطلب:</strong></td><td>${getStatusLabel(sale.status)}</td></tr>
                                        <tr><td><strong>حالة الدفع:</strong></td><td>${getPaymentStatusLabel(sale.payment_status)}</td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6>المبالغ</h6>
                                    <table class="table table-borderless">
                                        <tr><td><strong>المجموع الفرعي:</strong></td><td>${parseFloat(sale.subtotal).toFixed(2)} ر.س</td></tr>
                                        <tr><td><strong>المجموع الإجمالي:</strong></td><td>${parseFloat(sale.total_amount).toFixed(2)} ر.س</td></tr>
                                        <tr><td><strong>المبلغ المدفوع:</strong></td><td>${parseFloat(sale.paid_amount).toFixed(2)} ر.س</td></tr>
                                        <tr><td><strong>المبلغ المتبقي:</strong></td><td>${(parseFloat(sale.total_amount) - parseFloat(sale.paid_amount)).toFixed(2)} ر.س</td></tr>
                                    </table>
                                </div>
                            </div>
                        `;
                        
                        if (sale.shipping_address) {
                            html += `<div class=\"mt-3\"><h6>عنوان التسليم</h6><p>${sale.shipping_address}</p></div>`;
                        }
                        
                        if (sale.notes) {
                            html += `<div class=\"mt-3\"><h6>الملاحظات</h6><p>${sale.notes}</p></div>`;
                        }
                        
                        if (sale.items && sale.items.length > 0) {
                            html += `
                                <div class="mt-3">
                                    <h6>المنتجات</h6>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>المنتج</th>
                                                <th>الكمية</th>
                                                <th>سعر الوحدة</th>
                                                <th>الخصم</th>
                                                <th>المجموع</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                            `;
                            
                            sale.items.forEach(item => {
                                html += `
                                    <tr>
                                        <td>${item.product_name} <small class="text-muted">(${item.product_sku})</small></td>
                                        <td>${item.quantity}</td>
                                        <td>${parseFloat(item.unit_price).toFixed(2)} ر.س</td>
                                        <td>${parseFloat(item.discount_amount).toFixed(2)} ر.س</td>
                                        <td>${parseFloat(item.total_price).toFixed(2)} ر.س</td>
                                    </tr>
                                `;
                                if (item.notes) {
                                    html += `<tr><td colspan=\"5\"><small class=\"text-muted\">ملاحظة: ${item.notes}</small></td></tr>`;
                                }
                            });
                            
                            html += '</tbody></table></div>';
                        }
                        
                        document.getElementById('saleDetails').innerHTML = html;
                        new bootstrap.Modal(document.getElementById('viewSaleModal')).show();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء تحميل بيانات عملية البيع');
                });
        }
        
        // Edit sale
        function editSale(id) {
            fetch(`sales.php?action=get_sale&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const sale = data.sale;
                        
                        // Fill form fields
                        document.getElementById('sale_id').value = sale.id;
                        document.querySelector('input[name="action"]').value = 'edit';
                        document.querySelector('.modal-title').textContent = 'تعديل عملية البيع';
                        
                        // Fill basic fields
                        document.getElementById('customer_id').value = sale.customer_id;
                        document.getElementById('warehouse_id').value = sale.warehouse_id;
                        document.getElementById('so_date').value = sale.so_date;
                        document.getElementById('delivery_date').value = sale.delivery_date || '';
                        document.getElementById('shipping_address').value = sale.shipping_address || '';
                        document.getElementById('notes').value = sale.notes || '';
                        document.getElementById('terms_conditions').value = sale.terms_conditions || '';
                        
                        // Clear existing product rows
                        document.getElementById('products-container').innerHTML = '';
                        productRowIndex = 0;
                        
                        // Add product rows
                        if (sale.items && sale.items.length > 0) {
                            sale.items.forEach(item => {
                                addProductRow();
                                const currentRow = document.querySelectorAll('.product-row')[productRowIndex - 1];
                                
                                currentRow.querySelector('select[name*="[product_id]"]').value = item.product_id;
                                currentRow.querySelector('input[name*="[quantity]"]').value = item.quantity;
                                currentRow.querySelector('input[name*="[unit_price]"]').value = parseFloat(item.unit_price).toFixed(2);
                                currentRow.querySelector('input[name*="[discount_amount]"]').value = parseFloat(item.discount_amount).toFixed(2);
                                currentRow.querySelector('input[name*="[notes]"]').value = item.notes || '';
                                
                                calculateRowTotal(productRowIndex - 1);
                            });
                        } else {
                            addProductRow();
                        }
                        
                        // Show modal
                        new bootstrap.Modal(document.getElementById('saleModal')).show();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء تحميل بيانات عملية البيع');
                });
        }
        
        // Update sale status
        function updateStatus(id, status) {
            if (confirm('هل أنت متأكد من تغيير حالة عملية البيع؟')) {
                fetch('sales.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=update_status&id=${id}&status=${status}`
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
                    alert('حدث خطأ أثناء تحديث حالة عملية البيع');
                });
            }
        }
        
        // Delete sale
        function deleteSale(id) {
            if (confirm('هل أنت متأكد من حذف عملية البيع؟ لا يمكن التراجع عن هذا الإجراء.')) {
                fetch('sales.php', {
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
                    alert('حدث خطأ أثناء حذف عملية البيع');
                });
            }
        }
        
        // Print sale
        function printSale(id) {
            window.open(`print_sale.php?id=${id}`, '_blank');
        }
        
        function printSaleModal() {
            window.print();
        }
        
        // Helper functions
        function getStatusLabel(status) {
            const labels = {
                'draft': 'مسودة',
                'confirmed': 'مؤكد',
                'processing': 'قيد التنفيذ',
                'shipped': 'تم الشحن',
                'delivered': 'تم التسليم',
                'cancelled': 'ملغي'
            };
            return labels[status] || status;
        }
        
        function getPaymentStatusLabel(status) {
            const labels = {
                'pending': 'قيد الانتظار',
                'partial': 'دفع جزئي',
                'paid': 'مدفوع'
            };
            return labels[status] || status;
        }
        
        // Reset form when modal is hidden
        document.getElementById('saleModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('saleForm').reset();
            document.getElementById('sale_id').value = '';
            document.querySelector('input[name="action"]').value = 'add';
            document.querySelector('.modal-title').textContent = 'إضافة عملية بيع جديدة';
            document.getElementById('products-container').innerHTML = '';
            productRowIndex = 0;
            addProductRow();
            document.getElementById('so_date').value = '<?php echo date('Y-m-d'); ?>';
        });
        
        // Initialize with one product row when page loads
        document.addEventListener('DOMContentLoaded', function() {
            addProductRow();
        });
        
        // Form validation before submit
        document.getElementById('saleForm').addEventListener('submit', function(e) {
            const productRows = document.querySelectorAll('.product-row');
            if (productRows.length === 0) {
                e.preventDefault();
                alert('يرجى إضافة منتج واحد على الأقل');
                return;
            }
            
            let hasValidProduct = false;
            productRows.forEach(row => {
                const productSelect = row.querySelector('select[name*="[product_id]"]');
                const quantityInput = row.querySelector('input[name*="[quantity]"]');
                const priceInput = row.querySelector('input[name*="[unit_price]"]');
                
                if (productSelect.value && quantityInput.value && priceInput.value) {
                    hasValidProduct = true;
                }
            });
            
            if (!hasValidProduct) {
                e.preventDefault();
                alert('يرجى إضافة منتج صحيح واحد على الأقل');
            }
        });
    </script>
</body>
</html>
