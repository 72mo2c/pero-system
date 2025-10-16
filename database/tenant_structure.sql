-- =============================================================================
-- TENANT DATABASE STRUCTURE - IMPROVED VERSION
-- =============================================================================
-- Warehouse SaaS System - Tenant Database Template (Enhanced)
-- Created: 2025-10-16
-- 
-- IMPORTANT INSTRUCTIONS:
-- 1. Before running this script, you must create a database for the tenant
-- 2. Replace 'TENANT_DATABASE_NAME' below with your actual tenant database name
-- 3. This script is idempotent - safe to run multiple times
-- 
-- MANUAL STEPS IN phpMyAdmin:
-- 1. Create a new database (e.g., 'warehouse_tenant_company1')
-- 2. Select the database you just created
-- 3. Import this SQL file
-- 
-- ALTERNATIVE: Replace the line below with your database name and uncomment it
-- USE 'TENANT_DATABASE_NAME';
--
-- =============================================================================

-- Check if we're in the right context
-- If no database is selected, this will show an error
SELECT DATABASE() as current_database;

-- =============================================================================
-- USERS TABLE
-- =============================================================================

-- Users table
CREATE TABLE IF NOT EXISTS 'users' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'username' varchar(100) NOT NULL,
    'email' varchar(255) NOT NULL,
    'password_hash' varchar(255) NOT NULL,
    'full_name' varchar(255) NOT NULL,
    'phone' varchar(20),
    'role' enum('admin', 'manager', 'employee') DEFAULT 'employee',
    'permissions' json,
    'is_active' tinyint(1) DEFAULT 1,
    'avatar_path' varchar(500),
    'last_login' timestamp NULL,
    'login_attempts' int(11) DEFAULT 0,
    'locked_until' timestamp NULL,
    'created_by' int(11),
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    UNIQUE KEY 'username' ('username'),
    UNIQUE KEY 'email' ('email'),
    KEY 'idx_role' ('role'),
    KEY 'idx_is_active' ('is_active')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- WAREHOUSES TABLE
-- =============================================================================

-- Warehouses table
CREATE TABLE IF NOT EXISTS 'warehouses' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'code' varchar(20) NOT NULL,
    'name' varchar(255) NOT NULL,
    'name_en' varchar(255),
    'description' text,
    'address' text,
    'city' varchar(100),
    'phone' varchar(20),
    'manager_id' int(11),
    'is_active' tinyint(1) DEFAULT 1,
    'created_by' int(11),
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    UNIQUE KEY 'code' ('code'),
    KEY 'fk_warehouse_manager' ('manager_id'),
    KEY 'fk_warehouse_creator' ('created_by'),
    CONSTRAINT 'fk_warehouse_manager' FOREIGN KEY ('manager_id') REFERENCES 'users' ('id') ON DELETE SET NULL,
    CONSTRAINT 'fk_warehouse_creator' FOREIGN KEY ('created_by') REFERENCES 'users' ('id') ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- PRODUCT CATEGORIES TABLE
-- =============================================================================

-- Product categories table
CREATE TABLE IF NOT EXISTS 'product_categories' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'name' varchar(255) NOT NULL,
    'name_en' varchar(255),
    'description' text,
    'parent_id' int(11),
    'image_path' varchar(500),
    'is_active' tinyint(1) DEFAULT 1,
    'sort_order' int(11) DEFAULT 0,
    'created_by' int(11),
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    KEY 'fk_category_parent' ('parent_id'),
    KEY 'fk_category_creator' ('created_by'),
    KEY 'idx_is_active' ('is_active'),
    CONSTRAINT 'fk_category_parent' FOREIGN KEY ('parent_id') REFERENCES 'product_categories' ('id') ON DELETE CASCADE,
    CONSTRAINT 'fk_category_creator' FOREIGN KEY ('created_by') REFERENCES 'users' ('id') ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- PRODUCTS TABLE
-- =============================================================================

