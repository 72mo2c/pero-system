<?php
/**
 * Developer Portal Top Navigation Bar
 * Created: 2025-10-16
 */

if (!defined('ROOT_PATH')) {
    die('Direct access not allowed');
}

// Get developer info
$developer_name = Session::get('developer_full_name', Session::get('developer_username', 'مطور'));
$developer_email = Session::get('developer_email', '');

// Get notifications count
try {
    $pdo = DatabaseConfig::getMainConnection();
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM system_notifications 
        WHERE is_read = 0 AND (tenant_id IS NULL OR is_system_wide = 1)
    ");
    $notifications_count = $stmt->fetchColumn();
} catch (Exception $e) {
    $notifications_count = 0;
}
?>

<div class="top-navbar">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <!-- Sidebar Toggle -->
            <button class="btn btn-link text-muted p-0 me-3" onclick="toggleSidebar()" title="قائمة التنقل">
                <i class="fas fa-bars fa-lg"></i>
            </button>
            
            <!-- Mobile Sidebar Toggle -->
            <button class="btn btn-link text-muted p-0 me-3 d-md-none" onclick="toggleMobileSidebar()" title="قائمة التنقل">
                <i class="fas fa-bars fa-lg"></i>
            </button>
            
            <!-- Breadcrumb Location -->
            <div class="d-none d-md-block">
                <span class="text-muted">لوحة المطور</span>
                <span class="text-primary mx-2">/</span>
                <span class="fw-bold"><?php echo $page_title ?? 'لوحة التحكم'; ?></span>
            </div>
        </div>
        
        <div class="d-flex align-items-center">
            <!-- Quick Actions -->
            <div class="dropdown me-3">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>إجراءات سريعة
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="tenants.php?action=add">
                        <i class="fas fa-building me-2"></i>إضافة شركة
                    </a></li>
                    <li><a class="dropdown-item" href="backups.php?action=create">
                        <i class="fas fa-database me-2"></i>إنشاء نسخة احتياطية
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="settings.php">
                        <i class="fas fa-cog me-2"></i>إعدادات النظام
                    </a></li>
                </ul>
            </div>
            
            <!-- Notifications -->
            <div class="dropdown me-3">
                <button class="btn btn-link text-muted p-0 position-relative" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-bell fa-lg"></i>
                    <?php if ($notifications_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                            <?php echo $notifications_count > 99 ? '99+' : $notifications_count; ?>
                        </span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end" style="width: 350px;">
                    <div class="dropdown-header d-flex justify-content-between align-items-center">
                        <span>الإشعارات</span>
                        <?php if ($notifications_count > 0): ?>
                            <span class="badge bg-primary rounded-pill"><?php echo $notifications_count; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($notifications_count > 0): ?>
                        <div class="dropdown-divider"></div>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php
                            try {
                                $stmt = $pdo->query("
                                    SELECT title, message, notification_type, created_at
                                    FROM system_notifications 
                                    WHERE is_read = 0 AND (tenant_id IS NULL OR is_system_wide = 1)
                                    ORDER BY created_at DESC 
                                    LIMIT 5
                                ");
                                $notifications = $stmt->fetchAll();
                                
                                foreach ($notifications as $notification):
                            ?>
                                <div class="dropdown-item-text border-bottom py-2">
                                    <div class="d-flex align-items-start">
                                        <div class="me-2">
                                            <i class="fas fa-<?php 
                                                echo $notification['notification_type'] === 'error' ? 'exclamation-triangle text-danger' :
                                                     ($notification['notification_type'] === 'warning' ? 'exclamation-circle text-warning' :
                                                      ($notification['notification_type'] === 'success' ? 'check-circle text-success' : 'info-circle text-info'));
                                            ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold small"><?php echo htmlspecialchars($notification['title']); ?></div>
                                            <div class="text-muted small"><?php echo Utils::truncateText($notification['message'], 60); ?></div>
                                            <div class="text-muted" style="font-size: 0.7rem;">
                                                <?php echo Utils::timeAgo($notification['created_at']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                endforeach;
                            } catch (Exception $e) {
                                echo '<div class="dropdown-item-text text-muted">خطأ في تحميل الإشعارات</div>';
                            }
                            ?>
                        </div>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-item text-center">
                            <a href="notifications.php" class="text-decoration-none">عرض جميع الإشعارات</a>
                        </div>
                    <?php else: ?>
                        <div class="dropdown-item-text text-center text-muted py-3">
                            <i class="fas fa-bell-slash fa-2x mb-2 d-block"></i>
                            لا توجد إشعارات جديدة
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- System Status Indicator -->
            <div class="me-3">
                <span class="badge bg-success rounded-pill" title="حالة النظام: فعال">
                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                </span>
            </div>
            
            <!-- Developer Profile Dropdown -->
            <div class="dropdown">
                <button class="btn btn-link text-decoration-none p-0 d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                    <div class="me-2 text-end d-none d-md-block">
                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($developer_name); ?></div>
                        <div class="small text-muted">مطور النظام</div>
                    </div>
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-user-tie text-white"></i>
                    </div>
                    <i class="fas fa-chevron-down text-muted ms-2 small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="dropdown-header">
                        <div class="fw-bold"><?php echo htmlspecialchars($developer_name); ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars($developer_email); ?></div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="profile.php">
                        <i class="fas fa-user-cog me-2"></i>ملفي الشخصي
                    </a></li>
                    <li><a class="dropdown-item" href="settings.php">
                        <i class="fas fa-cog me-2"></i>إعدادات النظام
                    </a></li>
                    <li><a class="dropdown-item" href="../warehouse_system/" target="_blank">
                        <i class="fas fa-external-link-alt me-2"></i>عرض النظام الرئيسي
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="logout()">
                        <i class="fas fa-sign-out-alt me-2"></i>تسجيل الخروج
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Real-time updates indicator -->
<div id="liveIndicator" class="position-fixed" style="top: 10px; left: 10px; z-index: 1100; display: none;">
    <span class="badge bg-success">
        <i class="fas fa-circle" style="font-size: 0.5rem; animation: pulse 1s infinite;"></i>
        متصل
    </span>
</div>

<style>
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}
</style>

<script>
// Show live indicator periodically
setInterval(function() {
    const indicator = document.getElementById('liveIndicator');
    indicator.style.display = 'block';
    setTimeout(function() {
        indicator.style.display = 'none';
    }, 2000);
}, 30000); // Show every 30 seconds
</script>