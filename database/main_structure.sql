-- Main System Database Structure (Safe Version)
-- Warehouse SaaS System - Main Database
-- Created: 2025-10-16
-- Updated: Safe version that handles existing tables

CREATE DATABASE IF NOT EXISTS 'warehouse_saas_main' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE 'warehouse_saas_main';

-- System settings table
CREATE TABLE IF NOT EXISTS 'system_settings' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'setting_key' varchar(100) NOT NULL,
    'setting_value' text,
    'description' text,
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    UNIQUE KEY 'setting_key' ('setting_key')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Developer accounts table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tenants (Companies) table
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
    KEY 'subscription_plan' ('subscription_plan'),
    FOREIGN KEY ('created_by') REFERENCES 'developer_accounts'('id') ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subscription plans table
CREATE TABLE IF NOT EXISTS 'subscription_plans' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'plan_name' varchar(100) NOT NULL,
    'plan_name_en' varchar(100) NOT NULL,
    'description' text,
    'price_monthly' decimal(10,2) NOT NULL DEFAULT 0.00,
    'price_yearly' decimal(10,2) NOT NULL DEFAULT 0.00,
    'max_users' int(11) NOT NULL DEFAULT 5,
    'max_warehouses' int(11) NOT NULL DEFAULT 3,
    'disk_space_limit_mb' int(11) NOT NULL DEFAULT 1000,
    'monthly_transactions_limit' int(11) NOT NULL DEFAULT 1000,
    'api_access' tinyint(1) DEFAULT 0,
    'custom_domain' tinyint(1) DEFAULT 0,
    'support_level' enum('basic', 'priority', 'premium') DEFAULT 'basic',
    'features' json,
    'is_active' tinyint(1) DEFAULT 1,
    'sort_order' int(11) DEFAULT 0,
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    UNIQUE KEY 'plan_name' ('plan_name')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tenant subscriptions history
CREATE TABLE IF NOT EXISTS 'tenant_subscriptions' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'tenant_id' int(11) NOT NULL,
    'subscription_plan_id' int(11) NOT NULL,
    'start_date' date NOT NULL,
    'end_date' date NOT NULL,
    'amount_paid' decimal(10,2) NOT NULL DEFAULT 0.00,
    'payment_method' varchar(50),
    'payment_reference' varchar(255),
    'status' enum('active', 'expired', 'cancelled') DEFAULT 'active',
    'auto_renewal' tinyint(1) DEFAULT 1,
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    KEY 'tenant_id' ('tenant_id'),
    KEY 'subscription_plan_id' ('subscription_plan_id'),
    KEY 'status' ('status'),
    FOREIGN KEY ('tenant_id') REFERENCES 'tenants'('id') ON DELETE CASCADE,
    FOREIGN KEY ('subscription_plan_id') REFERENCES 'subscription_plans'('id') ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System activity logs
CREATE TABLE IF NOT EXISTS 'system_activity_logs' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'tenant_id' int(11),
    'user_type' enum('developer', 'admin', 'manager', 'employee') NOT NULL,
    'user_id' int(11) NOT NULL,
    'action_type' varchar(100) NOT NULL,
    'action_description' text,
    'affected_table' varchar(100),
    'affected_record_id' int(11),
    'old_values' json,
    'new_values' json,
    'ip_address' varchar(45),
    'user_agent' text,
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    KEY 'tenant_id' ('tenant_id'),
    KEY 'user_type' ('user_type'),
    KEY 'user_id' ('user_id'),
    KEY 'action_type' ('action_type'),
    KEY 'created_at' ('created_at'),
    FOREIGN KEY ('tenant_id') REFERENCES 'tenants'('id') ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System error logs