-- Products table
CREATE TABLE IF NOT EXISTS 'products' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'sku' varchar(50) NOT NULL,
    'barcode' varchar(100),
    'name' varchar(255) NOT NULL,
    'name_en' varchar(255),
    'description' text,
    'category_id' int(11),
    'unit' varchar(50) DEFAULT 'piece',
    'cost_price' decimal(10,3) DEFAULT 0.000,
    'selling_price' decimal(10,3) DEFAULT 0.000,
    'min_stock_level' int(11) DEFAULT 0,
    'max_stock_level' int(11),
    'weight' decimal(8,3),
    'dimensions' varchar(100),
    'image_path' varchar(500),
    'additional_images' json,
    'is_active' tinyint(1) DEFAULT 1,
    'is_trackable' tinyint(1) DEFAULT 1,
    'notes' text,
    'created_by' int(11),
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    UNIQUE KEY 'sku' ('sku'),
    UNIQUE KEY 'barcode' ('barcode'),
    KEY 'fk_product_category' ('category_id'),
    KEY 'fk_product_creator' ('created_by'),
    KEY 'idx_is_active' ('is_active'),
    CONSTRAINT 'fk_product_category' FOREIGN KEY ('category_id') REFERENCES 'product_categories' ('id') ON DELETE SET NULL,
    CONSTRAINT 'fk_product_creator' FOREIGN KEY ('created_by') REFERENCES 'users' ('id') ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- PRODUCT STOCK TABLE
-- =============================================================================

-- Product stock table
CREATE TABLE IF NOT EXISTS 'product_stock' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'product_id' int(11) NOT NULL,
    'warehouse_id' int(11) NOT NULL,
    'quantity' int(11) DEFAULT 0,
    'reserved_quantity' int(11) DEFAULT 0,
    'available_quantity' int(11) GENERATED ALWAYS AS ('quantity' - 'reserved_quantity') STORED,
    'last_movement_date' timestamp NULL,
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    UNIQUE KEY 'product_warehouse' ('product_id', 'warehouse_id'),
    KEY 'fk_stock_warehouse' ('warehouse_id'),
    CONSTRAINT 'fk_stock_product' FOREIGN KEY ('product_id') REFERENCES 'products' ('id') ON DELETE CASCADE,
    CONSTRAINT 'fk_stock_warehouse' FOREIGN KEY ('warehouse_id') REFERENCES 'warehouses' ('id') ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SUPPLIERS TABLE
-- =============================================================================

-- Suppliers table
CREATE TABLE IF NOT EXISTS 'suppliers' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'code' varchar(20) NOT NULL,
    'name' varchar(255) NOT NULL,
    'name_en' varchar(255),
    'contact_person' varchar(255),
    'email' varchar(255),
    'phone' varchar(20),
    'mobile' varchar(20),
    'address' text,
    'city' varchar(100),
    'country' varchar(100) DEFAULT 'Saudi Arabia',
    'tax_number' varchar(50),
    'commercial_registration' varchar(50),
    'payment_terms' varchar(100),
    'credit_limit' decimal(12,2) DEFAULT 0.00,
    'current_balance' decimal(12,2) DEFAULT 0.00,
    'is_active' tinyint(1) DEFAULT 1,
    'notes' text,
    'created_by' int(11),
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    UNIQUE KEY 'code' ('code'),
    KEY 'fk_supplier_creator' ('created_by'),
    KEY 'idx_is_active' ('is_active'),
    CONSTRAINT 'fk_supplier_creator' FOREIGN KEY ('created_by') REFERENCES 'users' ('id') ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- CUSTOMERS TABLE
-- =============================================================================

-- Customers table
CREATE TABLE IF NOT EXISTS 'customers' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'code' varchar(20) NOT NULL,
    'name' varchar(255) NOT NULL,
    'name_en' varchar(255),
    'contact_person' varchar(255),
    'email' varchar(255),
    'phone' varchar(20),
    'mobile' varchar(20),
    'address' text,
    'city' varchar(100),
    'country' varchar(100) DEFAULT 'Saudi Arabia',
    'tax_number' varchar(50),
    'commercial_registration' varchar(50),
    'payment_terms' varchar(100),
    'credit_limit' decimal(12,2) DEFAULT 0.00,
    'current_balance' decimal(12,2) DEFAULT 0.00,
    'customer_type' enum('individual', 'company') DEFAULT 'individual',
    'is_active' tinyint(1) DEFAULT 1,
    'notes' text,
    'created_by' int(11),
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    UNIQUE KEY 'code' ('code'),
    KEY 'fk_customer_creator' ('created_by'),
    KEY 'idx_is_active' ('is_active'),
    KEY 'idx_customer_type' ('customer_type'),
    CONSTRAINT 'fk_customer_creator' FOREIGN KEY ('created_by') REFERENCES 'users' ('id') ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- PURCHASE ORDERS TABLE
-- =============================================================================

