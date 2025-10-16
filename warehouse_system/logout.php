<?php
/**
 * Logout functionality for warehouse system
 * Created: 2025-10-16
 */

require_once '../config/config.php';

// Initialize session
Session::initialize();

// Log logout activity if user is logged in
if (Session::isUserLoggedIn()) {
    try {
        $pdo = DatabaseConfig::getTenantConnection(Session::getTenantId());
        Utils::logActivity(
            $pdo, 
            Session::getUserId(), 
            'logout', 
            null, 
            null, 
            null, 
            null, 
            'تسجيل خروج من النظام'
        );
    } catch (Exception $e) {
        error_log('Logout logging error: ' . $e->getMessage());
    }
}

// Destroy session
Session::destroy();

// Clear any remember me cookies
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/', '', true, true);
}

// Redirect to login page
header('Location: login.php?success=logged_out');
exit;
?>
