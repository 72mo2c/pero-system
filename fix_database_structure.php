<?php
/**
 * إصلاح سريع لمشكلة العمود is_active
 * Quick fix for is_active column issue
 * Created: 2025-10-16
 */

echo "<h2>إصلاح مشكلة بنية قاعدة البيانات</h2>";
echo "<p>سيتم حذف الجداول الخاطئة وإعادة إنشائها بالبنية الصحيحة</p>";

try {
    // Configuration
    $config = [
        'host' => 'localhost',
        'username' => 'root', 
        'password' => '',
        'database' => 'warehouse_saas_main',
        'charset' => 'utf8mb4'
    ];
    
    // Connect to database
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✓ تم الاتصال بقاعدة البيانات</p>";
    
    // Temporarily disable foreign key checks to allow table recreation
    echo "<p>جاري تعطيل فحص المفاتيح الخارجية مؤقتاً...</p>";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "<p>✓ تم تعطيل فحص المفاتيح الخارجية</p>";
    
    // Drop existing tenants table if it has wrong structure
    echo "<p>جاري حذف الجدول القديم...</p>";
    $pdo->exec("DROP TABLE IF EXISTS tenants");
    echo "<p>✓ تم حذف الجدول القديم</p>";
    
    // Recreate tenants table with correct structure
    echo "<p>جاري إنشاء الجدول بالبنية الصحيحة...</p>";
    $pdo->exec("
        CREATE TABLE 'tenants' (
            'id' int(11) NOT NULL AUTO_INCREMENT,
            'tenant_id' varchar(50) NOT NULL,
            'company_name' varchar(255) NOT NULL,
            'company_name_en' varchar(255),
            'contact_person' varchar(255) NOT NULL,
            'email' varchar(255) NOT NULL,
            'phone' varchar(20),
            'address' text,
            'city' varchar(100),
            'country' varchar(100) DEFAULT 'Saudi Arabia',
            'tax_number' varchar(50),
            'commercial_registration' varchar(50),
            'subscription_plan' enum('basic', 'professional', 'enterprise') DEFAULT 'basic',
            'subscription_start' date NOT NULL,
            'subscription_end' date NOT NULL,
            'max_users' int(11) DEFAULT 5,
            'max_warehouses' int(11) DEFAULT 3,
            'database_name' varchar(100) NOT NULL,
            'status' enum('pending', 'active', 'suspended', 'cancelled') DEFAULT 'pending',
            'is_trial' tinyint(1) DEFAULT 0,
            'trial_days_remaining' int(11) DEFAULT 0,
            'disk_space_limit_mb' int(11) DEFAULT 1000,
            'monthly_transactions_limit' int(11) DEFAULT 1000,
            'api_access' tinyint(1) DEFAULT 0,
            'custom_domain' varchar(255),
            'logo_path' varchar(500),
            'theme_settings' json,
            'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
            'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            'created_by' int(11),
            PRIMARY KEY ('id'),
            UNIQUE KEY 'tenant_id' ('tenant_id'),
            UNIQUE KEY 'database_name' ('database_name'),
            UNIQUE KEY 'email' ('email'),
            KEY 'status' ('status'),
            KEY 'subscription_plan' ('subscription_plan')
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p>✓ تم إنشاء الجدول بنجاح</p>";
    
    // Add sample data
    echo "<p>جاري إضافة البيانات التجريبية...</p>";
    $stmt = $pdo->prepare("
        INSERT INTO tenants (tenant_id, company_name, contact_person, email, subscription_plan, subscription_start, subscription_end, database_name, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $sample_tenants = [
        [
            'demo_company',
            'الشركة التجريبية الأولى',
            'أحمد محمد علي',
            'demo1@example.com',
            'basic',
            date('Y-m-d'),
            date('Y-m-d', strtotime('+1 year')),
            'warehouse_tenant_demo_company',
            'active'
        ],
        [
            'test_company',
            'شركة الاختبار',
            'فاطمة أحمد',
            'test@example.com',
            'professional',
            date('Y-m-d'),
            date('Y-m-d', strtotime('+1 year')),
            'warehouse_tenant_test_company',
            'active'
        ],
        [
            'sample_corp',
            'الشركة النموذجية',
            'محمد السعيد',
            'sample@example.com',
            'enterprise',
            date('Y-m-d'),
            date('Y-m-d', strtotime('+2 years')),
            'warehouse_tenant_sample_corp',
            'pending'
        ]
    ];
    
    foreach ($sample_tenants as $tenant) {
        $stmt->execute($tenant);
    }
    
    echo "<p>✓ تم إضافة " . count($sample_tenants) . " شركات تجريبية</p>";
    
    // Re-enable foreign key checks
    echo "<p>جاري إعادة تفعيل فحص المفاتيح الخارجية...</p>";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<p>✓ تم إعادة تفعيل فحص المفاتيح الخارجية</p>";
    
    echo "<hr>";
    echo "<h3 style='color: green;'>✅ تم إصلاح المشكلة بنجاح!</h3>";
    echo "<p><strong>التغييرات:</strong></p>";
    echo "<ul>";
    echo "<li>تم تعطيل فحص المفاتيح الخارجية مؤقتاً لحل مشكلة القيود</li>";
    echo "<li>تم حذف الجدول القديم ذو البنية الخاطئة</li>";
    echo "<li>تم إنشاء جدول جديد بالبنية الصحيحة (status بدلاً من is_active/is_approved)</li>";
    echo "<li>تم إضافة بيانات تجريبية للاختبار</li>";
    echo "<li>تم إعادة تفعيل فحص المفاتيح الخارجية</li>";
    echo "</ul>";
    
    echo "<p><a href='developer_portal/tenants.php' target='_blank'>اختبر صفحة إدارة الشركات الآن</a></p>";
    echo "<p><a href='developer_portal/' target='_blank'>انتقل إلى لوحة المطور</a></p>";
    
} catch (Exception $e) {
    // Re-enable foreign key checks in case of error
    try {
        if (isset($pdo)) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        }
    } catch (Exception $ignored) {
        // If PDO failed, we might not have a connection
    }
    
    echo "<p style='color: red;'><strong>خطأ:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>تأكد من:</strong></p>";
    echo "<ul>";
    echo "<li>تشغيل خادم MySQL</li>";
    echo "<li>وجود قاعدة البيانات warehouse_saas_main</li>";
    echo "<li>صحة بيانات الاتصال</li>";
    echo "<li>عدم وجود مستخدمين متصلين بالجداول المطلوب تعديلها</li>";
    echo "</ul>";
}
?>