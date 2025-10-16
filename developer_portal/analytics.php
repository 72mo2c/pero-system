<?php
/**
 * Analytics Dashboard
 * لوحة التحليلات
 */

require_once '../config/config.php';
require_once 'includes/auth_check.php';
require_once '../shared/classes/Database.php';
require_once '../shared/classes/Security.php';

$current_page = 'analytics';
$page_title = 'التحليلات والإحصائيات';

// الحصول على قاعدة البيانات
$db = Database::getInstance();
$pdo = $db->getConnection();

$success_message = '';
$error_message = '';

// معاملات التصفية
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // بداية الشهر الحالي
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // اليوم الحالي

// إحصائيات عامة
$general_stats = [];

try {
    // إجمالي المستأجرين
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tenants");
    $general_stats['total_tenants'] = $stmt->fetch()['total'];
    
    // المستأجرين النشطين
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM tenants WHERE subscription_status = 'active'");
    $general_stats['active_tenants'] = $stmt->fetch()['active'];
    
    // المستأجرين الجدد هذا الشهر
    $stmt = $pdo->query("SELECT COUNT(*) as new_this_month FROM tenants WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $general_stats['new_tenants_month'] = $stmt->fetch()['new_this_month'];
    
    // الاشتراكات المنتهية
    $stmt = $pdo->query("SELECT COUNT(*) as expired FROM tenants WHERE subscription_end < CURDATE()");
    $general_stats['expired_subscriptions'] = $stmt->fetch()['expired'];
    
    // متوسط مدة الاشتراك
    $stmt = $pdo->query("SELECT AVG(DATEDIFF(subscription_end, subscription_start)) as avg_subscription_days FROM tenants WHERE subscription_status = 'active'");
    $avg_days = $stmt->fetch()['avg_subscription_days'];
    $general_stats['avg_subscription_days'] = round($avg_days, 0);
    
} catch (Exception $e) {
    $error_message = "خطأ في جلب الإحصائيات العامة: " . $e->getMessage();
}

// إحصائيات الاشتراكات حسب النوع
$subscription_stats = [];

try {
    $stmt = $pdo->query("SELECT subscription_plan, COUNT(*) as count FROM tenants GROUP BY subscription_plan ORDER BY count DESC");
    while ($row = $stmt->fetch()) {
        $subscription_stats[] = [
            'plan' => $row['subscription_plan'],
            'count' => $row['count']
        ];
    }
} catch (Exception $e) {
    $error_message = "خطأ في جلب إحصائيات الاشتراكات: " . $e->getMessage();
}

// نمو المستأجرين خلال الأشهر الماضية
$growth_data = [];

try {
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as new_tenants
        FROM tenants 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    
    while ($row = $stmt->fetch()) {
        $growth_data[] = [
            'month' => $row['month'],
            'count' => $row['new_tenants']
        ];
    }
} catch (Exception $e) {
    $error_message = "خطأ في جلب بيانات النمو: " . $e->getMessage();
}

// حالات الاشتراكات
$status_distribution = [];

try {
    $stmt = $pdo->query("
        SELECT 
            CASE 
                WHEN subscription_end < CURDATE() THEN 'منتهي'
                WHEN subscription_status = 'active' THEN 'نشط'
                WHEN subscription_status = 'pending' THEN 'معلق'
                ELSE 'غير محدد'
            END as status_text,
            COUNT(*) as count
        FROM tenants 
        GROUP BY status_text
        ORDER BY count DESC
    ");
    
    while ($row = $stmt->fetch()) {
        $status_distribution[] = [
            'status' => $row['status_text'],
            'count' => $row['count']
        ];
    }
} catch (Exception $e) {
    $error_message = "خطأ في جلب توزيع الحالات: " . $e->getMessage();
}

// أكثر المدن نشاطاً
$city_stats = [];

try {
    $stmt = $pdo->query("
        SELECT city, COUNT(*) as count 
        FROM tenants 
        WHERE city IS NOT NULL AND city != ''
        GROUP BY city 
        ORDER BY count DESC 
        LIMIT 10
    ");
    
    while ($row = $stmt->fetch()) {
        $city_stats[] = [
            'city' => $row['city'],
            'count' => $row['count']
        ];
    }
} catch (Exception $e) {
    $error_message = "خطأ في جلب إحصائيات المدن: " . $e->getMessage();
}

// السجلات الأخيرة
$recent_activities = [];

try {
    $stmt = $pdo->query("
        SELECT log_level, log_message, created_at, user_action 
        FROM system_logs 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    $recent_activities = $stmt->fetchAll();
} catch (Exception $e) {
    // إذا لم يكن جدول السجلات موجوداً، نتجاهل الخطأ
}

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>التحليلات والإحصائيات</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="dashboard.php">الرئيسية</a></li>
                        <li class="breadcrumb-item active">التحليلات</li>
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

            <!-- مرشحات التاريخ -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">من تاريخ</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">إلى تاريخ</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> تطبيق المرشح
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="button" class="btn btn-success" onclick="exportAnalytics()">
                                    <i class="fas fa-download"></i> تصدير التقرير
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- الإحصائيات العامة -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3><?php echo number_format($general_stats['total_tenants'] ?? 0); ?></h3>
                                    <p class="mb-0">إجمالي المستأجرين</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3><?php echo number_format($general_stats['active_tenants'] ?? 0); ?></h3>
                                    <p class="mb-0">مستأجرين نشطين</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3><?php echo number_format($general_stats['new_tenants_month'] ?? 0); ?></h3>
                                    <p class="mb-0">مستأجرين جدد (30 يوم)</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-user-plus fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3><?php echo number_format($general_stats['avg_subscription_days'] ?? 0); ?></h3>
                                    <p class="mb-0">متوسط مدة الاشتراك (يوم)</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <!-- رسم بياني لنمو المستأجرين -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">نمو المستأجرين - آخر 6 أشهر</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="growthChart" style="height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- توزيع أنواع الاشتراكات -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">توزيع أنواع الاشتراكات</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="subscriptionChart" style="height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <!-- حالات الاشتراكات -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">توزيع حالات الاشتراكات</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="statusChart" style="height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- أكثر المدن نشاطاً -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">أكثر المدن نشاطاً</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($city_stats)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>المدينة</th>
                                                <th>عدد المستأجرين</th>
                                                <th>النسبة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total = array_sum(array_column($city_stats, 'count'));
                                            foreach ($city_stats as $city): 
                                                $percentage = round(($city['count'] / $total) * 100, 1);
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($city['city']); ?></td>
                                                    <td><?php echo number_format($city['count']); ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar" style="width: <?php echo $percentage; ?>%">
                                                                <?php echo $percentage; ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">لا توجد بيانات متاحة</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- النشاط الأخير -->
            <?php if (!empty($recent_activities)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">النشاط الأخير</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>الوقت</th>
                                                <th>المستوى</th>
                                                <th>الرسالة</th>
                                                <th>العملية</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_activities as $activity): ?>
                                                <tr>
                                                    <td>
                                                        <small><?php echo date('H:i:s', strtotime($activity['created_at'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $badge_class = 'secondary';
                                                        switch ($activity['log_level']) {
                                                            case 'info': $badge_class = 'info'; break;
                                                            case 'warning': $badge_class = 'warning'; break;
                                                            case 'error': $badge_class = 'danger'; break;
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                                            <?php echo strtoupper($activity['log_level']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars(mb_substr($activity['log_message'], 0, 50)); ?>
                                                        <?php if (mb_strlen($activity['log_message']) > 50): ?>...<?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($activity['user_action'] ?? '-'); ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// رسم بياني لنمو المستأجرين
const growthData = <?php echo json_encode($growth_data); ?>;
const growthCtx = document.getElementById('growthChart').getContext('2d');

new Chart(growthCtx, {
    type: 'line',
    data: {
        labels: growthData.map(item => item.month),
        datasets: [{
            label: 'مستأجرين جدد',
            data: growthData.map(item => item.count),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// رسم بياني لأنواع الاشتراكات
const subscriptionData = <?php echo json_encode($subscription_stats); ?>;
const subscriptionCtx = document.getElementById('subscriptionChart').getContext('2d');

new Chart(subscriptionCtx, {
    type: 'doughnut',
    data: {
        labels: subscriptionData.map(item => item.plan),
        datasets: [{
            data: subscriptionData.map(item => item.count),
            backgroundColor: [
                '#FF6384',
                '#36A2EB',
                '#FFCE56',
                '#4BC0C0',
                '#9966FF'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// رسم بياني لحالات الاشتراكات
const statusData = <?php echo json_encode($status_distribution); ?>;
const statusCtx = document.getElementById('statusChart').getContext('2d');

new Chart(statusCtx, {
    type: 'bar',
    data: {
        labels: statusData.map(item => item.status),
        datasets: [{
            label: 'عدد الاشتراكات',
            data: statusData.map(item => item.count),
            backgroundColor: [
                '#28a745',
                '#dc3545',
                '#ffc107',
                '#6c757d'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// دالة تصدير التحليلات
function exportAnalytics() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'true');
    window.location.href = 'analytics.php?' + params.toString();
}
</script>

<?php include 'includes/footer.php'; ?>