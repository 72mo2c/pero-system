<?php
/**
 * Warehouse System Dashboard
 * Main dashboard for tenant users
 * Created: 2025-10-16
 */

require_once '../config/config.php';

// Initialize security and session
Security::initialize();
Session::initialize();

// Check if user is logged in
Session::requireTenantLogin();

// Get database connection for current tenant
$pdo = DatabaseConfig::getTenantConnection(Session::getTenantId());

// Get dashboard statistics
$stats = [];

try {
    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
    $stats['total_products'] = $stmt->fetch()['count'];
    
    // Low stock products
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT p.id) as count 
        FROM products p 
        JOIN product_stock ps ON p.id = ps.product_id 
        WHERE p.is_active = 1 AND ps.available_quantity <= p.min_stock_level
    ");
    $stats['low_stock_products'] = $stmt->fetch()['count'];
    
    // Total customers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customers WHERE is_active = 1");
    $stats['total_customers'] = $stmt->fetch()['count'];
    
    // Total suppliers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM suppliers WHERE is_active = 1");
    $stats['total_suppliers'] = $stmt->fetch()['count'];
    
    // This month's sales
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM sales_orders 
        WHERE MONTH(so_date) = MONTH(NOW()) AND YEAR(so_date) = YEAR(NOW()) 
        AND status NOT IN ('cancelled', 'draft')
    ");
    $stats['monthly_sales'] = $stmt->fetch()['total'];
    
    // This month's purchases
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM purchase_orders 
        WHERE MONTH(po_date) = MONTH(NOW()) AND YEAR(po_date) = YEAR(NOW()) 
        AND status NOT IN ('cancelled', 'draft')
    ");
    $stats['monthly_purchases'] = $stmt->fetch()['total'];
    
    // Recent sales orders
    $stmt = $pdo->query("
        SELECT so.id, so.so_number, c.name as customer_name, so.total_amount, so.so_date, so.status
        FROM sales_orders so
        LEFT JOIN customers c ON so.customer_id = c.id
        ORDER BY so.created_at DESC
        LIMIT 5
    ");
    $recent_sales = $stmt->fetchAll();
    
    // Recent purchase orders
    $stmt = $pdo->query("
        SELECT po.id, po.po_number, s.name as supplier_name, po.total_amount, po.po_date, po.status
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        ORDER BY po.created_at DESC
        LIMIT 5
    ");
    $recent_purchases = $stmt->fetchAll();
    
    // Low stock products list
    $stmt = $pdo->query("
        SELECT p.name, p.sku, ps.available_quantity, p.min_stock_level, w.name as warehouse_name
        FROM products p 
        JOIN product_stock ps ON p.id = ps.product_id
        JOIN warehouses w ON ps.warehouse_id = w.id
        WHERE p.is_active = 1 AND ps.available_quantity <= p.min_stock_level
        ORDER BY (ps.available_quantity / p.min_stock_level) ASC
        LIMIT 10
    ");
    $low_stock_products = $stmt->fetchAll();
    
    // Sales chart data (last 12 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(so_date, '%Y-%m') as month,
            SUM(total_amount) as total_sales
        FROM sales_orders 
        WHERE so_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        AND status NOT IN ('cancelled', 'draft')
        GROUP BY DATE_FORMAT(so_date, '%Y-%m')
        ORDER BY month
    ");
    $sales_chart_data = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    $stats = array_fill_keys(['total_products', 'low_stock_products', 'total_customers', 'total_suppliers', 'monthly_sales', 'monthly_purchases'], 0);
    $recent_sales = [];
    $recent_purchases = [];
    $low_stock_products = [];
    $sales_chart_data = [];
}

$page_title = 'لوحة التحكم';
$current_page = 'dashboard';
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
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-card.warning {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
        }
        .stats-card.success {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }
        .stats-card.info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
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
                            <i class="fas fa-tachometer-alt me-2"></i>
                            لوحة التحكم
                        </h2>
                        <div class="text-muted">
                            <i class="fas fa-calendar-day me-1"></i>
                            <?php echo Utils::formatDate(date('Y-m-d'), 'Y/m/d'); ?>
                        </div>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="stats-card info">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1">إجمالي المنتجات</h5>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_products']); ?></h3>
                                    </div>
                                    <i class="fas fa-boxes fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="stats-card warning">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1">مخزون منخفض</h5>
                                        <h3 class="mb-0"><?php echo number_format($stats['low_stock_products']); ?></h3>
                                    </div>
                                    <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="stats-card success">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1">إجمالي العملاء</h5>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_customers']); ?></h3>
                                    </div>
                                    <i class="fas fa-users fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-3">
                            <div class="stats-card info">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1">إجمالي الموردين</h5>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_suppliers']); ?></h3>
                                    </div>
                                    <i class="fas fa-truck fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Financial Summary -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <div class="chart-container">
                                <h5 class="mb-3">
                                    <i class="fas fa-chart-line me-2 text-success"></i>
                                    مبيعات هذا الشهر
                                </h5>
                                <h3 class="text-success"><?php echo Utils::formatCurrency($stats['monthly_sales']); ?></h3>
                                <small class="text-muted">الإجمالي لشهر <?php echo date('m/Y'); ?></small>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="chart-container">
                                <h5 class="mb-3">
                                    <i class="fas fa-chart-line me-2 text-primary"></i>
                                    مشتريات هذا الشهر
                                </h5>
                                <h3 class="text-primary"><?php echo Utils::formatCurrency($stats['monthly_purchases']); ?></h3>
                                <small class="text-muted">الإجمالي لشهر <?php echo date('m/Y'); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts Row -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="chart-container">
                                <h5 class="mb-3">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    مخطط المبيعات (آخر 12 شهر)
                                </h5>
                                <canvas id="salesChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activities -->
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="table-container">
                                <div class="table-header p-3 bg-light">
                                    <h5 class="mb-0">
                                        <i class="fas fa-shopping-cart me-2"></i>
                                        أحدث أوامر البيع
                                    </h5>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>رقم الأمر</th>
                                                <th>العميل</th>
                                                <th>المبلغ</th>
                                                <th>الحالة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_sales)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted py-4">
                                                        لا توجد أوامر بيع حديثة
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_sales as $sale): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($sale['so_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($sale['customer_name'] ?: 'غير محدد'); ?></td>
                                                        <td><?php echo Utils::formatCurrency($sale['total_amount']); ?></td>
                                                        <td>
                                                            <?php
                                                            $status_classes = [
                                                                'draft' => 'bg-secondary',
                                                                'confirmed' => 'bg-info',
                                                                'processing' => 'bg-warning',
                                                                'shipped' => 'bg-primary',
                                                                'delivered' => 'bg-success',
                                                                'cancelled' => 'bg-danger'
                                                            ];
                                                            $status_labels = [
                                                                'draft' => 'مسودة',
                                                                'confirmed' => 'مؤكد',
                                                                'processing' => 'قيد التحضير',
                                                                'shipped' => 'مشحون',
                                                                'delivered' => 'مسلم',
                                                                'cancelled' => 'ملغي'
                                                            ];
                                                            $class = $status_classes[$sale['status']] ?? 'bg-secondary';
                                                            $label = $status_labels[$sale['status']] ?? $sale['status'];
                                                            ?>
                                                            <span class="status-badge <?php echo $class; ?>"><?php echo $label; ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="table-container">
                                <div class="table-header p-3 bg-light">
                                    <h5 class="mb-0">
                                        <i class="fas fa-truck me-2"></i>
                                        أحدث أوامر الشراء
                                    </h5>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>رقم الأمر</th>
                                                <th>المورد</th>
                                                <th>المبلغ</th>
                                                <th>الحالة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recent_purchases)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted py-4">
                                                        لا توجد أوامر شراء حديثة
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_purchases as $purchase): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($purchase['po_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($purchase['supplier_name'] ?: 'غير محدد'); ?></td>
                                                        <td><?php echo Utils::formatCurrency($purchase['total_amount']); ?></td>
                                                        <td>
                                                            <?php
                                                            $status_classes = [
                                                                'draft' => 'bg-secondary',
                                                                'sent' => 'bg-info',
                                                                'confirmed' => 'bg-warning',
                                                                'partially_received' => 'bg-primary',
                                                                'received' => 'bg-success',
                                                                'cancelled' => 'bg-danger'
                                                            ];
                                                            $status_labels = [
                                                                'draft' => 'مسودة',
                                                                'sent' => 'مرسل',
                                                                'confirmed' => 'مؤكد',
                                                                'partially_received' => 'مستلم جزئياً',
                                                                'received' => 'مستلم',
                                                                'cancelled' => 'ملغي'
                                                            ];
                                                            $class = $status_classes[$purchase['status']] ?? 'bg-secondary';
                                                            $label = $status_labels[$purchase['status']] ?? $purchase['status'];
                                                            ?>
                                                            <span class="status-badge <?php echo $class; ?>"><?php echo $label; ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Low Stock Alert -->
                    <?php if (!empty($low_stock_products)): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="table-container">
                                <div class="table-header p-3 bg-warning text-dark">
                                    <h5 class="mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        تنبيه: منتجات بمخزون منخفض
                                    </h5>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>اسم المنتج</th>
                                                <th>رمز المنتج</th>
                                                <th>الكمية المتاحة</th>
                                                <th>الحد الأدنى</th>
                                                <th>المخزن</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($low_stock_products as $product): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                                    <td class="text-danger">
                                                        <strong><?php echo number_format($product['available_quantity']); ?></strong>
                                                    </td>
                                                    <td><?php echo number_format($product['min_stock_level']); ?></td>
                                                    <td><?php echo htmlspecialchars($product['warehouse_name']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($sales_chart_data, 'month')); ?>,
                datasets: [{
                    label: 'المبيعات',
                    data: <?php echo json_encode(array_column($sales_chart_data, 'total_sales')); ?>,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('ar-SA', {
                                    style: 'currency',
                                    currency: 'SAR'
                                }).format(value);
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
