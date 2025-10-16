<?php
/**
 * Database Configuration for Warehouse SaaS System
 * Multi-tenant Architecture Support
 * Created: 2025-10-16
 */

class DatabaseConfig {
    // Main system database (for tenant management)
    private static $main_config = [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'warehouse_saas_main',
        'charset' => 'utf8mb4'
    ];
    
    // Tenant database prefix
    private static $tenant_prefix = 'warehouse_tenant_';
    
    // Current tenant info
    private static $current_tenant = null;
    
    /**
     * Get main system database connection
     */
    public static function getMainConnection() {
        try {
            $dsn = "mysql:host=" . self::$main_config['host'] . ";dbname=" . self::$main_config['database'] . ";charset=" . self::$main_config['charset'];
            $pdo = new PDO($dsn, self::$main_config['username'], self::$main_config['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        } catch (PDOException $e) {
            error_log("Main Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    /**
     * Get tenant database connection
     */
    public static function getTenantConnection($tenant_id) {
        try {
            $database_name = self::$tenant_prefix . $tenant_id;
            $dsn = "mysql:host=" . self::$main_config['host'] . ";dbname=" . $database_name . ";charset=" . self::$main_config['charset'];
            $pdo = new PDO($dsn, self::$main_config['username'], self::$main_config['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        } catch (PDOException $e) {
            error_log("Tenant Database Connection Error: " . $e->getMessage());
            throw new Exception("Tenant database connection failed");
        }
    }
    
    /**
     * Create new tenant database
     */
    public static function createTenantDatabase($tenant_id) {
        try {
            $database_name = self::$tenant_prefix . $tenant_id;
            $main_pdo = self::getMainConnection();
            
            // Create database
            $main_pdo->exec("CREATE DATABASE IF NOT EXISTS '{$database_name}' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Get tenant connection and create tables
            $tenant_pdo = self::getTenantConnection($tenant_id);
            self::createTenantTables($tenant_pdo);
            
            return true;
        } catch (Exception $e) {
            error_log("Create Tenant Database Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create tenant tables
     */
    private static function createTenantTables($pdo) {
        $sql_file = __DIR__ . '/../database/tenant_structure.sql';
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            $pdo->exec($sql);
        }
    }
    
    /**
     * Set current tenant
     */
    public static function setCurrentTenant($tenant_id) {
        self::$current_tenant = $tenant_id;
    }
    
    /**
     * Get current tenant
     */
    public static function getCurrentTenant() {
        return self::$current_tenant;
    }
    
    /**
     * Get tenant database name
     */
    public static function getTenantDatabaseName($tenant_id) {
        return self::$tenant_prefix . $tenant_id;
    }
}
?>