<?php
/**
 * Database Check and Setup Script
 * تحقق من حالة قاعدة البيانات وإعدادها
 * Created: 2025-10-16
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';

echo "<h2>فحص حالة قاعدة البيانات</h2>";

try {
    // Test main database connection
    echo "<h3>1. اختبار الاتصال بقاعدة البيانات الرئيسية:</h3>";
    $pdo = DatabaseConfig::getMainConnection();
    echo "<p style='color: green;'>✓ تم الاتصال بقاعدة البيانات بنجاح</p>";
    
    // Check if tables exist
    echo "<h3>2. التحقق من وجود الجداول:</h3>";
    
    $tables = ['system_settings', 'developer_accounts', 'tenants', 'subscriptions', 'system_logs'];
    $missing_tables = [];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✓ جدول $table موجود</p>";
        } else {
            echo "<p style='color: red;'>✗ جدول $table غير موجود</p>";
            $missing_tables[] = $table;
        }
    }
    
    // Check tenants table specifically
    if (!in_array('tenants', $missing_tables)) {
        echo "<h3>3. فحص جدول الشركات:</h3>";
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tenants");
        $count = $stmt->fetch()['count'];
        echo "<p>عدد الشركات المسجلة: $count</p>";
        
        if ($count > 0) {
            $stmt = $pdo->query("SELECT tenant_id, company_name, is_active FROM tenants LIMIT 5");
            $tenants = $stmt->fetchAll();
            echo "<p>آخر 5 شركات:</p><ul>";
            foreach ($tenants as $tenant) {
                $status = $tenant['is_active'] ? 'نشط' : 'غير نشط';
                echo "<li>{$tenant['tenant_id']} - {$tenant['company_name']} ($status)</li>";
            }
            echo "</ul>";
        }
    }
    
    // Create database structure if needed
    if (!empty($missing_tables)) {
        echo "<h3>4. إنشاء الجداول المفقودة:</h3>";
        echo "<p style='color: orange;'>جاري إنشاء الجداول المفقودة...</p>";
        
        $sql_file = __DIR__ . '/database/main_structure.sql';
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            // Remove USE database command as we're already connected
            $sql = preg_replace('/USE\s+'warehouse_saas_main'\s*;/i', '', $sql);
            
            $statements = explode(';', $sql);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    try {
                        $pdo->exec($statement);
                    } catch (Exception $e) {
                        // Ignore errors for existing tables
                        if (!strpos($e->getMessage(), 'already exists')) {
                            echo "<p style='color: red;'>خطأ في تنفيذ الاستعلام: " . $e->getMessage() . "</p>";
                        }
                    }
                }
            }
            echo "<p style='color: green;'>✓ تم إنشاء الجداول بنجاح</p>";
        } else {
            echo "<p style='color: red;'>✗ ملف SQL غير موجود: $sql_file</p>";
        }
    }
    
    // Test creating a sample tenant
    echo "<h3>5. اختبار إضافة بيانات تجريبية:</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE tenant_id = 'test_company'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("
            INSERT INTO tenants (tenant_id, company_name, contact_person, email, subscription_plan, subscription_start, subscription_end, database_name, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'test_company',
            'شركة تجريبية',
            'مدير الشركة',
            'test@example.com',
            'basic',
            date('Y-m-d'),
            date('Y-m-d', strtotime('+1 year')),
            'warehouse_tenant_test_company',
            'active'
        ]);
        echo "<p style='color: green;'>✓ تم إضافة شركة تجريبية بنجاح</p>";
    } else {
        echo "<p style='color: blue;'>ℹ شركة تجريبية موجودة مسبقاً</p>";
    }
    
    // Test the developer account
    echo "<h3>6. التحقق من حساب المطور:</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM developer_accounts WHERE username = 'admin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $password_hash = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO developer_accounts (username, email, password_hash, full_name, is_active) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'admin',
            'admin@example.com',
            $password_hash,
            'مدير النظام',
            1
        ]);
        echo "<p style='color: green;'>✓ تم إنشاء حساب المطور (admin/admin123)</p>";
    } else {
        echo "<p style='color: blue;'>ℹ حساب المطور موجود مسبقاً</p>";
    }
    
    echo "<hr><h3>النتيجة النهائية:</h3>";
    echo "<p style='color: green; font-weight: bold;'>✓ قاعدة البيانات جاهزة للاستخدام!</p>";
    echo "<p><a href='developer_portal/'>انتقل إلى لوحة المطور</a> | <a href='developer_portal/tenants.php'>إدارة الشركات</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>خطأ:</strong> " . $e->getMessage() . "</p>";
    echo "<h3>خطوات الحل:</h3>";
    echo "<ol>";
    echo "<li>تأكد من تشغيل خادم MySQL</li>";
    echo "<li>تأكد من صحة بيانات الاتصال في config/database.php</li>";
    echo "<li>تأكد من وجود صلاحيات إنشاء قواعد البيانات</li>";
    echo "</ol>";
}
?>