-- Purchase orders table
CREATE TABLE IF NOT EXISTS 'purchase_orders' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'po_number' varchar(50) NOT NULL,
    'supplier_id' int(11) NOT NULL,
    'warehouse_id' int(11) NOT NULL,
    'po_date' date NOT NULL,
    'expected_delivery_date' date,
    'actual_delivery_date' date,
    'status' enum('draft', 'sent', 'confirmed', 'partially_received', 'received', 'cancelled') DEFAULT 'draft',
    'subtotal' decimal(12,3) DEFAULT 0.000,
    'tax_amount' decimal(12,3) DEFAULT 0.000,
    'discount_amount' decimal(12,3) DEFAULT 0.000,
    'total_amount' decimal(12,3) DEFAULT 0.000,
    'paid_amount' decimal(12,3) DEFAULT 0.000,
    'payment_status' enum('pending', 'partial', 'paid') DEFAULT 'pending',
    'notes' text,
    'terms_conditions' text,
    'created_by' int(11),
    'approved_by' int(11),
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    UNIQUE KEY 'po_number' ('po_number'),
    KEY 'fk_po_supplier' ('supplier_id'),
    KEY 'fk_po_warehouse' ('warehouse_id'),
    KEY 'fk_po_creator' ('created_by'),
    KEY 'fk_po_approver' ('approved_by'),
    KEY 'idx_status' ('status'),
    KEY 'idx_po_date' ('po_date'),
    CONSTRAINT 'fk_po_supplier' FOREIGN KEY ('supplier_id') REFERENCES 'suppliers' ('id') ON DELETE RESTRICT,
    CONSTRAINT 'fk_po_warehouse' FOREIGN KEY ('warehouse_id') REFERENCES 'warehouses' ('id') ON DELETE RESTRICT,
    CONSTRAINT 'fk_po_creator' FOREIGN KEY ('created_by') REFERENCES 'users' ('id') ON DELETE SET NULL,
    CONSTRAINT 'fk_po_approver' FOREIGN KEY ('approved_by') REFERENCES 'users' ('id') ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- PURCHASE ORDER ITEMS TABLE
-- =============================================================================

-- Purchase order items table
CREATE TABLE IF NOT EXISTS 'purchase_order_items' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'purchase_order_id' int(11) NOT NULL,
    'product_id' int(11) NOT NULL,
    'quantity_ordered' int(11) NOT NULL,
    'quantity_received' int(11) DEFAULT 0,
    'unit_price' decimal(10,3) NOT NULL,
    'total_price' decimal(12,3) GENERATED ALWAYS AS ('quantity_ordered' * 'unit_price') STORED,
    'notes' text,
    PRIMARY KEY ('id'),
    KEY 'fk_poi_po' ('purchase_order_id'),
    KEY 'fk_poi_product' ('product_id'),
    CONSTRAINT 'fk_poi_po' FOREIGN KEY ('purchase_order_id') REFERENCES 'purchase_orders' ('id') ON DELETE CASCADE,
    CONSTRAINT 'fk_poi_product' FOREIGN KEY ('product_id') REFERENCES 'products' ('id') ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SALES ORDERS TABLE
-- =============================================================================

