<?php
/**
 * Developer Portal Main Page
 * Entry point for developer portal
 * Created: 2025-10-16
 */

require_once '../config/config.php';

// Check if developer is logged in
if (!Session::isDeveloperLoggedIn()) {
    header('Location: login.php');
    exit;
}

header('Location: dashboard.php');
exit;
?>