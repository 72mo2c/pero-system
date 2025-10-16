<?php
/**
 * Reports Page
 * صفحة التقارير
 */

require_once '../config/config.php';
require_once 'includes/auth_check.php';
require_once '../shared/classes/Database.php';
require_once '../shared/classes/Security.php';

$current_page = 'reports';
$page_title = 'التقارير';

// الحصول على قاعدة البيانات
$db = Database::getInstance();
$pdo = $db->getConnection();

$success_message = '';
$error_message = '';

// معاملات التصفية
$report_type = $_GET['report_type'] ?? 'tenants';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$export_format = $_GET['export_format'] ?? 'html';

// معالجة تصدير التقارير
if (isset($_GET['export']) && $_GET['export'] === 'true') {
    switch ($export_format) {
        case 'csv':
            exportToCSV($report_type, $date_from, $date_to);
            break;
        case 'pdf':
            exportToPDF($report_type, $date_from, $date_to);
            break;
        case 'excel':
            exportToExcel($report_type, $date_from, $date_to);
            break;
    }
}

// دالة تصدير CSV
function exportToCSV($report_type, $date_from, $date_to) {
    global $pdo;
    
    $filename = "report_{$report_type}_{$date_from}_{$date_to}.csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // إضافة BOM للدعم العربي
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($report_type === 'tenants') {
        fputcsv($output, ['ID', 'اسم الشركة', 'البريد الإلكتروني', 'نوع الاشتراك', 'حالة الاشتراك', 'تاريخ الإنشاء', 'تاريخ انتهاء الاشتراك']);
        
        $stmt = $pdo->prepare("SELECT id, company_name, email, subscription_plan, subscription_status, created_at, subscription_end FROM tenants WHERE DATE(created_at) BETWEEN ? AND ?");
        $stmt->execute([$date_from, $date_to]);
        
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                $row['company_name'],
                $row['email'],
                $row['subscription_plan'],
                $row['subscription_status'],
                $row['created_at'],
                $row['subscription_end']
            ]);
        }
    } elseif ($report_type === 'subscriptions') {
        fputcsv($output, ['نوع الاشتراك', 'عدد المشتركين', 'المبلغ الإجمالي']);
        
        $stmt = $pdo->query("SELECT subscription_plan, COUNT(*) as count FROM tenants GROUP BY subscription_plan");
        
        while ($row = $stmt->fetch()) {
            $amount = 0;
            switch ($row['subscription_plan']) {
                case 'basic': $amount = $row['count'] * 99; break;
                case 'premium': $amount = $row['count'] * 199; break;
                case 'enterprise': $amount = $row['count'] * 399; break;
            }
            
            fputcsv($output, [
                $row['subscription_plan'],
                $row['count'],
                $amount . ' ريال'
            ]);
        }
    }
    
    fclose($output);
    exit;
}

// دالة تصدير PDF (مبسطة)
function exportToPDF($report_type, $date_from, $date_to) {
    // في التطبيق الحقيقي، ستحتاج لمكتبة PDF مثل TCPDF أو DOMPDF
    $filename = "report_{$report_type}_{$date_from}_{$date_to}.pdf";
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "PDF export functionality requires additional libraries.";
    exit;
}

// دالة تصدير Excel (مبسطة)
function exportToExcel($report_type, $date_from, $date_to) {
    // في التطبيق الحقيقي، ستحتاج لمكتبة PhpSpreadsheet
    $filename = "report_{$report_type}_{$date_from}_{$date_to}.xlsx";
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "Excel export functionality requires PhpSpreadsheet library.";
    exit;
}

// جلب بيانات التقارير
$report_data = [];

