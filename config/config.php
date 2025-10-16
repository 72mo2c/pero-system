<?php
/**
 * Main Configuration File for Warehouse SaaS System
 * Created: 2025-10-16
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define system constants
define('SYSTEM_NAME', 'Warehouse SaaS System');
define('SYSTEM_VERSION', '1.0.0');
define('SYSTEM_AUTHOR', 'Elbiruni Soft');
define('SYSTEM_URL', 'http://localhost/warehouse_saas_system');
define('DEVELOPER_PORTAL_URL', SYSTEM_URL . '/developer_portal');
define('WAREHOUSE_SYSTEM_URL', SYSTEM_URL . '/warehouse_system');

// Define paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('SHARED_PATH', ROOT_PATH . '/shared');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('DATABASE_PATH', ROOT_PATH . '/database');
define('DEVELOPER_PORTAL_PATH', ROOT_PATH . '/developer_portal');
define('WAREHOUSE_SYSTEM_PATH', ROOT_PATH . '/warehouse_system');

// System settings
define('TIMEZONE', 'Asia/Riyadh');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('CURRENCY_SYMBOL', 'ر.س');
define('LANGUAGE', 'ar');

// Security settings
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('CSRF_TOKEN_EXPIRY', 1800); // 30 minutes

// File upload settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Pagination settings
define('ITEMS_PER_PAGE', 20);
define('MAX_PAGINATION_LINKS', 10);

// Email settings (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('FROM_EMAIL', 'noreply@warehousesaas.com');
define('FROM_NAME', 'Warehouse SaaS System');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error reporting
if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Include required files
require_once CONFIG_PATH . '/database.php';
require_once SHARED_PATH . '/classes/Security.php';
require_once SHARED_PATH . '/classes/Utils.php';
require_once SHARED_PATH . '/classes/Session.php';

// Initialize security and session management
Security::initialize();
Session::initialize();

/**
 * Get current timestamp in system format
 */
function getCurrentTimestamp() {
    return date(DATETIME_FORMAT);
}

/**
 * Get current date in system format
 */
function getCurrentDate() {
    return date(DATE_FORMAT);
}

/**
 * Generate UUID
 */
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return number_format($amount, 2) . ' ' . CURRENCY_SYMBOL;
}

/**
 * Redirect function
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Get base URL
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    return $protocol . '://' . $host . $path;
}
?>