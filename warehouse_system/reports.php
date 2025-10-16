<?php
/**
 * Reports Dashboard
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

// Get date filters
$date_from = Security::sanitizeInput($_GET['date_from'] ?? date('Y-m-01'));
$date_to = Security::sanitizeInput($_GET['date_to'] ?? date('Y-m-d'));

// Sales Statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_sales,
        COALESCE(SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END), 0) as delivered_sales,
        COALESCE(SUM(paid_amount), 0) as total_payments,
        COALESCE(AVG(total_amount), 0) as avg_order_value
    FROM sales_orders 
    WHERE so_date BETWEEN ? AND ?
");
$stmt->execute([$date_from, $date_to]);
$sales_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Purchase Statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_purchases,
        COALESCE(SUM(CASE WHEN status = 'received' THEN total_amount ELSE 0 END), 0) as received_purchases,
        COALESCE(SUM(paid_amount), 0) as total_payments
    FROM purchase_orders 
    WHERE po_date BETWEEN ? AND ?
");
$stmt->execute([$date_from, $date_to]);
$purchase_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Inventory Statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_products,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_products,
        COALESCE(SUM(ps.available_quantity), 0) as total_stock,
        COALESCE(SUM(ps.available_quantity * p.cost_price), 0) as stock_value
    FROM products p
    LEFT JOIN product_stock ps ON p.id = ps.product_id
");
$inventory_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Low Stock Products
$stmt = $pdo->query("
    SELECT p.name, p.sku, p.min_stock_level, COALESCE(SUM(ps.available_quantity), 0) as current_stock
    FROM products p
    LEFT JOIN product_stock ps ON p.id = ps.product_id
    WHERE p.is_active = 1
    GROUP BY p.id
    HAVING current_stock <= p.min_stock_level OR (current_stock = 0 AND p.min_stock_level > 0)
    ORDER BY current_stock ASC
    LIMIT 10
");
$low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top Selling Products
$stmt = $pdo->prepare("
    SELECT p.name, p.sku, SUM(soi.quantity) as total_sold, SUM(soi.total_price) as total_revenue
    FROM sales_order_items soi
    JOIN products p ON soi.product_id = p.id
    JOIN sales_orders so ON soi.sales_order_id = so.id
    WHERE so.so_date BETWEEN ? AND ? AND so.status != 'cancelled'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 10
");
$stmt->execute([$date_from, $date_to]);
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent Sales
$stmt = $pdo->prepare("
    SELECT so.so_number, c.name as customer_name, so.total_amount, so.status, so.so_date
    FROM sales_orders so
    LEFT JOIN customers c ON so.customer_id = c.id
    WHERE so.so_date BETWEEN ? AND ?
    ORDER BY so.created_at DESC
    LIMIT 10
");
$stmt->execute([$date_from, $date_to]);
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly Sales Chart Data
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(so_date, '%Y-%m') as month,
        COUNT(*) as orders,
        SUM(total_amount) as revenue
    FROM sales_orders 
    WHERE so_date >= DATE_SUB(?, INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(so_date, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute([$date_to]);
$monthly_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'التقارير والإحصائيات';
$current_page = 'reports';
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
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .sales-icon { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .purchase-icon { background: linear-gradient(135deg, #6f42c1 0%, #563d7c 100%); }
        .inventory-icon { background: linear-gradient(135deg, #fd7e14 0%, #e55a4e 100%); }
        .finance-icon { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .report-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .report-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #dee2e6;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            padding: 20px;
        }
        
        .table-container {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .filters-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .low-stock-item {
            padding: 10px;
            border-left: 4px solid #dc3545;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        
        .top-product-item {
            padding: 10px;
            border-left: 4px solid #28a745;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 5px;
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
                            <i class="fas fa-chart-bar me-2"></i>
                            التقارير والإحصائيات
                        </h2>
                        
                        <div>
                            <button type="button" class="btn btn-primary" onclick="exportReport()">
                                <i class="fas fa-file-export me-2"></i>
                                تصدير التقرير
                            </button>
                            <button type="button" class="btn btn-outline-primary" onclick="printReport()">
                                <i class="fas fa-print me-2"></i>
                                طباعة
                            </button>
                        </div>
                    </div>
                    
                    <!-- Date Filters -->
                    <div class="filters-card">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">من تاريخ</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">إلى تاريخ</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">
                                    <i class="fas fa-filter me-1"></i>
                                    تطبيق الفلتر
                                </button>
                            </div>
                            
                            <div class="col-md-4 text-end">
                                <label class="form-label">الفترة المحددة</label>
                                <div class="form-control-plaintext">
                                    من <?php echo date('d/m/Y', strtotime($date_from)); ?> إلى <?php echo date('d/m/Y', strtotime($date_to)); ?>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <div class="stats-icon sales-icon mx-auto">
                                        <i class="fas fa-shopping-cart"></i>
                                    </div>
                                    <div class="stat-number"><?php echo number_format($sales_stats['total_orders']); ?></div>
                                    <div class="stat-label">طلبات المبيعات</div>
                                    <hr>
                                    <div class="text-success">
                                        <strong><?php echo Utils::formatCurrency($sales_stats['total_sales']); ?></strong>
                                        <br><small>إجمالي المبيعات</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <div class="stats-icon purchase-icon mx-auto">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <div class="stat-number"><?php echo number_format($purchase_stats['total_orders']); ?></div>
                                    <div class="stat-label">أوامر الشراء</div>
                                    <hr>
                                    <div class="text-purple">
                                        <strong><?php echo Utils::formatCurrency($purchase_stats['total_purchases']); ?></strong>
                                        <br><small>إجمالي المشتريات</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <div class="stats-icon inventory-icon mx-auto">
                                        <i class="fas fa-boxes"></i>
                                    </div>
                                    <div class="stat-number"><?php echo number_format($inventory_stats['total_products']); ?></div>
                                    <div class="stat-label">المنتجات</div>
                                    <hr>
                                    <div class="text-warning">
                                        <strong><?php echo number_format($inventory_stats['total_stock']); ?></strong>
                                        <br><small>إجمالي المخزون</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <div class="stats-icon finance-icon mx-auto">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="stat-number"><?php echo Utils::formatCurrency($sales_stats['avg_order_value']); ?></div>
                                    <div class="stat-label">متوسط قيمة الطلب</div>
                                    <hr>
                                    <div class="text-info">
                                        <strong><?php echo Utils::formatCurrency($inventory_stats['stock_value']); ?></strong>
                                        <br><small>قيمة المخزون</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts and Tables -->
                    <div class="row">
                        <!-- Monthly Sales Chart -->
                        <div class="col-md-8">
                            <div class="card report-card">
                                <div class="report-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-chart-line me-2"></i>
                                        المبيعات الشهرية (آخر 12 شهر)
                                    </h5>
                                </div>
                                <div class="chart-container">
                                    <canvas id="salesChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Low Stock Alert -->
                        <div class="col-md-4">
                            <div class="card report-card">
                                <div class="report-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-exclamation-triangle me-2 text-danger"></i>
                                        تنبيه المخزون المنخفض
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($low_stock_products)): ?>
                                        <div class="text-center text-muted py-3">
                                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                                            <div>جميع المنتجات في مستوى آمن</div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($low_stock_products as $product): ?>
                                            <div class="low-stock-item">
                                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($product['sku']); ?></small>
                                                <div class="mt-1">
                                                    <span class="badge bg-danger"><?php echo $product['current_stock']; ?></span>
                                                    /
                                                    <span class="text-muted"><?php echo $product['min_stock_level']; ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Top Selling Products -->
                        <div class="col-md-6">
                            <div class="card report-card">
                                <div class="report-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-star me-2"></i>
                                        أكثر المنتجات مبيعاً
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($top_products)): ?>
                                        <div class="text-center text-muted py-3">
                                            <i class="fas fa-box-open fa-2x mb-2"></i>
                                            <div>لا توجد مبيعات في الفترة المحددة</div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($top_products as $index => $product): ?>
                                            <div class="top-product-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <span class="badge bg-primary">#<?php echo ($index + 1); ?></span>
                                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($product['sku']); ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <div class="text-success fw-bold"><?php echo number_format($product['total_sold']); ?> قطعة</div>
                                                        <small class="text-muted"><?php echo Utils::formatCurrency($product['total_revenue']); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Sales -->
                        <div class="col-md-6">
                            <div class="card report-card">
                                <div class="report-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-clock me-2"></i>
                                        أحدث المبيعات
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-container">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>رقم الفاتورة</th>
                                                    <th>العميل</th>
                                                    <th>المبلغ</th>
                                                    <th>الحالة</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($recent_sales)): ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center py-3 text-muted">
                                                            لا توجد مبيعات في الفترة المحددة
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($recent_sales as $sale): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($sale['so_number']); ?></strong>
                                                                <br><small class="text-muted"><?php echo date('d/m/Y', strtotime($sale['so_date'])); ?></small>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($sale['customer_name'] ?: 'غير محدد'); ?></td>
                                                            <td><?php echo Utils::formatCurrency($sale['total_amount']); ?></td>
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
                                                                <span class="badge bg-<?php echo $status_colors[$sale['status']] ?? 'secondary'; ?>">
                                                                    <?php echo $status_labels[$sale['status']] ?? $sale['status']; ?>
                                                                </span>
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
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/app.js"></script>
    
    <script>
        // Sales Chart
        const monthlyData = <?php echo json_encode($monthly_sales); ?>;
        
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('ar-SA', { year: 'numeric', month: 'short' });
                }),
                datasets: [{
                    label: 'المبيعات (ر.س)',
                    data: monthlyData.map(item => item.revenue),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'عدد الطلبات',
                    data: monthlyData.map(item => item.orders),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'المبيعات (ر.س)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'عدد الطلبات'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
        
        // Export report
        function exportReport() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.location.href = 'export_report.php?' + params.toString();
        }
        
        // Print report
        function printReport() {
            window.print();
        }
        
        // Auto-refresh every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
    </script>
    
    <style>
    @media print {
        .btn, .filters-card {
            display: none !important;
        }
        
        .stats-card, .report-card {
            break-inside: avoid;
            margin-bottom: 15px;
        }
        
        .chart-container {
            height: 250px !important;
        }
    }
    </style>
</body>
</html>