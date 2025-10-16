<?php
/**
 * Purchase Orders Management
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
        case 'get_purchase':
            $id = (int)($_GET['id'] ?? 0);
            if ($id) {
                $stmt = $pdo->prepare("
                    SELECT po.*, s.name as supplier_name, s.phone as supplier_phone, 
                           w.name as warehouse_name, u.full_name as creator_name
                    FROM purchase_orders po
                    LEFT JOIN suppliers s ON po.supplier_id = s.id
                    LEFT JOIN warehouses w ON po.warehouse_id = w.id
                    LEFT JOIN users u ON po.created_by = u.id
                    WHERE po.id = ?
                ");
                $stmt->execute([$id]);
                $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($purchase) {
                    // Get purchase items
                    $stmt = $pdo->prepare("
                        SELECT poi.*, p.name as product_name, p.sku as product_sku
                        FROM purchase_order_items poi
                        JOIN products p ON poi.product_id = p.id
                        WHERE poi.purchase_order_id = ?
                    ");
                    $stmt->execute([$id]);
                    $purchase['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    Utils::sendJsonResponse(['success' => true, 'purchase' => $purchase]);
                } else {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'أمر الشراء غير موجود']);
                }
            }
            break;
            
        case 'get_supplier_info':
            $supplier_id = (int)($_GET['supplier_id'] ?? 0);
            if ($supplier_id) {
                $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? AND is_active = 1");
                $stmt->execute([$supplier_id]);
                $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($supplier) {
                    Utils::sendJsonResponse(['success' => true, 'supplier' => $supplier]);
                } else {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'المورد غير موجود']);
                }
            }
            break;
            
        case 'get_product_cost':
            $product_id = (int)($_GET['product_id'] ?? 0);
            if ($product_id) {
                $stmt = $pdo->prepare("SELECT cost_price, name FROM products WHERE id = ? AND is_active = 1");
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
            Session::requirePermission('purchases_edit');
            $id = (int)($_POST['id'] ?? 0);
            $status = Security::sanitizeInput($_POST['status'] ?? '');
            
            $valid_statuses = ['draft', 'sent', 'confirmed', 'partially_received', 'received', 'cancelled'];
            
            if ($id && in_array($status, $valid_statuses)) {
                $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
                if ($stmt->execute([$status, $id])) {
                    Utils::logActivity($pdo, Session::getUserId(), 'update_purchase_status', 'purchase_orders', $id, null, ['status' => $status]);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'تم تحديث حالة أمر الشراء']);
                } else {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'فشل في تحديث حالة أمر الشراء']);
                }
            } else {
                Utils::sendJsonResponse(['success' => false, 'message' => 'بيانات غير صحيحة']);
            }
            break;
            
        case 'delete':
            Session::requirePermission('purchases_delete');
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                // Check if purchase can be deleted (only draft orders)
                $stmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE id = ?");
                $stmt->execute([$id]);
                $purchase = $stmt->fetch();
                
                if (!$purchase) {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'أمر الشراء غير موجود']);
                } elseif ($purchase['status'] !== 'draft') {
                    Utils::sendJsonResponse(['success' => false, 'message' => 'لا يمكن حذف أمر شراء مؤكد']);
                } else {
                    $pdo->beginTransaction();
                    try {
                        // Delete purchase items first
                        $stmt = $pdo->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = ?");
                        $stmt->execute([$id]);
                        
                        // Delete purchase order
                        $stmt = $pdo->prepare("DELETE FROM purchase_orders WHERE id = ?");
                        $stmt->execute([$id]);
                        
                        $pdo->commit();
                        Utils::logActivity($pdo, Session::getUserId(), 'delete_purchase', 'purchase_orders', $id);
                        Utils::sendJsonResponse(['success' => true, 'message' => 'تم حذف أمر الشراء بنجاح']);
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        Utils::sendJsonResponse(['success' => false, 'message' => 'فشل في حذف أمر الشراء']);
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
            Session::requirePermission('purchases_add');
        } else {
            Session::requirePermission('purchases_edit');
        }
        
        // Validate CSRF token
        if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
            Session::setFlash('error', 'انتهت صلاحية الجلسة. يرجى المحاولة مرة أخرى.');
            header('Location: purchase_orders.php');
            exit;
        }
        
        // Sanitize and validate input
        $data = [
            'supplier_id' => (int)($_POST['supplier_id'] ?? 0),
            'warehouse_id' => (int)($_POST['warehouse_id'] ?? 0),
            'po_date' => Security::sanitizeInput($_POST['po_date'] ?? ''),
            'expected_delivery_date' => Security::sanitizeInput($_POST['expected_delivery_date'] ?? '') ?: null,
            'notes' => Security::sanitizeInput($_POST['notes'] ?? '') ?: null,
            'terms_conditions' => Security::sanitizeInput($_POST['terms_conditions'] ?? '') ?: null
        ];
        
        $items = $_POST['items'] ?? [];
        
        $errors = [];
        
        // Validation
        if (empty($data['supplier_id'])) {
            $errors[] = 'يرجى اختيار المورد';
        }
        if (empty($data['warehouse_id'])) {
            $errors[] = 'يرجى اختيار المخزن';
        }
        if (empty($data['po_date'])) {
            $errors[] = 'تاريخ أمر الشراء مطلوب';
        }
        if (empty($items)) {
            $errors[] = 'يرجى إضافة منتج واحد على الأقل';
        }
        
        // Validate items
        $subtotal = 0;
        foreach ($items as $index => $item) {
            if (empty($item['product_id']) || empty($item['quantity']) || empty($item['unit_cost'])) {
                $errors[] = "بيانات المنتج رقم " . ($index + 1) . " غير مكتملة";
            } else {
                $quantity = (int)$item['quantity'];
                $unit_cost = (float)$item['unit_cost'];
                $discount_amount = (float)($item['discount_amount'] ?? 0);
                
                if ($quantity <= 0) {
                    $errors[] = "كمية المنتج رقم " . ($index + 1) . " يجب أن تكون أكبر من صفر";
                }
                if ($unit_cost <= 0) {
                    $errors[] = "سعر المنتج رقم " . ($index + 1) . " يجب أن يكون أكبر من صفر";
                }
                
                $line_total = ($quantity * $unit_cost) - $discount_amount;
                $subtotal += $line_total;
            }
        }
        
        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                $id = (int)($_POST['id'] ?? 0);
                
                if ($action === 'add') {
                    // Generate PO number
                    $po_number = 'PO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Check if PO number exists
                    $stmt = $pdo->prepare("SELECT id FROM purchase_orders WHERE po_number = ?");
                    $stmt->execute([$po_number]);
                    while ($stmt->fetch()) {
                        $po_number = 'PO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $stmt->execute([$po_number]);
                    }
                    
                    $data['po_number'] = $po_number;
                    $data['subtotal'] = $subtotal;
                    $data['total_amount'] = $subtotal; // Can be modified later with taxes
                    $data['status'] = 'draft';
                    $data['created_by'] = Session::getUserId();
                    
                    $sql = "INSERT INTO purchase_orders (" . implode(', ', array_keys($data)) . ") VALUES (:" . implode(', :', array_keys($data)) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($data);
                    $purchase_id = $pdo->lastInsertId();
                    
                    Utils::logActivity($pdo, Session::getUserId(), 'add_purchase', 'purchase_orders', $purchase_id, null, $data);
                    Session::setFlash('success', 'تم إضافة أمر الشراء بنجاح - رقم الأمر: ' . $po_number);
                } else {
                    // Update existing purchase (only if still draft)
                    $stmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE id = ?");
                    $stmt->execute([$id]);
                    $current_purchase = $stmt->fetch();
                    
                    if (!$current_purchase) {
                        throw new Exception('أمر الشراء غير موجود');
                    }
                    if ($current_purchase['status'] !== 'draft') {
                        throw new Exception('لا يمكن تعديل أمر شراء مؤكد');
                    }
                    
                    $data['subtotal'] = $subtotal;
                    $data['total_amount'] = $subtotal;
                    
                    $sql = "UPDATE purchase_orders SET " . implode(' = ?, ', array_keys($data)) . " = ? WHERE id = ?";
                    $params = array_values($data);
                    $params[] = $id;
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $purchase_id = $id;
                    
                    // Delete existing items
                    $stmt = $pdo->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = ?");
                    $stmt->execute([$purchase_id]);
                    
                    Utils::logActivity($pdo, Session::getUserId(), 'edit_purchase', 'purchase_orders', $purchase_id, null, $data);
                    Session::setFlash('success', 'تم تحديث أمر الشراء بنجاح');
                }
                
                // Insert purchase items
                foreach ($items as $item) {
                    $quantity = (int)$item['quantity'];
                    $unit_cost = (float)$item['unit_cost'];
                    $discount_amount = (float)($item['discount_amount'] ?? 0);
                    $total_cost = ($quantity * $unit_cost) - $discount_amount;
                    
                    $item_data = [
                        'purchase_order_id' => $purchase_id,
                        'product_id' => (int)$item['product_id'],
                        'quantity' => $quantity,
                        'unit_cost' => $unit_cost,
                        'discount_amount' => $discount_amount,
                        'total_cost' => $total_cost,
                        'notes' => Security::sanitizeInput($item['notes'] ?? '') ?: null
                    ];
                    
                    $sql = "INSERT INTO purchase_order_items (" . implode(', ', array_keys($item_data)) . ") VALUES (:" . implode(', :', array_keys($item_data)) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($item_data);
                }
                
                $pdo->commit();
                header('Location: purchase_orders.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Purchase save error: ' . $e->getMessage());
                Session::setFlash('error', $e->getMessage());
            }
        } else {
            Session::setFlash('error', implode('<br>', $errors));
        }
    }
}

// Get filters
$search = Security::sanitizeInput($_GET['search'] ?? '');
$supplier_filter = (int)($_GET['supplier'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$date_from = Security::sanitizeInput($_GET['date_from'] ?? '');
$date_to = Security::sanitizeInput($_GET['date_to'] ?? '');

// Build query
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(po.po_number LIKE ? OR s.name LIKE ? OR s.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($supplier_filter) {
    $where_conditions[] = "po.supplier_id = ?";
    $params[] = $supplier_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "po.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "po.po_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "po.po_date <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(DISTINCT po.id) as total FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id = s.id WHERE $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$total_pages = ceil($total_records / $per_page);
$offset = ($page - 1) * $per_page;

// Get purchase orders
$sql = "
    SELECT po.*, 
           s.name as supplier_name,
           s.phone as supplier_phone,
           w.name as warehouse_name,
           u.full_name as creator_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN warehouses w ON po.warehouse_id = w.id
    LEFT JOIN users u ON po.created_by = u.id
    WHERE $where_clause
    ORDER BY po.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get suppliers for filter and form
$stmt = $pdo->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get warehouses for form
$stmt = $pdo->query("SELECT id, name FROM warehouses WHERE is_active = 1 ORDER BY name");
$warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products for form
$stmt = $pdo->query("SELECT id, name, sku, cost_price FROM products WHERE is_active = 1 ORDER BY name");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'إدارة أوامر الشراء';
$current_page = 'purchase_orders';
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
        .purchase-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .purchase-card:hover {
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
            background: linear-gradient(135deg, #6f42c1 0%, #563d7c 100%);
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
            color: #6f42c1;
        }
        
        .purchase-number {
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
            color: #6f42c1;
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
                            <i class="fas fa-file-invoice-dollar me-2"></i>
                            إدارة أوامر الشراء
                        </h2>
                        
                        <?php if (Session::hasPermission('purchases_add')): ?>
                        <div>
                            <button type="button" class="btn btn-purple" data-bs-toggle="modal" data-bs-target="#purchaseModal" style="background: #6f42c1; border-color: #6f42c1;">
                                <i class="fas fa-plus me-2"></i>
                                إضافة أمر شراء جديد
                            </button>
                            <button type="button" class="btn btn-outline-purple" style="color: #6f42c1; border-color: #6f42c1;">
                                <i class="fas fa-file-export me-2"></i>
                                تصدير أوامر الشراء
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
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="رقم الأمر، اسم المورد، أو رقم الهاتف">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">المورد</label>
                                <select class="form-select" name="supplier">
                                    <option value="">جميع الموردين</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['id']; ?>" <?php echo $supplier_filter == $supplier['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($supplier['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">حالة الأمر</label>
                                <select class="form-select" name="status">
                                    <option value="">جميع الحالات</option>
                                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>مسودة</option>
                                    <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>مرسل</option>
                                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>مؤكد</option>
                                    <option value="partially_received" <?php echo $status_filter === 'partially_received' ? 'selected' : ''; ?>>مستلم جزئياً</option>
                                    <option value="received" <?php echo $status_filter === 'received' ? 'selected' : ''; ?>>مستلم</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>ملغي</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">من تاريخ</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">إلى تاريخ</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>
                                        بحث
                                    </button>
                                    <a href="purchase_orders.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo me-1"></i>
                                        إعادة تعيين
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Purchase Orders Table -->
                    <div class="table-card">
                        <div class="table-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                قائمة أوامر الشراء (<?php echo number_format($total_records); ?> أمر شراء)
                            </h5>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>رقم الأمر</th>
                                        <th>المورد</th>
                                        <th>المخزن</th>
                                        <th>تاريخ الأمر</th>
                                        <th>المبلغ الإجمالي</th>
                                        <th>حالة الأمر</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($purchases)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="fas fa-file-invoice-dollar fa-3x mb-3 d-block"></i>
                                                لا توجد أوامر شراء لعرضها
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($purchases as $purchase): ?>
                                            <tr>
                                                <td>
                                                    <span class="purchase-number"><?php echo htmlspecialchars($purchase['po_number']); ?></span>
                                                    <br><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($purchase['created_at'])); ?></small>
                                                </td>
                                                
                                                <td>
                                                    <strong><?php echo htmlspecialchars($purchase['supplier_name']); ?></strong>
                                                    <?php if ($purchase['supplier_phone']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($purchase['supplier_phone']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td><?php echo htmlspecialchars($purchase['warehouse_name']); ?></td>
                                                
                                                <td>
                                                    <?php echo date('d/m/Y', strtotime($purchase['po_date'])); ?>
                                                    <?php if ($purchase['expected_delivery_date']): ?>
                                                        <br><small class="text-muted">التسليم المتوقع: <?php echo date('d/m/Y', strtotime($purchase['expected_delivery_date'])); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <span class="amount-display"><?php echo Utils::formatCurrency($purchase['total_amount']); ?></span>
                                                    <?php if ($purchase['paid_amount'] > 0): ?>
                                                        <br><small class="text-success">مدفوع: <?php echo Utils::formatCurrency($purchase['paid_amount']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <?php
                                                    $status_colors = [
                                                        'draft' => 'secondary',
                                                        'sent' => 'info',
                                                        'confirmed' => 'primary',
                                                        'partially_received' => 'warning',
                                                        'received' => 'success',
                                                        'cancelled' => 'danger'
                                                    ];
                                                    $status_labels = [
                                                        'draft' => 'مسودة',
                                                        'sent' => 'مرسل',
                                                        'confirmed' => 'مؤكد',
                                                        'partially_received' => 'مستلم جزئياً',
                                                        'received' => 'مستلم',
                                                        'cancelled' => 'ملغي'
                                                    ];
                                                    ?>
                                                    <span class="status-badge bg-<?php echo $status_colors[$purchase['status']]; ?>">
                                                        <?php echo $status_labels[$purchase['status']]; ?>
                                                    </span>
                                                </td>
                                                
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary btn-action" onclick="viewPurchase(<?php echo $purchase['id']; ?>)" title="عرض">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <?php if (Session::hasPermission('purchases_edit') && $purchase['status'] === 'draft'): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-warning btn-action" onclick="editPurchase(<?php echo $purchase['id']; ?>)" title="تعديل">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (Session::hasPermission('purchases_edit')): ?>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-sm btn-outline-info btn-action dropdown-toggle" data-bs-toggle="dropdown" title="تغيير الحالة">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <ul class="dropdown-menu">
                                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $purchase['id']; ?>, 'sent')">إرسال</a></li>
                                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $purchase['id']; ?>, 'confirmed')">تأكيد</a></li>
                                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $purchase['id']; ?>, 'partially_received')">استلام جزئي</a></li>
                                                                    <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $purchase['id']; ?>, 'received')">تم الاستلام</a></li>
                                                                    <li><hr class="dropdown-divider"></li>
                                                                    <li><a class="dropdown-item text-danger" href="#" onclick="updateStatus(<?php echo $purchase['id']; ?>, 'cancelled')">إلغاء</a></li>
                                                                </ul>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (Session::hasPermission('purchases_delete') && $purchase['status'] === 'draft'): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger btn-action" onclick="deletePurchase(<?php echo $purchase['id']; ?>)" title="حذف">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-action" onclick="printPurchase(<?php echo $purchase['id']; ?>)" title="طباعة">
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
                                <?php echo Utils::generatePagination($page, $total_pages, 'purchase_orders.php', $_GET); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Purchase Modal -->
    <div class="modal fade" id="purchaseModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة أمر شراء جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST" id="purchaseForm">
                    <?php echo Security::getCSRFInput(); ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id" id="purchase_id">
                    
                    <div class="modal-body">
                        <div class="row">
                            <!-- Purchase Header -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="supplier_id" class="form-label">المورد <span class="text-danger">*</span></label>
                                    <select class="form-select" id="supplier_id" name="supplier_id" required>
                                        <option value="">اختر المورد</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
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
                                    <label for="po_date" class="form-label">تاريخ أمر الشراء <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="po_date" name="po_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="expected_delivery_date" class="form-label">تاريخ التسليم المتوقع</label>
                                    <input type="date" class="form-control" id="expected_delivery_date" name="expected_delivery_date">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="notes" class="form-label">ملاحظات</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Products Section -->
                        <div class="row">
                            <div class="col-12">
                                <h6 class="mb-3">
                                    <i class="fas fa-boxes me-2"></i>
                                    المنتجات
                                    <button type="button" class="btn btn-sm ms-2" style="background: #6f42c1; color: white;" onclick="addProductRow()">
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
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="terms_conditions" class="form-label">الشروط والأحكام</label>
                                    <textarea class="form-control" id="terms_conditions" name="terms_conditions" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn" style="background: #6f42c1; color: white;">حفظ أمر الشراء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Purchase Modal -->
    <div class="modal fade" id="viewPurchaseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تفاصيل أمر الشراء</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="purchaseDetails">
                    <!-- Purchase details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-primary" onclick="printPurchaseModal()">طباعة</button>
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
            productRow.innerHTML = '
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">المنتج</label>
                        <select class="form-select" name="items[${productRowIndex}][product_id]" onchange="updateProductCost(this, ${productRowIndex})" required>
                            <option value="">اختر المنتج</option>
                            ${products.map(p => '<option value="${p.id}" data-cost="${p.cost_price}">${p.name} (${p.sku})</option>').join('')}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">الكمية</label>
                        <input type="number" class="form-control" name="items[${productRowIndex}][quantity]" min="1" value="1" onchange="calculateRowTotal(${productRowIndex})" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">سعر الوحدة</label>
                        <input type="number" class="form-control" name="items[${productRowIndex}][unit_cost]" step="0.01" min="0" onchange="calculateRowTotal(${productRowIndex})" required>
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
            ';
            
            container.appendChild(productRow);
            productRowIndex++;
        }
        
        // Remove product row
        function removeProductRow(button) {
            const row = button.closest('.product-row');
            row.remove();
            calculateGrandTotal();
        }
        
        // Update product cost when product is selected
        function updateProductCost(select, index) {
            const selectedOption = select.options[select.selectedIndex];
            const cost = selectedOption.getAttribute('data-cost');
            const costInput = select.closest('.product-row').querySelector('input[name*="[unit_cost]"]');
            
            if (cost && costInput) {
                costInput.value = parseFloat(cost).toFixed(2);
                calculateRowTotal(index);
            }
        }
        
        // Calculate row total
        function calculateRowTotal(index) {
            const row = document.querySelector('input[name="items[${index}][quantity]"]').closest('.product-row');
            const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
            const unitCost = parseFloat(row.querySelector('input[name*="[unit_cost]"]').value) || 0;
            const discount = parseFloat(row.querySelector('input[name*="[discount_amount]"]').value) || 0;
            
            const total = (quantity * unitCost) - discount;
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
                const unitCost = parseFloat(row.querySelector('input[name*="[unit_cost]"]').value) || 0;
                const discount = parseFloat(row.querySelector('input[name*="[discount_amount]"]').value) || 0;
                
                const rowTotal = (quantity * unitCost) - discount;
                if (rowTotal > 0) {
                    subtotal += rowTotal;
                }
            });
            
            document.getElementById('subtotal-display').textContent = subtotal.toFixed(2) + ' ر.س';
            document.getElementById('total-display').textContent = subtotal.toFixed(2) + ' ر.س';
        }
        
        // View purchase details
        function viewPurchase(id) {
            fetch('purchase_orders.php?action=get_purchase&id=${id}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const purchase = data.purchase;
                        
                        let html = '
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>معلومات أمر الشراء</h6>
                                    <table class="table table-borderless">
                                        <tr><td><strong>رقم الأمر:</strong></td><td>${purchase.po_number}</td></tr>
                                        <tr><td><strong>المورد:</strong></td><td>${purchase.supplier_name}</td></tr>
                                        <tr><td><strong>المخزن:</strong></td><td>${purchase.warehouse_name}</td></tr>
                                        <tr><td><strong>تاريخ الأمر:</strong></td><td>${purchase.po_date}</td></tr>
                                        <tr><td><strong>التسليم المتوقع:</strong></td><td>${purchase.expected_delivery_date || 'غير محدد'}</td></tr>
                                        <tr><td><strong>حالة الأمر:</strong></td><td>${getStatusLabel(purchase.status)}</td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6>المبالغ</h6>
                                    <table class="table table-borderless">
                                        <tr><td><strong>المجموع الفرعي:</strong></td><td>${parseFloat(purchase.subtotal).toFixed(2)} ر.س</td></tr>
                                        <tr><td><strong>المجموع الإجمالي:</strong></td><td>${parseFloat(purchase.total_amount).toFixed(2)} ر.س</td></tr>
                                        <tr><td><strong>المبلغ المدفوع:</strong></td><td>${parseFloat(purchase.paid_amount).toFixed(2)} ر.س</td></tr>
                                        <tr><td><strong>المبلغ المتبقي:</strong></td><td>${(parseFloat(purchase.total_amount) - parseFloat(purchase.paid_amount)).toFixed(2)} ر.س</td></tr>
                                    </table>
                                </div>
                            </div>
                        ';
                        
                        if (purchase.notes) {
                            html += '<div class="mt-3"><h6>الملاحظات</h6><p>${purchase.notes}</p></div>';
                        }
                        
                        if (purchase.items && purchase.items.length > 0) {
                            html += '
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
                            ';
                            
                            purchase.items.forEach(item => {
                                html += '
                                    <tr>
                                        <td>${item.product_name} <small class="text-muted">(${item.product_sku})</small></td>
                                        <td>${item.quantity}</td>
                                        <td>${parseFloat(item.unit_cost).toFixed(2)} ر.س</td>
                                        <td>${parseFloat(item.discount_amount).toFixed(2)} ر.س</td>
                                        <td>${parseFloat(item.total_cost).toFixed(2)} ر.س</td>
                                    </tr>
                                ';
                                if (item.notes) {
                                    html += '<tr><td colspan="5"><small class="text-muted">ملاحظة: ${item.notes}</small></td></tr>';
                                }
                            });
                            
                            html += '</tbody></table></div>';
                        }
                        
                        document.getElementById('purchaseDetails').innerHTML = html;
                        new bootstrap.Modal(document.getElementById('viewPurchaseModal')).show();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء تحميل بيانات أمر الشراء');
                });
        }
        
        // Edit purchase
        function editPurchase(id) {
            fetch('purchase_orders.php?action=get_purchase&id=${id}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const purchase = data.purchase;
                        
                        // Fill form fields
                        document.getElementById('purchase_id').value = purchase.id;
                        document.querySelector('input[name="action"]').value = 'edit';
                        document.querySelector('.modal-title').textContent = 'تعديل أمر الشراء';
                        
                        // Fill basic fields
                        document.getElementById('supplier_id').value = purchase.supplier_id;
                        document.getElementById('warehouse_id').value = purchase.warehouse_id;
                        document.getElementById('po_date').value = purchase.po_date;
                        document.getElementById('expected_delivery_date').value = purchase.expected_delivery_date || '';
                        document.getElementById('notes').value = purchase.notes || '';
                        document.getElementById('terms_conditions').value = purchase.terms_conditions || '';
                        
                        // Clear existing product rows
                        document.getElementById('products-container').innerHTML = '';
                        productRowIndex = 0;
                        
                        // Add product rows
                        if (purchase.items && purchase.items.length > 0) {
                            purchase.items.forEach(item => {
                                addProductRow();
                                const currentRow = document.querySelectorAll('.product-row')[productRowIndex - 1];
                                
                                currentRow.querySelector('select[name*="[product_id]"]').value = item.product_id;
                                currentRow.querySelector('input[name*="[quantity]"]').value = item.quantity;
                                currentRow.querySelector('input[name*="[unit_cost]"]').value = parseFloat(item.unit_cost).toFixed(2);
                                currentRow.querySelector('input[name*="[discount_amount]"]').value = parseFloat(item.discount_amount).toFixed(2);
                                currentRow.querySelector('input[name*="[notes]"]').value = item.notes || '';
                                
                                calculateRowTotal(productRowIndex - 1);
                            });
                        } else {
                            addProductRow();
                        }
                        
                        // Show modal
                        new bootstrap.Modal(document.getElementById('purchaseModal')).show();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء تحميل بيانات أمر الشراء');
                });
        }
        
        // Update purchase status
        function updateStatus(id, status) {
            if (confirm('هل أنت متأكد من تغيير حالة أمر الشراء؟')) {
                fetch('purchase_orders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=update_status&id=${id}&status=${status}'
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
                    alert('حدث خطأ أثناء تحديث حالة أمر الشراء');
                });
            }
        }
        
        // Delete purchase
        function deletePurchase(id) {
            if (confirm('هل أنت متأكد من حذف أمر الشراء؟ لا يمكن التراجع عن هذا الإجراء.')) {
                fetch('purchase_orders.php', {
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
                    alert('حدث خطأ أثناء حذف أمر الشراء');
                });
            }
        }
        
        // Print purchase
        function printPurchase(id) {
            window.open('print_purchase.php?id=${id}', '_blank');
        }
        
        function printPurchaseModal() {
            window.print();
        }
        
        // Helper functions
        function getStatusLabel(status) {
            const labels = {
                'draft': 'مسودة',
                'sent': 'مرسل',
                'confirmed': 'مؤكد',
                'partially_received': 'مستلم جزئياً',
                'received': 'مستلم',
                'cancelled': 'ملغي'
            };
            return labels[status] || status;
        }
        
        // Reset form when modal is hidden
        document.getElementById('purchaseModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('purchaseForm').reset();
            document.getElementById('purchase_id').value = '';
            document.querySelector('input[name="action"]').value = 'add';
            document.querySelector('.modal-title').textContent = 'إضافة أمر شراء جديد';
            document.getElementById('products-container').innerHTML = '';
            productRowIndex = 0;
            addProductRow();
            document.getElementById('po_date').value = '<?php echo date('Y-m-d'); ?>';
        });
        
        // Initialize with one product row when page loads
        document.addEventListener('DOMContentLoaded', function() {
            addProductRow();
        });
        
        // Form validation before submit
        document.getElementById('purchaseForm').addEventListener('submit', function(e) {
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
                const costInput = row.querySelector('input[name*="[unit_cost]"]');
                
                if (productSelect.value && quantityInput.value && costInput.value) {
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