-- Sales orders table
CREATE TABLE IF NOT EXISTS 'sales_orders' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'so_number' varchar(50) NOT NULL,
    'customer_id' int(11) NOT NULL,
    'warehouse_id' int(11) NOT NULL,
    'so_date' date NOT NULL,
    'delivery_date' date,
    'status' enum('draft', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'draft',
    'subtotal' decimal(12,3) DEFAULT 0.000,
    'tax_amount' decimal(12,3) DEFAULT 0.000,
    'discount_amount' decimal(12,3) DEFAULT 0.000,
    'shipping_amount' decimal(12,3) DEFAULT 0.000,
    'total_amount' decimal(12,3) DEFAULT 0.000,
    'paid_amount' decimal(12,3) DEFAULT 0.000,
    'payment_status' enum('pending', 'partial', 'paid') DEFAULT 'pending',
    'shipping_address' text,
    'notes' text,
    'terms_conditions' text,
    'created_by' int(11),
    'approved_by' int(11),
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    UNIQUE KEY 'so_number' ('so_number'),
    KEY 'fk_so_customer' ('customer_id'),
    KEY 'fk_so_warehouse' ('warehouse_id'),
    KEY 'fk_so_creator' ('created_by'),
    KEY 'fk_so_approver' ('approved_by'),
    KEY 'idx_status' ('status'),
    KEY 'idx_so_date' ('so_date'),
    CONSTRAINT 'fk_so_customer' FOREIGN KEY ('customer_id') REFERENCES 'customers' ('id') ON DELETE RESTRICT,
    CONSTRAINT 'fk_so_warehouse' FOREIGN KEY ('warehouse_id') REFERENCES 'warehouses' ('id') ON DELETE RESTRICT,
    CONSTRAINT 'fk_so_creator' FOREIGN KEY ('created_by') REFERENCES 'users' ('id') ON DELETE SET NULL,
    CONSTRAINT 'fk_so_approver' FOREIGN KEY ('approved_by') REFERENCES 'users' ('id') ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SALES ORDER ITEMS TABLE
-- =============================================================================

-- Sales order items table
CREATE TABLE IF NOT EXISTS 'sales_order_items' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'sales_order_id' int(11) NOT NULL,
    'product_id' int(11) NOT NULL,
    'quantity' int(11) NOT NULL,
    'unit_price' decimal(10,3) NOT NULL,
    'discount_percentage' decimal(5,2) DEFAULT 0.00,
    'discount_amount' decimal(10,3) DEFAULT 0.000,
    'total_price' decimal(12,3) NOT NULL,
    'notes' text,
    PRIMARY KEY ('id'),
    KEY 'fk_soi_so' ('sales_order_id'),
    KEY 'fk_soi_product' ('product_id'),
    CONSTRAINT 'fk_soi_so' FOREIGN KEY ('sales_order_id') REFERENCES 'sales_orders' ('id') ON DELETE CASCADE,
    CONSTRAINT 'fk_soi_product' FOREIGN KEY ('product_id') REFERENCES 'products' ('id') ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- STOCK MOVEMENTS TABLE
-- =============================================================================

-- Stock movements table
CREATE TABLE IF NOT EXISTS 'stock_movements' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'product_id' int(11) NOT NULL,
    'warehouse_id' int(11) NOT NULL,
    'movement_type' enum('in', 'out', 'transfer', 'adjustment') NOT NULL,
    'quantity' int(11) NOT NULL,
    'unit_cost' decimal(10,3),
    'total_cost' decimal(12,3),
    'reference_type' enum('purchase_order', 'sales_order', 'transfer', 'adjustment', 'manual') NOT NULL,
    'reference_id' int(11),
    'notes' text,
    'movement_date' timestamp DEFAULT CURRENT_TIMESTAMP,
    'created_by' int(11),
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    KEY 'fk_movement_product' ('product_id'),
    KEY 'fk_movement_warehouse' ('warehouse_id'),
    KEY 'fk_movement_creator' ('created_by'),
    KEY 'idx_movement_type' ('movement_type'),
    KEY 'idx_movement_date' ('movement_date'),
    CONSTRAINT 'fk_movement_product' FOREIGN KEY ('product_id') REFERENCES 'products' ('id') ON DELETE RESTRICT,
    CONSTRAINT 'fk_movement_warehouse' FOREIGN KEY ('warehouse_id') REFERENCES 'warehouses' ('id') ON DELETE RESTRICT,
    CONSTRAINT 'fk_movement_creator' FOREIGN KEY ('created_by') REFERENCES 'users' ('id') ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TREASURY ACCOUNTS TABLE
-- =============================================================================

-- Treasury accounts table
CREATE TABLE IF NOT EXISTS 'treasury_accounts' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'account_name' varchar(255) NOT NULL,
    'account_type' enum('cash', 'bank', 'credit_card') DEFAULT 'cash',
    'account_number' varchar(100),
    'bank_name' varchar(255),
    'opening_balance' decimal(12,3) DEFAULT 0.000,
    'current_balance' decimal(12,3) DEFAULT 0.000,
    'is_active' tinyint(1) DEFAULT 1,
    'is_default' tinyint(1) DEFAULT 0,
    'notes' text,
    'created_by' int(11),
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    KEY 'fk_treasury_creator' ('created_by'),
    KEY 'idx_is_active' ('is_active'),
    CONSTRAINT 'fk_treasury_creator' FOREIGN KEY ('created_by') REFERENCES 'users' ('id') ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TREASURY TRANSACTIONS TABLE
-- =============================================================================

-- Treasury transactions table
CREATE TABLE IF NOT EXISTS 'treasury_transactions' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'treasury_account_id' int(11) NOT NULL,
    'transaction_type' enum('income', 'expense', 'transfer_in', 'transfer_out') NOT NULL,
    'amount' decimal(12,3) NOT NULL,
    'description' text NOT NULL,
    'reference_type' enum('purchase_order', 'sales_order', 'expense', 'transfer', 'manual') NOT NULL,
    'reference_id' int(11),
    'transaction_date' timestamp DEFAULT CURRENT_TIMESTAMP,
    'created_by' int(11),
    'approved_by' int(11),
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    KEY 'fk_transaction_treasury' ('treasury_account_id'),
    KEY 'fk_transaction_creator' ('created_by'),
    KEY 'fk_transaction_approver' ('approved_by'),
    KEY 'idx_transaction_type' ('transaction_type'),
    KEY 'idx_transaction_date' ('transaction_date'),
    CONSTRAINT 'fk_transaction_treasury' FOREIGN KEY ('treasury_account_id') REFERENCES 'treasury_accounts' ('id') ON DELETE RESTRICT,
    CONSTRAINT 'fk_transaction_creator' FOREIGN KEY ('created_by') REFERENCES 'users' ('id') ON DELETE SET NULL,
    CONSTRAINT 'fk_transaction_approver' FOREIGN KEY ('approved_by') REFERENCES 'users' ('id') ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ACTIVITY LOGS TABLE
-- =============================================================================

-- Activity logs table
CREATE TABLE IF NOT EXISTS 'activity_logs' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'user_id' int(11),
    'action' varchar(100) NOT NULL,
    'table_name' varchar(100),
    'record_id' int(11),
    'old_values' json,
    'new_values' json,
    'ip_address' varchar(45),
    'user_agent' text,
    'created_at' timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    KEY 'fk_log_user' ('user_id'),
    KEY 'idx_action' ('action'),
    KEY 'idx_table_name' ('table_name'),
    KEY 'idx_created_at' ('created_at'),
    CONSTRAINT 'fk_log_user' FOREIGN KEY ('user_id') REFERENCES 'users' ('id') ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SETTINGS TABLE
-- =============================================================================

-- Settings table
CREATE TABLE IF NOT EXISTS 'settings' (
    'id' int(11) NOT NULL AUTO_INCREMENT,
    'setting_key' varchar(100) NOT NULL,
    'setting_value' text,
    'description' varchar(500),
    'is_system' tinyint(1) DEFAULT 0,
    'updated_by' int(11),
    'updated_at' timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY ('id'),
    UNIQUE KEY 'setting_key' ('setting_key'),
    KEY 'fk_setting_updater' ('updated_by'),
    CONSTRAINT 'fk_setting_updater' FOREIGN KEY ('updated_by') REFERENCES 'users' ('id') ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- DEFAULT DATA INSERTION
-- =============================================================================

-- Insert default admin user (only if not exists)
INSERT IGNORE INTO 'users' ('username', 'email', 'password_hash', 'full_name', 'role', 'permissions') VALUES
('admin', 'admin@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin', '[]');

-- Insert default warehouse (only if not exists)
INSERT IGNORE INTO 'warehouses' ('code', 'name', 'name_en', 'description', 'created_by') VALUES
('WH001', 'المخزن الرئيسي', 'Main Warehouse', 'المخزن الرئيسي للشركة', 1);

-- Insert default treasury account (only if not exists)
INSERT IGNORE INTO 'treasury_accounts' ('account_name', 'account_type', 'opening_balance', 'current_balance', 'is_default', 'created_by') VALUES
('الخزينة الرئيسية', 'cash', 0.000, 0.000, 1, 1);

-- Insert default settings (only if not exists)
INSERT IGNORE INTO 'settings' ('setting_key', 'setting_value', 'description') VALUES
('company_name', 'اسم الشركة', 'اسم الشركة'),
('company_name_en', 'Company Name', 'Company name in English'),
('tax_percentage', '15', 'نسبة الضريبة المضافة'),
('currency_symbol', 'ر.س', 'رمز العملة'),
('date_format', 'Y-m-d', 'تنسيق التاريخ'),
('timezone', 'Asia/Riyadh', 'المنطقة الزمنية'),
('language', 'ar', 'لغة النظام'),
('items_per_page', '20', 'عدد العناصر في الصفحة'),
('low_stock_threshold', '10', 'حد التنبيه للمخزون المنخفض'),
('auto_generate_codes', '1', 'إنشاء الأكواد تلقائياً'),
('backup_frequency', 'daily', 'تكرار النسخ الاحتياطي'),
('email_notifications', '1', 'تفعيل الإشعارات عبر البريد الإلكتروني');

-- =============================================================================
-- END OF SCRIPT
-- =============================================================================

-- Verify setup completed successfully
SELECT 'Tenant database structure created successfully!' as status;