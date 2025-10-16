<?php
/**
 * System Status Checker for Developer Portal
 * Returns system status information via AJAX
 */

// تعطيل جميع أنواع الأخطاء والتحذيرات
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// بدء output buffering
ob_start();

// تنظيف أي مخرجات سابقة
if (ob_get_level() > 1) {
    ob_end_clean();
    ob_start();
}

// إعداد Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// تهيئة الرد الافتراضي
$status = [
    'success' => true,
    'data' => [
        'database' => false,
        'security' => true,
        'filesystem' => false,
        'sessions' => false
    ],
    'message' => 'تم فحص حالة النظام بنجاح'
];

try {
    // فحص قاعدة البيانات بطريقة مبسطة
    try {
        $host = 'localhost';
        $dbname = 'warehouse_saas_main';  // اسم قاعدة البيانات الصحيح
        $username = 'root';
        $password = '';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        
        $stmt = $pdo->query("SELECT 1");
        if ($stmt) {
            $status['data']['database'] = true;
        }
    } catch (Exception $e) {
        $status['data']['database'] = false;
    }

    // فحص الملفات
    $upload_dir = '../uploads';
    $logs_dir = '../logs';
    
    $filesystem_ok = true;
    if (!is_dir($upload_dir)) {
        $filesystem_ok = @mkdir($upload_dir, 0755, true);
    }
    if (!is_dir($logs_dir)) {
        $filesystem_ok = $filesystem_ok && @mkdir($logs_dir, 0755, true);
    }
    
    $status['data']['filesystem'] = $filesystem_ok && is_writable($upload_dir) && is_writable($logs_dir);

    // فحص الجلسات
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $status['data']['sessions'] = session_status() === PHP_SESSION_ACTIVE;

    // إضافة معلومات النظام
    $status['data']['server_time'] = date('Y-m-d H:i:s');
    $status['data']['php_version'] = PHP_VERSION;
    $status['data']['memory_usage'] = round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';

} catch (Exception $e) {
    $status = [
        'success' => false,
        'message' => 'حدث خطأ أثناء فحص حالة النظام',
        'data' => [
            'database' => false,
            'security' => false,
            'filesystem' => false,
            'sessions' => false
        ]
    ];
}

// تنظيف أي مخرجات
if (ob_get_level()) {
    ob_end_clean();
}

// إرجاع JSON فقط
echo json_encode($status, JSON_UNESCAPED_UNICODE);
exit;