<?php
/**
 * Developer Portal Dashboard
 * Main dashboard for developers
 * Created: 2025-10-16
 */

require_once '../config/config.php';
require_once 'includes/auth_check.php';

// Get dashboard statistics
try {
    $pdo = DatabaseConfig::getMainConnection();
    
    // Get tenants statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_tenants,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_tenants,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tenants,
            SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_tenants
        FROM tenants
    ");
    $tenant_stats = $stmt->fetch();
    
    // Get subscription statistics
    $stmt = $pdo->query("
        SELECT 
            subscription_plan,
            COUNT(*) as count
        FROM tenants 
        WHERE is_active = 1
        GROUP BY subscription_plan
    ");
    $subscription_stats = $stmt->fetchAll();
    
    // Get recent tenants
    $stmt = $pdo->query("
        SELECT 
            tenant_id, company_name, contact_person, email, 
            subscription_plan, is_active, is_approved, created_at
        FROM tenants 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recent_tenants = $stmt->fetchAll();
    
    // Get system activity logs
    $stmt = $pdo->query("
        SELECT 
            user_type, action, description, ip_address, created_at
        FROM system_activity_logs 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $recent_activities = $stmt->fetchAll();
    
    // Get error logs count
    $stmt = $pdo->query("
        SELECT COUNT(*) as error_count
        FROM system_error_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $recent_errors = $stmt->fetchColumn();
    
    // Get backup statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_backups,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_backups,
            MAX(created_at) as last_backup
        FROM system_backups
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $backup_stats = $stmt->fetch();
    
} catch (Exception $e) {
    error_log('Dashboard stats error: ' . $e->getMessage());
    $tenant_stats = ['total_tenants' => 0, 'active_tenants' => 0, 'pending_tenants' => 0, 'suspended_tenants' => 0];
    $subscription_stats = [];
    $recent_tenants = [];
    $recent_activities = [];
    $recent_errors = 0;
    $backup_stats = ['total_backups' => 0, 'successful_backups' => 0, 'last_backup' => null];
}

$page_title = 'لوحة تحكم المطور';
include 'includes/header.php';
?>

<div class="main-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-tachometer-alt me-3"></i>لوحة تحكم المطور</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item active">لوحة تحكم المطور</li>
                    </ol>
                </nav>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon bg-primary me-3">
                                <i class="fas fa-building"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo number_format($tenant_stats['total_tenants']); ?></h3>
                                <p class="text-muted mb-0">إجمالي الشركات</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon bg-success me-3">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo number_format($tenant_stats['active_tenants']); ?></h3>
                                <p class="text-muted mb-0">شركات نشطة</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon bg-warning me-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo number_format($tenant_stats['pending_tenants']); ?></h3>
                                <p class="text-muted mb-0">بانتظار الموافقة</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="stats-icon bg-danger me-3">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo number_format($recent_errors); ?></h3>
                                <p class="text-muted mb-0">أخطاء (24 ساعة)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Subscription Distribution Chart -->
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>توزيع الاشتراكات
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="subscriptionChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- System Status -->
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-server me-2"></i>حالة النظام
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="badge bg-success rounded-pill me-2"></div>
                                        <div>
                                            <div class="fw-bold">حالة الخادم</div>
                                            <div class="text-success small">فعال</div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="badge bg-success rounded-pill me-2"></div>
                                        <div>
                                            <div class="fw-bold">قاعدة البيانات</div>
                                            <div class="text-success small">متصلة</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="badge bg-info rounded-pill me-2"></div>
                                        <div>
                                            <div class="fw-bold">النسخ الاحتياطي</div>
                                            <div class="text-info small">
                                                <?php echo $backup_stats['successful_backups']; ?> نجح
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="badge bg-<?php echo $recent_errors > 0 ? 'danger' : 'success'; ?> rounded-pill me-2"></div>
                                        <div>
                                            <div class="fw-bold">الأخطاء</div>
                                            <div class="text-<?php echo $recent_errors > 0 ? 'danger' : 'success'; ?> small">
                                                <?php echo $recent_errors; ?> خطأ
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <small class="text-muted">
                                    آخر نسخة احتياطية: 
                                    <?php 
                                    if ($backup_stats['last_backup']) {
                                        echo Utils::formatDateTime($backup_stats['last_backup']);
                                    } else {
                                        echo 'لا توجد';
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Recent Tenants -->
                <div class="col-xl-7">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-users me-2"></i>الشركات الجديدة
                            </h5>
                            <a href="tenants.php" class="btn btn-sm btn-outline-primary">
                                عرض الكل <i class="fas fa-arrow-left ms-1"></i>
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>اسم الشركة</th>
                                            <th>البريد الإلكتروني</th>
                                            <th>الباقة</th>
                                            <th>الحالة</th>
                                            <th>تاريخ التسجيل</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_tenants)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    <i class="fas fa-info-circle me-2"></i>لا توجد شركات مسجلة
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_tenants as $tenant): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($tenant['company_name']); ?></div>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($tenant['contact_person']); ?></div>
                                                    </td>
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
                                                    <td>
                                                        <?php if (!$tenant['is_approved']): ?>
                                                            <span class="badge bg-warning">بالانتظار</span>
                                                        <?php elseif ($tenant['is_active']): ?>
                                                            <span class="badge bg-success">نشط</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">موقف</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="small text-muted">
                                                        <?php echo Utils::formatDateTime($tenant['created_at']); ?>
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
                
                <!-- Recent Activities -->
                <div class="col-xl-5">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>آخر الأنشطة
                            </h5>
                            <a href="logs.php" class="btn btn-sm btn-outline-primary">
                                عرض الكل <i class="fas fa-arrow-left ms-1"></i>
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <div class="activity-list" style="max-height: 400px; overflow-y: auto;">
                                <?php if (empty($recent_activities)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle me-2"></i>لا توجد أنشطة
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="d-flex align-items-start p-3 border-bottom">
                                            <div class="me-3">
                                                <div class="bg-<?php 
                                                    echo $activity['action'] === 'login' ? 'success' : 
                                                         ($activity['action'] === 'logout' ? 'warning' : 'info');
                                                ?> rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 35px; height: 35px;">
                                                    <i class="fas fa-<?php 
                                                        echo $activity['action'] === 'login' ? 'sign-in-alt' : 
                                                             ($activity['action'] === 'logout' ? 'sign-out-alt' : 'cog');
                                                    ?> text-white small"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-bold small">
                                                    <?php echo htmlspecialchars($activity['description']); ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?php echo Utils::timeAgo($activity['created_at']); ?> | 
                                                    <?php echo htmlspecialchars($activity['ip_address']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Subscription distribution chart
const subscriptionData = <?php echo json_encode($subscription_stats); ?>;
const ctx = document.getElementById('subscriptionChart').getContext('2d');

const planLabels = {
    'basic': 'أساسي',
    'professional': 'محترف',
    'enterprise': 'مؤسسي'
};

const chartData = {
    labels: subscriptionData.map(item => planLabels[item.subscription_plan] || item.subscription_plan),
    datasets: [{
        data: subscriptionData.map(item => item.count),
        backgroundColor: ['#6c757d', '#28a745', '#007bff'],
        borderWidth: 0
    }]
};

new Chart(ctx, {
    type: 'doughnut',
    data: chartData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Auto-refresh dashboard every 5 minutes
setInterval(function() {
    location.reload();
}, 300000);
</script>

<?php include 'includes/footer.php'; ?>