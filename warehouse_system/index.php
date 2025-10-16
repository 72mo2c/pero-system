<?php
/**
 * Warehouse System Main Entry Point
 * Tenant-specific warehouse management system
 * Created: 2025-10-16
 */

require_once '../config/config.php';

// Check if user is logged in
if (!Session::isUserLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Redirect to dashboard
header('Location: dashboard.php');
exit;
?>