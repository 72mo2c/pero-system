<?php
/**
 * Developer Portal Sidebar
 * Navigation menu for developers
 * Created: 2025-10-16
 */

if (!defined('ROOT_PATH')) {
    die('Direct access not allowed');
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-code-branch"></i>
        </div>
        <h4>لوحة المطور</h4>
        <p class="subtitle">نظام إدارة المخازن</p>
    </div>
    
    <div class="sidebar-menu">
        <!-- Dashboard -->
        <div class="menu-title">الرئيسية</div>
        <div class="menu-item">
            <a href="dashboard.php" class="menu-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>لوحة التحكم</span>
            </a>
        </div>
        
        <!-- Tenant Management -->
        <div class="menu-title">إدارة الشركات</div>
        <div class="menu-item">
            <a href="tenants.php" class="menu-link <?php echo $current_page === 'tenants' ? 'active' : ''; ?>">
                <i class="fas fa-building"></i>
                <span>الشركات</span>
                <?php 
                try {
                    $pdo = DatabaseConfig::getMainConnection();
                    $stmt = $pdo->query("SELECT COUNT(*) FROM tenants WHERE is_approved = 0");
                    $pending_count = $stmt->fetchColumn();
                    if ($pending_count > 0) {
                        echo '<span class="badge bg-warning rounded-pill">' . $pending_count . '</span>';
                    }
                } catch (Exception $e) {
                    // Ignore error
                }
                ?>
            </a>
        </div>
        
        <div class="menu-item">
            <a href="subscriptions.php" class="menu-link <?php echo $current_page === 'subscriptions' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i>
                <span>الاشتراكات</span>
            </a>
        </div>
        
        <!-- System Management -->
        <div class="menu-title">إدارة النظام</div>
        <div class="menu-item">
            <a href="backups.php" class="menu-link <?php echo $current_page === 'backups' ? 'active' : ''; ?>">
                <i class="fas fa-database"></i>
                <span>النسخ الاحتياطية</span>
            </a>
        </div>
        
        <div class="menu-item">
            <a href="updates.php" class="menu-link <?php echo $current_page === 'updates' ? 'active' : ''; ?>">
                <i class="fas fa-sync-alt"></i>
                <span>التحديثات</span>
            </a>
        </div>
        
        <div class="menu-item">
            <a href="logs.php" class="menu-link <?php echo $current_page === 'logs' ? 'active' : ''; ?>">
                <i class="fas fa-list-alt"></i>
                <span>سجلات النظام</span>
                <?php 
                try {
                    $pdo = DatabaseConfig::getMainConnection();
                    $stmt = $pdo->query("SELECT COUNT(*) FROM system_error_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                    $error_count = $stmt->fetchColumn();
                    if ($error_count > 0) {
                        echo '<span class="badge bg-danger rounded-pill">' . $error_count . '</span>';
                    }
                } catch (Exception $e) {
                    // Ignore error
                }
                ?>
            </a>
        </div>
        
        <!-- Analytics -->
        <div class="menu-title">التحليلات</div>
        <div class="menu-item">
            <a href="analytics.php" class="menu-link <?php echo $current_page === 'analytics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>تحليلات النظام</span>
            </a>
        </div>
        
        <div class="menu-item">
            <a href="reports.php" class="menu-link <?php echo $current_page === 'reports' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>التقارير</span>
            </a>
        </div>
        
        <!-- Settings -->
        <div class="menu-title">الإعدادات</div>
        <div class="menu-item">
            <a href="settings.php" class="menu-link <?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>إعدادات النظام</span>
            </a>
        </div>
        
        <div class="menu-item">
            <a href="profile.php" class="menu-link <?php echo $current_page === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i>
                <span>ملفي الشخصي</span>
            </a>
        </div>
        
        <!-- Logout -->
        <div class="menu-title">أخرى</div>
        <div class="menu-item">
            <a href="#" class="menu-link" onclick="logout()">
                <i class="fas fa-sign-out-alt"></i>
                <span>تسجيل الخروج</span>
            </a>
        </div>
    </div>
</div>

<script>
function logout() {
    if (confirm('هل أنت متأكد من أنك تريد تسجيل الخروج؟')) {
        window.location.href = 'logout.php';
    }
}
</script>