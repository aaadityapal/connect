<?php
// Set proper content type header for JSON response
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create a simple response
$response = [
    'success' => true,
    'message' => 'This is a test response',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion()
];

// Output as JSON
echo json_encode($response);
exit;
?>