<?php
// Set proper content type header for JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Log the request
error_log("work_media_handler.php proxy started");
error_log("POST data: " . json_encode($_POST));
error_log("FILES data: " . json_encode($_FILES));

try {
    // Include the actual handler
    require_once 'includes/process_work_media.php';
    // The included file has its own exit statement, so execution won't continue past this point
} catch (Exception $e) {
    // Log the error
    error_log("Error in work_media_handler.php proxy: " . $e->getMessage());
    
    // Return JSON error response
    echo json_encode([
        'success' => false,
        'error' => 'Error in proxy handler: ' . $e->getMessage(),
        'file' => __FILE__
    ]);
    exit;
} 