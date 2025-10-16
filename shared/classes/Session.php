<?php
/**
 * Session Management Class for Warehouse SaaS System
 * Handles session management, user authentication, and tenant context
 * Created: 2025-10-16
 */

class Session {
    private static $initialized = false;
    
    /**
     * Initialize session management
     */
    public static function initialize() {
        if (self::$initialized) return;
        
        // Configure session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.cookie_samesite', 'Strict');
        
        // Set session timeout
        ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID periodically
        self::regenerateSessionId();
        
        // Check session timeout
        self::checkSessionTimeout();
        
        self::$initialized = true;
    }
    
    /**
     * Regenerate session ID
     */
    private static function regenerateSessionId() {
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    /**
     * Check session timeout
     */
    private static function checkSessionTimeout() {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                self::destroy();
                return false;
            }
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Set session value
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get session value
     */
    public static function get($key, $default = null) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }
    
    /**
     * Check if session key exists
     */
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session key
     */
    public static function remove($key) {
        unset($_SESSION[$key]);
    }
    
    /**
     * Destroy session
     */
    public static function destroy() {
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        
        session_destroy();
    }
    
    /**
     * Login developer
     */
    public static function loginDeveloper($user_data) {
        self::set('developer_logged_in', true);
        self::set('developer_id', $user_data['id']);
        self::set('developer_username', $user_data['username']);
        self::set('developer_email', $user_data['email']);
        self::set('developer_full_name', $user_data['full_name']);
        self::set('user_type', 'developer');
        
        // Update last login
        try {
            $pdo = DatabaseConfig::getMainConnection();
            $stmt = $pdo->prepare("UPDATE developer_accounts SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user_data['id']]);
        } catch (Exception $e) {
            error_log('Failed to update developer last login: ' . $e->getMessage());
        }
    }
    
    /**
     * Login tenant user
     */
    public static function loginTenantUser($user_data, $tenant_data) {
        self::set('user_logged_in', true);
        self::set('user_id', $user_data['id']);
        self::set('user_username', $user_data['username']);
        self::set('user_email', $user_data['email']);
        self::set('user_full_name', $user_data['full_name']);
        self::set('user_role', $user_data['role']);
        self::set('user_permissions', $user_data['permissions']);
        self::set('user_type', 'tenant_user');
        
        // Set tenant context
        self::set('tenant_id', $tenant_data['tenant_id']);
        self::set('tenant_name', $tenant_data['company_name']);
        self::set('tenant_database', $tenant_data['database_name']);
        
        // Update last login
        try {
            $pdo = DatabaseConfig::getTenantConnection($tenant_data['tenant_id']);
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user_data['id']]);
        } catch (Exception $e) {
            error_log('Failed to update user last login: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if developer is logged in
     */
    public static function isDeveloperLoggedIn() {
        return self::get('developer_logged_in', false) && self::get('user_type') === 'developer';
    }
    
    /**
     * Check if tenant user is logged in
     */
    public static function isUserLoggedIn() {
        return self::get('user_logged_in', false) && self::get('user_type') === 'tenant_user';
    }
    
    /**
     * Check if any user is logged in
     */
    public static function isLoggedIn() {
        return self::isDeveloperLoggedIn() || self::isUserLoggedIn();
    }
    
    /**
     * Get current user ID
     */
    public static function getUserId() {
        if (self::isDeveloperLoggedIn()) {
            return self::get('developer_id');
        } elseif (self::isUserLoggedIn()) {
            return self::get('user_id');
        }
        return null;
    }
    
    /**
     * Get current user type
     */
    public static function getUserType() {
        return self::get('user_type');
    }
    
    /**
     * Get current user role
     */
    public static function getUserRole() {
        if (self::isDeveloperLoggedIn()) {
            return 'developer';
        }
        return self::get('user_role', 'employee');
    }
    
    /**
     * Get current tenant ID
     */
    public static function getTenantId() {
        return self::get('tenant_id');
    }
    
    /**
     * Get current tenant name
     */
    public static function getTenantName() {
        return self::get('tenant_name');
    }
    
    /**
     * Check user permission
     */
    public static function hasPermission($permission) {
        if (self::isDeveloperLoggedIn()) {
            return true; // Developers have all permissions
        }
        
        $role = self::getUserRole();
        $permissions = self::get('user_permissions', []);
        
        // Admin has all permissions
        if ($role === 'admin') {
            return true;
        }
        
        // Check specific permissions
        if (is_string($permissions)) {
            $permissions = json_decode($permissions, true) ?: [];
        }
        
        return in_array($permission, $permissions);
    }
    
    /**
     * Require login
     */
    public static function requireLogin($redirect_url = null) {
        if (!self::isLoggedIn()) {
            $redirect_url = $redirect_url ?: '/warehouse_saas_system/warehouse_system/login.php';
            header("Location: $redirect_url");
            exit;
        }
    }
    
    /**
     * Require developer login
     */
    public static function requireDeveloperLogin($redirect_url = null) {
        if (!self::isDeveloperLoggedIn()) {
            $redirect_url = $redirect_url ?: '/warehouse_saas_system/developer_portal/login.php';
            header("Location: $redirect_url");
            exit;
        }
    }
    
    /**
     * Require tenant user login
     */
    public static function requireTenantLogin($redirect_url = null) {
        if (!self::isUserLoggedIn()) {
            $redirect_url = $redirect_url ?: '/warehouse_saas_system/warehouse_system/login.php';
            header("Location: $redirect_url");
            exit;
        }
    }
    
    /**
     * Require specific permission
     */
    public static function requirePermission($permission, $redirect_url = null) {
        if (!self::hasPermission($permission)) {
            if (Utils::isAjax()) {
                Utils::sendJsonResponse(['success' => false, 'message' => 'لا تملك صلاحية للقيام بهذا الإجراء'], 403);
            } else {
                $redirect_url = $redirect_url ?: '/warehouse_saas_system/warehouse_system/dashboard.php?error=no_permission';
                header("Location: $redirect_url");
                exit;
            }
        }
    }
    
    /**
     * Set flash message
     */
    public static function setFlash($type, $message) {
        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }
        $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
    }
    
    /**
     * Get flash messages
     */
    public static function getFlash() {
        $messages = self::get('flash_messages', []);
        self::remove('flash_messages');
        return $messages;
    }
    
    /**
     * Set success message
     */
    public static function setSuccess($message) {
        self::setFlash('success', $message);
    }
    
    /**
     * Set error message
     */
    public static function setError($message) {
        self::setFlash('error', $message);
    }
    
    /**
     * Set warning message
     */
    public static function setWarning($message) {
        self::setFlash('warning', $message);
    }
    
    /**
     * Set info message
     */
    public static function setInfo($message) {
        self::setFlash('info', $message);
    }
    
    /**
     * Logout current user
     */
    public static function logout() {
        // Log activity before destroying session
        if (self::isUserLoggedIn()) {
            try {
                $tenant_id = self::getTenantId();
                $user_id = self::getUserId();
                $pdo = DatabaseConfig::getTenantConnection($tenant_id);
                Utils::logActivity($pdo, $user_id, 'logout', null, null, null, null, 'تسجيل خروج من النظام');
            } catch (Exception $e) {
                error_log('Failed to log logout activity: ' . $e->getMessage());
            }
        }
        
        self::destroy();
    }
    
    /**
     * Get current user display name
     */
    public static function getUserDisplayName() {
        if (self::isDeveloperLoggedIn()) {
            return self::get('developer_full_name', self::get('developer_username'));
        } elseif (self::isUserLoggedIn()) {
            return self::get('user_full_name', self::get('user_username'));
        }
        return 'ضيف';
    }
    
    /**
     * Get session info for debugging
     */
    public static function getSessionInfo() {
        return [
            'session_id' => session_id(),
            'is_logged_in' => self::isLoggedIn(),
            'user_type' => self::getUserType(),
            'user_role' => self::getUserRole(),
            'tenant_id' => self::getTenantId(),
            'last_activity' => self::get('last_activity'),
            'last_regeneration' => self::get('last_regeneration')
        ];
    }
}
?>