try {
    switch ($report_type) {
        case 'tenants':
            $stmt = $pdo->prepare("
                SELECT id, company_name, email, subscription_plan, subscription_status, 
                       created_at, subscription_end, 
                       DATEDIFF(subscription_end, CURDATE()) as days_remaining
                FROM tenants 
                WHERE DATE(created_at) BETWEEN ? AND ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $report_data = $stmt->fetchAll();
            break;
            
        case 'subscriptions':
            $stmt = $pdo->query("
                SELECT subscription_plan, 
                       COUNT(*) as total_count,
                       SUM(CASE WHEN subscription_status = 'active' THEN 1 ELSE 0 END) as active_count,
                       SUM(CASE WHEN subscription_end < CURDATE() THEN 1 ELSE 0 END) as expired_count
                FROM tenants 
                GROUP BY subscription_plan
                ORDER BY total_count DESC
            ");
            $report_data = $stmt->fetchAll();
            break;
            
        case 'revenue':
            // تقرير الإيرادات المتوقعة حسب نوع الاشتراك
            $plans_pricing = [
                'trial' => 0,
                'basic' => 99,
                'premium' => 199,
                'enterprise' => 399
            ];
            
            $stmt = $pdo->query("
                SELECT subscription_plan, 
                       COUNT(*) as count,
                       SUM(CASE WHEN subscription_status = 'active' THEN 1 ELSE 0 END) as active_count
                FROM tenants 
                GROUP BY subscription_plan
            ");
            
            $temp_data = $stmt->fetchAll();
            foreach ($temp_data as $row) {
                $row['price'] = $plans_pricing[$row['subscription_plan']] ?? 0;
                $row['monthly_revenue'] = $row['active_count'] * $row['price'];
                $row['annual_revenue'] = $row['monthly_revenue'] * 12;
                $report_data[] = $row;
            }
            break;
            
        case 'activity':
            $stmt = $pdo->prepare("
                SELECT DATE(created_at) as date, 
                       COUNT(*) as new_tenants,
                       log_level,
                       COUNT(*) as log_count
                FROM system_logs 
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at), log_level
                ORDER BY date DESC
            ");
            $stmt->execute([$date_from, $date_to]);
            $report_data = $stmt->fetchAll();
            break;
            
        case 'locations':
            $stmt = $pdo->query("
                SELECT city, 
                       COUNT(*) as tenant_count,
                       SUM(CASE WHEN subscription_status = 'active' THEN 1 ELSE 0 END) as active_count
                FROM tenants 
                WHERE city IS NOT NULL AND city != ''
                GROUP BY city 
                ORDER BY tenant_count DESC
                LIMIT 20
            ");
            $report_data = $stmt->fetchAll();
            break;
    }
} catch (Exception $e) {
    $error_message = "خطأ في جلب بيانات التقرير: " . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>التقارير</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">الرئيسية</a></li>
                        <li class="breadcrumb-item active">التقارير</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- مرشحات التقرير -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">إعدادات التقرير</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="report_type" class="form-label">نوع التقرير</label>
                            <select class="form-select" id="report_type" name="report_type">
                                <option value="tenants" <?php echo $report_type === 'tenants' ? 'selected' : ''; ?>>تقرير المستأجرين</option>
                                <option value="subscriptions" <?php echo $report_type === 'subscriptions' ? 'selected' : ''; ?>>تقرير الاشتراكات</option>
                                <option value="revenue" <?php echo $report_type === 'revenue' ? 'selected' : ''; ?>>تقرير الإيرادات</option>
                                <option value="activity" <?php echo $report_type === 'activity' ? 'selected' : ''; ?>>تقرير النشاط</option>
                                <option value="locations" <?php echo $report_type === 'locations' ? 'selected' : ''; ?>>تقرير المواقع</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">من تاريخ</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">إلى تاريخ</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="export_format" class="form-label">صيغة التصدير</label>
                            <select class="form-select" id="export_format" name="export_format">
                                <option value="html">عرض على الشاشة</option>
                                <option value="csv">CSV</option>
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-chart-bar"></i> إنشاء التقرير
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- أزرار التصدير -->
            <?php if (!empty($report_data)): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'true', 'export_format' => 'csv'])); ?>" 
                                   class="btn btn-success w-100">
                                    <i class="fas fa-file-csv"></i> تصدير CSV
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'true', 'export_format' => 'pdf'])); ?>" 
                                   class="btn btn-danger w-100">
                                    <i class="fas fa-file-pdf"></i> تصدير PDF
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'true', 'export_format' => 'excel'])); ?>" 
                                   class="btn btn-info w-100">
                                    <i class="fas fa-file-excel"></i> تصدير Excel
                                </a>
                            </div>
                            <div class="col-md-3">
                                <button onclick="window.print()" class="btn btn-secondary w-100">
                                    <i class="fas fa-print"></i> طباعة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- محتوى التقرير -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <?php 
                        $report_titles = [
                            'tenants' => 'تقرير المستأجرين',
                            'subscriptions' => 'تقرير الاشتراكات', 
                            'revenue' => 'تقرير الإيرادات',
                            'activity' => 'تقرير النشاط',
                            'locations' => 'تقرير المواقع'
                        ];
                        echo $report_titles[$report_type] ?? 'تقرير';
                        ?>
                        <small class="text-muted">(من <?php echo $date_from; ?> إلى <?php echo $date_to; ?>)</small>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($report_data)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <?php if ($report_type === 'tenants'): ?>
                                            <th>ID</th>
                                            <th>اسم الشركة</th>
                                            <th>البريد الإلكتروني</th>
                                            <th>نوع الاشتراك</th>
                                            <th>حالة الاشتراك</th>
                                            <th>تاريخ الإنشاء</th>
                                            <th>الأيام المتبقية</th>
                                        <?php elseif ($report_type === 'subscriptions'): ?>
                                            <th>نوع الاشتراك</th>
                                            <th>إجمالي المشتركين</th>
                                            <th>المشتركين النشطين</th>
                                            <th>المشتركين المنتهيين</th>
                                            <th>معدل النشاط</th>
                                        <?php elseif ($report_type === 'revenue'): ?>
                                            <th>نوع الاشتراك</th>
                                            <th>عدد المشتركين</th>
                                            <th>المشتركين النشطين</th>
                                            <th>سعر الاشتراك</th>
                                            <th>الإيراد الشهري</th>
                                            <th>الإيراد السنوي</th>
                                        <?php elseif ($report_type === 'activity'): ?>
                                            <th>التاريخ</th>
                                            <th>مستوى السجل</th>
                                            <th>عدد السجلات</th>
                                        <?php elseif ($report_type === 'locations'): ?>
                                            <th>المدينة</th>
                                            <th>عدد المستأجرين</th>
                                            <th>المستأجرين النشطين</th>
                                            <th>معدل النشاط</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <?php if ($report_type === 'tenants'): ?>
                                                <td><?php echo $row['id']; ?></td>
                                                <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $row['subscription_plan']; ?></span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_class = $row['subscription_status'] === 'active' ? 'success' : 'warning';
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_class; ?>">
                                                        <?php echo $row['subscription_status']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($row['days_remaining'] > 0): ?>
                                                        <span class="text-success"><?php echo $row['days_remaining']; ?> يوم</span>
                                                    <?php else: ?>
                                                        <span class="text-danger">منتهي</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php elseif ($report_type === 'subscriptions'): ?>
                                                <td><?php echo $row['subscription_plan']; ?></td>
                                                <td><?php echo number_format($row['total_count']); ?></td>
                                                <td><?php echo number_format($row['active_count']); ?></td>
                                                <td><?php echo number_format($row['expired_count']); ?></td>
                                                <td>
                                                    <?php 
                                                    $activity_rate = $row['total_count'] > 0 ? round(($row['active_count'] / $row['total_count']) * 100, 1) : 0;
                                                    ?>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-success" style="width: <?php echo $activity_rate; ?>%">
                                                            <?php echo $activity_rate; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            <?php elseif ($report_type === 'revenue'): ?>
                                                <td><?php echo $row['subscription_plan']; ?></td>
                                                <td><?php echo number_format($row['count']); ?></td>
                                                <td><?php echo number_format($row['active_count']); ?></td>
                                                <td><?php echo number_format($row['price']); ?> ريال</td>
                                                <td><?php echo number_format($row['monthly_revenue']); ?> ريال</td>
                                                <td><?php echo number_format($row['annual_revenue']); ?> ريال</td>
                                            <?php elseif ($report_type === 'activity'): ?>
                                                <td><?php echo $row['date']; ?></td>
                                                <td>
                                                    <?php 
                                                    $level_class = 'secondary';
                                                    switch ($row['log_level']) {
                                                        case 'info': $level_class = 'info'; break;
                                                        case 'warning': $level_class = 'warning'; break;
                                                        case 'error': $level_class = 'danger'; break;
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?php echo $level_class; ?>">
                                                        <?php echo $row['log_level']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($row['log_count']); ?></td>
                                            <?php elseif ($report_type === 'locations'): ?>
                                                <td><?php echo htmlspecialchars($row['city']); ?></td>
                                                <td><?php echo number_format($row['tenant_count']); ?></td>
                                                <td><?php echo number_format($row['active_count']); ?></td>
                                                <td>
                                                    <?php 
                                                    $activity_rate = $row['tenant_count'] > 0 ? round(($row['active_count'] / $row['tenant_count']) * 100, 1) : 0;
                                                    ?>
                                                    <?php echo $activity_rate; ?>%
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- ملخص التقرير -->
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6>ملخص التقرير:</h6>
                            <ul class="mb-0">
                                <li>إجمالي السجلات: <?php echo count($report_data); ?></li>
                                <li>تاريخ إنشاء التقرير: <?php echo date('Y-m-d H:i:s'); ?></li>
                                <li>فترة التقرير: من <?php echo $date_from; ?> إلى <?php echo $date_to; ?></li>
                                <?php if ($report_type === 'revenue'): ?>
                                    <li>إجمالي الإيراد الشهري المتوقع: 
                                        <?php echo number_format(array_sum(array_column($report_data, 'monthly_revenue'))); ?> ريال
                                    </li>
                                    <li>إجمالي الإيراد السنوي المتوقع: 
                                        <?php echo number_format(array_sum(array_column($report_data, 'annual_revenue'))); ?> ريال
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">لا توجد بيانات</h5>
                            <p class="text-muted">لا توجد بيانات متاحة للفترة المحددة</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .navbar, .breadcrumb, .card-header .btn, .btn {
        display: none !important;
    }
    
    .content-wrapper {
        margin: 0 !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<?php include 'includes/footer.php'; ?>