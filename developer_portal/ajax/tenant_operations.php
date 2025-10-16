<?php
/**
 * AJAX handler for tenant operations
 * معالج AJAX لعمليات المستأجرين
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
        case 'get_tenant_details':
            $tenant_id = $_POST['tenant_id'] ?? $_GET['tenant_id'] ?? '';
            
            if (empty($tenant_id)) {
                throw new Exception('معرف المستأجر مطلوب');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
            $stmt->execute([$tenant_id]);
            $tenant = $stmt->fetch();
            
            if (!$tenant) {
                throw new Exception('المستأجر غير موجود');
            }
            
            $response['success'] = true;
            $response['data'] = $tenant;
            $response['message'] = 'تم جلب بيانات المستأجر بنجاح';
            break;
            
        case 'toggle_tenant_status':
            $tenant_id = $_POST['tenant_id'] ?? '';
            $new_status = $_POST['status'] ?? '';
            
            if (empty($tenant_id) || empty($new_status)) {
                throw new Exception('معرف المستأجر والحالة الجديدة مطلوبان');
            }
            
            $allowed_statuses = ['active', 'inactive', 'suspended', 'pending'];
            if (!in_array($new_status, $allowed_statuses)) {
                throw new Exception('الحالة غير صحيحة');
            }
            
            $stmt = $pdo->prepare("UPDATE tenants SET subscription_status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $tenant_id]);
            
            $response['success'] = true;
            $response['message'] = 'تم تحديث حالة المستأجر بنجاح';
            break;
            
        case 'delete_tenant':
            $tenant_id = $_POST['tenant_id'] ?? '';
            
            if (empty($tenant_id)) {
                throw new Exception('معرف المستأجر مطلوب');
            }
            
            // التحقق من وجود المستأجر
            $stmt = $pdo->prepare("SELECT tenant_id FROM tenants WHERE id = ?");
            $stmt->execute([$tenant_id]);
            $tenant = $stmt->fetch();
            
            if (!$tenant) {
                throw new Exception('المستأجر غير موجود');
            }
            
            // حذف قاعدة بيانات المستأجر (اختياري)
            // $database_name = "tenant_" . $tenant['tenant_id'];
            // $pdo->exec("DROP DATABASE IF EXISTS '$database_name'");
            
            // حذف المستأجر من قاعدة البيانات الرئيسية
            $stmt = $pdo->prepare("DELETE FROM tenants WHERE id = ?");
            $stmt->execute([$tenant_id]);
            
            $response['success'] = true;
            $response['message'] = 'تم حذف المستأجر بنجاح';
            break;
            
        case 'extend_subscription':
            $tenant_id = $_POST['tenant_id'] ?? '';
            $extension_days = (int)($_POST['extension_days'] ?? 30);
            
            if (empty($tenant_id)) {
                throw new Exception('معرف المستأجر مطلوب');
            }
            
            if ($extension_days <= 0 || $extension_days > 365) {
                throw new Exception('عدد الأيام يجب أن يكون بين 1 و 365');
            }
            
            $stmt = $pdo->prepare("
                UPDATE tenants 
                SET subscription_end = DATE_ADD(COALESCE(subscription_end, CURDATE()), INTERVAL ? DAY),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$extension_days, $tenant_id]);
            
            $response['success'] = true;
            $response['message'] = "تم تمديد الاشتراك لـ $extension_days يوم";
            break;
            
        case 'search_tenants':
            $search_term = $_POST['search'] ?? $_GET['search'] ?? '';
            $limit = (int)($_POST['limit'] ?? $_GET['limit'] ?? 10);
            
            if (empty($search_term)) {
                throw new Exception('مصطلح البحث مطلوب');
            }
            
            $search = '%' . $search_term . '%';
            $stmt = $pdo->prepare("
                SELECT id, tenant_id, company_name, email, subscription_status 
                FROM tenants 
                WHERE company_name LIKE ? OR email LIKE ? OR tenant_id LIKE ?
                ORDER BY company_name ASC
                LIMIT ?
            ");
            $stmt->execute([$search, $search, $search, $limit]);
            $tenants = $stmt->fetchAll();
            
            $response['success'] = true;
            $response['data'] = $tenants;
            $response['message'] = 'تم البحث بنجاح';
            break;
            
        case 'get_tenant_stats':
            $tenant_id = $_POST['tenant_id'] ?? $_GET['tenant_id'] ?? '';
            
            if (empty($tenant_id)) {
                throw new Exception('معرف المستأجر مطلوب');
            }
            
            // في تطبيق حقيقي، ستحتاج للاتصال بقاعدة بيانات المستأجر
            $stats = [
                'database_size' => '2.5 MB',
                'last_activity' => date('Y-m-d H:i:s'),
                'user_count' => rand(5, 50),
                'product_count' => rand(100, 1000),
                'transaction_count' => rand(50, 500)
            ];
            
            $response['success'] = true;
            $response['data'] = $stats;
            $response['message'] = 'تم جلب إحصائيات المستأجر بنجاح';
            break;
            
        default:
            throw new Exception('العملية غير مدعومة');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // تسجيل الخطأ
    error_log('Tenant AJAX Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>