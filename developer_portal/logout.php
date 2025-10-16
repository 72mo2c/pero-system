<?php
/**
 * Developer Portal Logout
 * Handles developer logout and session cleanup
 * Created: 2025-10-16
 */

require_once '../config/config.php';

// Check if request is AJAX
if (Utils::isAjax()) {
    try {
        // Log the logout activity if developer is logged in
        if (Session::isDeveloperLoggedIn()) {
            $developer_id = Session::getUserId();
            $pdo = DatabaseConfig::getMainConnection();
            $stmt = $pdo->prepare("
                INSERT INTO system_activity_logs (user_type, user_id, action, description, ip_address, user_agent) 
                VALUES ('developer', ?, 'logout', ?, ?, ?)
            ");
            $stmt->execute([
                $developer_id,
                'تسجيل خروج مطور ناجح',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        }
        
        // Destroy session
        Session::logout();
        
        Utils::sendJsonResponse([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    } catch (Exception $e) {
        error_log('Developer logout error: ' . $e->getMessage());
        Utils::sendJsonResponse([
            'success' => false,
            'message' => 'حدث خطأ أثناء تسجيل الخروج'
        ], 500);
    }
} else {
    // Regular logout request
    try {
        // Log the logout activity if developer is logged in
        if (Session::isDeveloperLoggedIn()) {
            $developer_id = Session::getUserId();
            $pdo = DatabaseConfig::getMainConnection();
            $stmt = $pdo->prepare("
                INSERT INTO system_activity_logs (user_type, user_id, action, description, ip_address, user_agent) 
                VALUES ('developer', ?, 'logout', ?, ?, ?)
            ");
            $stmt->execute([
                $developer_id,
                'تسجيل خروج مطور ناجح',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        }
    } catch (Exception $e) {
        error_log('Developer logout logging error: ' . $e->getMessage());
    }
    
    // Destroy session
    Session::logout();
    
    // Redirect to login page
    header('Location: login.php?message=' . urlencode('تم تسجيل الخروج بنجاح'));
    exit;
}
?>