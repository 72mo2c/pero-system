<?php
/**
 * Simple test for system status to debug JSON issues
 */

// Turn off all error reporting and display
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Simple test response
    $response = [
        'success' => true,
        'message' => 'Test successful',
        'data' => [
            'test' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION
        ]
    ];
    
    // Clean any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Return JSON
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Clean output buffer
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Return error
    echo json_encode([
        'success' => false,
        'message' => 'Test failed',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit;
?>