<?php
/**
 * Quick Database Setup
 * إعداد سريع لقاعدة البيانات
 * Created: 2025-10-16
 */

try {
    echo "<h2>إعداد قاعدة البيانات</h2>";
    
    // Configuration
    $config = [
        'host' => 'localhost',
        'username' => 'root', 
        'password' => '',
        'database' => 'warehouse_saas_main',
        'charset' => 'utf8mb4'
    ];
    
    // Connect without database first
    $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    echo "<p>جاري إنشاء قاعدة البيانات...</p>";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS '{$config['database']}' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✓ تم إنشاء قاعدة البيانات بنجاح</p>";
    
    // Select database
    $pdo->exec("USE '{$config['database']}'");
    
    // Create tables
    echo "<p>جاري إنشاء الجداول...</p>";
    
    // Tenants table - استخدام البنية الصحيحة من main_structure.sql
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS 'tenants' (
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
    
    // Developer accounts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS 'developer_accounts' (
            'id' int(11) NOT NULL AUTO_INCREMENT,
            'username' varchar(100) NOT NULL,
            'email' varchar(255) NOT NULL,
            'password_hash' varchar(255) NOT NULL,
            'full_name' varchar(255) NOT NULL,
            'phone' varchar(20),
            'is_active' tinyint(1) DEFAULT 1,
            'last_login' timestamp NULL,
            'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
            'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY ('id'),
            UNIQUE KEY 'username' ('username'),
            UNIQUE KEY 'email' ('email')
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // System logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS 'system_logs' (
            'id' int(11) NOT NULL AUTO_INCREMENT,
            'log_level' enum('info','warning','error','debug') NOT NULL DEFAULT 'info',
            'log_message' text NOT NULL,
            'user_action' varchar(255),
            'user_id' int(11),
            'tenant_id' varchar(50),
            'ip_address' varchar(45),
            'user_agent' text,
            'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY ('id'),
            KEY 'log_level' ('log_level'),
            KEY 'created_at' ('created_at'),
            KEY 'tenant_id' ('tenant_id')
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // System settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS 'system_settings' (
            'id' int(11) NOT NULL AUTO_INCREMENT,
            'setting_key' varchar(100) NOT NULL,
            'setting_value' text,
            'description' text,
            'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
            'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY ('id'),
            UNIQUE KEY 'setting_key' ('setting_key')
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    echo "<p style='color: green;'>✓ تم إنشاء جميع الجداول بنجاح</p>";
    
    // Create default admin account
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM developer_accounts WHERE username = 'admin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $password_hash = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO developer_accounts (username, email, password_hash, full_name, is_active) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute(['admin', 'admin@example.com', $password_hash, 'مدير النظام', 1]);
        echo "<p style='color: green;'>✓ تم إنشاء حساب المطور الافتراضي</p>";
        echo "<p><strong>بيانات تسجيل الدخول:</strong><br>اسم المستخدم: admin<br>كلمة المرور: admin123</p>";
    }
    
    // Add sample tenant
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE tenant_id = 'demo_company'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("
            INSERT INTO tenants (tenant_id, company_name, contact_person, email, subscription_plan, subscription_start, subscription_end, database_name, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            'demo_company',
            'الشركة التجريبية',
            'أحمد محمد',
            'demo@example.com',
            'basic',
            date('Y-m-d'),
            date('Y-m-d', strtotime('+1 year')),
            'warehouse_tenant_demo_company',
            'active'
        ]);
        echo "<p style='color: green;'>✓ تم إضافة شركة تجريبية</p>";
    }
    
    echo "<hr>";
    echo "<h3 style='color: green;'>✓ اكتمل الإعداد بنجاح!</h3>";
    echo "<p><a href='developer_portal/' target='_blank'>انتقل إلى لوحة المطور</a></p>";
    echo "<p><a href='developer_portal/tenants.php' target='_blank'>إدارة الشركات</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>خطأ:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>تأكد من:</strong></p>";
    echo "<ul>";
    echo "<li>تشغيل خادم MySQL</li>";
    echo "<li>صحة اسم المستخدم وكلمة المرور لـ MySQL</li>";
    echo "<li>وجود صلاحيات إنشاء قواعد البيانات</li>";
    echo "</ul>";
}
?>