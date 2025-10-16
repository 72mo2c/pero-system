<?php
/**
 * AJAX handler for live statistics
 * معالج AJAX للإحصائيات المباشرة
 */

// التأكد من أن الطلب هو AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    exit('Access denied');
}

// منع الكاشينج
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-Type: application/json; charset=utf-8');

require_once '../config/config.php';
require_once '../shared/classes/Database.php';
require_once '../shared/classes/Security.php';

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'dashboard_stats':
            $stats = [];
            
            // إجمالي المستأجرين
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM tenants");
            $stats['total_tenants'] = $stmt->fetch()['total'];
            
            // المستأجرين النشطين
            $stmt = $pdo->query("SELECT COUNT(*) as active FROM tenants WHERE subscription_status = 'active'");
            $stats['active_tenants'] = $stmt->fetch()['active'];
            
            // المستأجرين الجدد اليوم
            $stmt = $pdo->query("SELECT COUNT(*) as new_today FROM tenants WHERE DATE(created_at) = CURDATE()");
            $stats['new_today'] = $stmt->fetch()['new_today'];
            
            // الاشتراكات المنتهية
            $stmt = $pdo->query("SELECT COUNT(*) as expired FROM tenants WHERE subscription_end < CURDATE()");
            $stats['expired_subscriptions'] = $stmt->fetch()['expired'];
            
            // الاشتراكات التي تنتهي خلال 7 أيام
            $stmt = $pdo->query("SELECT COUNT(*) as expiring_soon FROM tenants WHERE subscription_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
            $stats['expiring_soon'] = $stmt->fetch()['expiring_soon'];
            
            // حجم قاعدة البيانات
            $stmt = $pdo->query("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS database_size_mb
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE()
            ");
            $db_size = $stmt->fetch()['database_size_mb'];
            $stats['database_size'] = $db_size ? $db_size . ' MB' : 'غير متاح';
            
            // آخر المستأجرين المسجلين
            $stmt = $pdo->query("
                SELECT company_name, created_at 
                FROM tenants 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stats['recent_tenants'] = $stmt->fetchAll();
            
            $response['success'] = true;
            $response['data'] = $stats;
            $response['message'] = 'تم جلب الإحصائيات بنجاح';
            break;
            
        case 'subscription_distribution':
            $stmt = $pdo->query("
                SELECT 
                    subscription_plan,
                    COUNT(*) as count,
                    ROUND((COUNT(*) * 100.0) / (SELECT COUNT(*) FROM tenants), 2) as percentage
                FROM tenants 
                GROUP BY subscription_plan
                ORDER BY count DESC
            ");
            
            $distribution = $stmt->fetchAll();
            
            $response['success'] = true;
            $response['data'] = $distribution;
            $response['message'] = 'تم جلب توزيع الاشتراكات بنجاح';
            break;
            
        case 'monthly_growth':
            $months = (int)($_POST['months'] ?? $_GET['months'] ?? 6);
            
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as new_tenants,
                    SUM(COUNT(*)) OVER (ORDER BY DATE_FORMAT(created_at, '%Y-%m')) as cumulative
                FROM tenants 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month
            ");
            $stmt->execute([$months]);
            
            $growth = $stmt->fetchAll();
            
            $response['success'] = true;
            $response['data'] = $growth;
            $response['message'] = 'تم جلب بيانات النمو بنجاح';
            break;
            
        case 'revenue_estimation':
            $pricing = [
                'trial' => 0,
                'basic' => 99,
                'premium' => 199,
                'enterprise' => 399
            ];
            
            $stmt = $pdo->query("
                SELECT 
                    subscription_plan,
                    COUNT(*) as total_count,
                    SUM(CASE WHEN subscription_status = 'active' THEN 1 ELSE 0 END) as active_count
                FROM tenants 
                GROUP BY subscription_plan
            ");
            
            $revenue_data = [];
            $total_monthly = 0;
            $total_annual = 0;
            
            while ($row = $stmt->fetch()) {
                $plan = $row['subscription_plan'];
                $price = $pricing[$plan] ?? 0;
                $monthly_revenue = $row['active_count'] * $price;
                $annual_revenue = $monthly_revenue * 12;
                
                $revenue_data[] = [
                    'plan' => $plan,
                    'active_count' => $row['active_count'],
                    'price' => $price,
                    'monthly_revenue' => $monthly_revenue,
                    'annual_revenue' => $annual_revenue
                ];
                
                $total_monthly += $monthly_revenue;
                $total_annual += $annual_revenue;
            }
            
            $response['success'] = true;
            $response['data'] = [
                'plans' => $revenue_data,
                'total_monthly' => $total_monthly,
                'total_annual' => $total_annual
            ];
            $response['message'] = 'تم حساب الإيرادات المتوقعة بنجاح';
            break;
            
        case 'system_health':
            $health = [];
            
            // حالة قاعدة البيانات
            try {
                $stmt = $pdo->query("SELECT 1");
                $health['database'] = ['status' => 'healthy', 'message' => 'قاعدة البيانات تعمل بشكل طبيعي'];
            } catch (Exception $e) {
                $health['database'] = ['status' => 'error', 'message' => 'خطأ في قاعدة البيانات'];
            }
            
            // استخدام الذاكرة
            $memory_usage = memory_get_usage(true);
            $memory_peak = memory_get_peak_usage(true);
            $memory_limit = ini_get('memory_limit');
            
            $health['memory'] = [
                'current' => formatBytes($memory_usage),
                'peak' => formatBytes($memory_peak),
                'limit' => $memory_limit,
                'percentage' => round(($memory_usage / parseBytes($memory_limit)) * 100, 2)
            ];
            
            // مساحة القرص (تقديرية)
            $disk_free = disk_free_space('.');
            $disk_total = disk_total_space('.');
            
            if ($disk_free && $disk_total) {
                $disk_used = $disk_total - $disk_free;
                $disk_percentage = round(($disk_used / $disk_total) * 100, 2);
                
                $health['disk'] = [
                    'free' => formatBytes($disk_free),
                    'total' => formatBytes($disk_total),
                    'used' => formatBytes($disk_used),
                    'percentage' => $disk_percentage
                ];
            }
            
            // معلومات الخادم
            $health['server'] = [
                'php_version' => PHP_VERSION,
                'server_time' => date('Y-m-d H:i:s'),
                'uptime' => getSystemUptime()
            ];
            
            $response['success'] = true;
            $response['data'] = $health;
            $response['message'] = 'تم فحص صحة النظام بنجاح';
            break;
            
        case 'recent_activity':
            $limit = (int)($_POST['limit'] ?? $_GET['limit'] ?? 10);
            
            try {
                $stmt = $pdo->prepare("
                    SELECT log_level, log_message, created_at, user_action 
                    FROM system_logs 
                    ORDER BY created_at DESC 
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                $activities = $stmt->fetchAll();
                
                $response['success'] = true;
                $response['data'] = $activities;
                $response['message'] = 'تم جلب النشاط الأخير بنجاح';
            } catch (Exception $e) {
                // إذا لم يكن جدول السجلات موجوداً
                $response['success'] = true;
                $response['data'] = [];
                $response['message'] = 'لا توجد سجلات متاحة';
            }
            break;
            
        default:
            throw new Exception('العملية غير مدعومة');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // تسجيل الخطأ
    error_log('Statistics AJAX Error: ' . $e->getMessage());
}

// دوال مساعدة
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function parseBytes($size) {
    $size = trim($size);
    $last = strtolower($size[strlen($size)-1]);
    $size = (int) $size;
    
    switch($last) {
        case 'g': $size *= 1024;
        case 'm': $size *= 1024;
        case 'k': $size *= 1024;
    }
    
    return $size;
}

function getSystemUptime() {
    if (file_exists('/proc/uptime')) {
        $uptime_seconds = (int) file_get_contents('/proc/uptime');
        return gmdate("H:i:s", $uptime_seconds);
    }
    return 'غير متاح';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>