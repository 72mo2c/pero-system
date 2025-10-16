<?php
/**
 * Authentication Check for Developer Portal
 * Ensure developer is logged in
 * Created: 2025-10-16
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)));
}

// Ensure config is loaded (for Session and other classes)
if (!class_exists('Session')) {
    require_once dirname(__DIR__) . '/config/config.php';
}

// Check if developer is logged in
if (!Session::isDeveloperLoggedIn()) {
    if (Utils::isAjax()) {
        Utils::sendJsonResponse([
            'success' => false,
            'message' => 'جلستك منتهية الصلاحية. يرجى تسجيل الدخول مرة أخرى.'
        ], 401);
    } else {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

// Update last activity
Session::set('last_activity', time());
?>