CREATE TABLE IF NOT EXISTS 'system_error_logs' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'tenant_id' int(11),
    'error_level' enum('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'error',
    'error_message' text NOT NULL,
    'error_file' varchar(500),
    'error_line' int(11),
    'stack_trace' longtext,
    'request_uri' varchar(500),
    'request_method' varchar(10),
    'request_data' json,
    'user_id' int(11),
    'user_type' varchar(20),
    'ip_address' varchar(45),
    'user_agent' text,
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    KEY 'tenant_id' ('tenant_id'),
    KEY 'error_level' ('error_level'),
    KEY 'created_at' ('created_at'),
    FOREIGN KEY ('tenant_id') REFERENCES 'tenants'('id') ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backup management
CREATE TABLE IF NOT EXISTS 'system_backups' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'tenant_id' int(11),
    'backup_type' enum('full', 'incremental', 'database_only', 'files_only') NOT NULL DEFAULT 'full',
    'backup_name' varchar(255) NOT NULL,
    'backup_path' varchar(500) NOT NULL,
    'backup_size_mb' decimal(10,2),
    'backup_status' enum('in_progress', 'completed', 'failed') NOT NULL DEFAULT 'in_progress',
    'backup_method' enum('automatic', 'manual') NOT NULL DEFAULT 'automatic',
    'initiated_by' int(11),
    'error_message' text,
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'completed_at' timestamp NULL,
    PRIMARY KEY ('id'),
    KEY 'tenant_id' ('tenant_id'),
    KEY 'backup_type' ('backup_type'),
    KEY 'backup_status' ('backup_status'),
    KEY 'created_at' ('created_at'),
    FOREIGN KEY ('tenant_id') REFERENCES 'tenants'('id') ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System notifications
CREATE TABLE IF NOT EXISTS 'system_notifications' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'tenant_id' int(11),
    'notification_type' enum('info', 'warning', 'error', 'success') NOT NULL DEFAULT 'info',
    'title' varchar(255) NOT NULL,
    'message' text NOT NULL,
    'target_user_type' enum('developer', 'tenant_admin', 'all_users') NOT NULL DEFAULT 'tenant_admin',
    'target_user_id' int(11),
    'is_read' tinyint(1) DEFAULT 0,
    'is_global' tinyint(1) DEFAULT 0,
    'expires_at' timestamp NULL,
    'action_required' tinyint(1) DEFAULT 0,
    'action_url' varchar(500),
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'read_at' timestamp NULL,
    PRIMARY KEY ('id'),
    KEY 'tenant_id' ('tenant_id'),
    KEY 'notification_type' ('notification_type'),
    KEY 'target_user_type' ('target_user_type'),
    KEY 'is_read' ('is_read'),
    KEY 'created_at' ('created_at'),
    FOREIGN KEY ('tenant_id') REFERENCES 'tenants'('id') ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tenant subscription history
CREATE TABLE IF NOT EXISTS 'tenant_subscription_history' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'tenant_id' int(11) NOT NULL,
    'plan' varchar(50) NOT NULL,
    'start_date' date NOT NULL,
    'end_date' date NOT NULL,
    'amount' decimal(10,2) DEFAULT 0.00,
    'currency' varchar(3) DEFAULT 'SAR',
    'payment_method' varchar(50),
    'payment_reference' varchar(255),
    'status' enum('active', 'expired', 'cancelled') DEFAULT 'active',
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    KEY 'fk_subscription_tenant' ('tenant_id'),
    CONSTRAINT 'fk_subscription_tenant' FOREIGN KEY ('tenant_id') REFERENCES 'tenants' ('id') ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System updates table
CREATE TABLE IF NOT EXISTS 'system_updates' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'version' varchar(20) NOT NULL,
    'update_type' enum('major', 'minor', 'patch', 'hotfix') NOT NULL,
    'title' varchar(255) NOT NULL,
    'description' text,
    'changelog' text,
    'file_path' varchar(500),
    'is_mandatory' tinyint(1) DEFAULT 0,
    'target_tenants' json,
    'status' enum('pending', 'in_progress', 'completed', 'failed') DEFAULT 'pending',
    'created_by' int(11) NOT NULL,
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'scheduled_at' timestamp NULL,
    'completed_at' timestamp NULL,
    PRIMARY KEY ('id'),
    KEY 'fk_updates_developer' ('created_by'),
    CONSTRAINT 'fk_updates_developer' FOREIGN KEY ('created_by') REFERENCES 'developer_accounts' ('id') ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default subscription plans (with IGNORE to prevent duplicates)
INSERT IGNORE INTO 'subscription_plans' ('plan_name', 'plan_name_en', 'description', 'price_monthly', 'price_yearly', 'max_users', 'max_warehouses', 'disk_space_limit_mb', 'monthly_transactions_limit', 'api_access', 'custom_domain', 'support_level', 'features') VALUES
('الباقة الأساسية', 'Basic Plan', 'باقة مناسبة للشركات الصغيرة والمتوسطة', 299.00, 2990.00, 5, 3, 1000, 1000, 0, 0, 'basic', '{"reports": true, "inventory": true, "sales": true, "purchases": true}'),
('الباقة المتقدمة', 'Professional Plan', 'باقة مناسبة للشركات المتوسطة والكبيرة', 599.00, 5990.00, 15, 10, 5000, 5000, 1, 0, 'priority', '{"reports": true, "inventory": true, "sales": true, "purchases": true, "analytics": true, "api_access": true}'),
('باقة المؤسسات', 'Enterprise Plan', 'باقة مناسبة للمؤسسات الكبيرة', 1199.00, 11990.00, 50, 999, 20000, 999999, 1, 1, 'premium', '{"reports": true, "inventory": true, "sales": true, "purchases": true, "analytics": true, "api_access": true, "custom_domain": true, "white_label": true}');

-- Insert default system settings (with IGNORE to prevent duplicates)
INSERT IGNORE INTO 'system_settings' ('setting_key', 'setting_value', 'description') VALUES
('system_name', 'نظام إدارة المخازن SaaS', 'اسم النظام'),
('system_version', '1.0.0', 'إصدار النظام'),
('developer_company', 'MiniMax Agent', 'الشركة المطورة'),
('default_language', 'ar', 'اللغة الافتراضية'),
('default_timezone', 'Asia/Riyadh', 'المنطقة الزمنية الافتراضية'),
('maintenance_mode', '0', 'وضع الصيانة'),
('allow_registration', '1', 'السماح بالتسجيل الجديد'),
('email_verification_required', '1', 'طلب تأكيد البريد الإلكتروني'),
('backup_frequency_hours', '24', 'تكرار النسخ الاحتياطية بالساعات'),
('session_timeout_minutes', '120', 'انتهاء صلاحية الجلسة بالدقائق'),
('max_login_attempts', '5', 'محاولات الدخول القصوى'),
('password_min_length', '8', 'الحد الأدنى لطول كلمة المرور'),
('currency_code', 'SAR', 'رمز العملة'),
('currency_symbol', 'ر.س', 'رمز العملة'),
('date_format', 'Y-m-d', 'تنسيق التاريخ'),
('time_format', 'H:i:s', 'تنسيق الوقت');

-- Insert default developer account (with IGNORE to prevent duplicates)
INSERT IGNORE INTO 'developer_accounts' ('username', 'email', 'password_hash', 'full_name', 'phone') VALUES
('admin', 'admin@warehousesaas.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', '+966500000000');