<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set header to return JSON
header('Content-Type: application/json');

// Create a simple test array
$test_data = [
    'success' => true,
    'message' => 'This is a test JSON response',
    'timestamp' => date('Y-m-d H:i:s'),
    'test_array' => [
        ['id' => 1, 'name' => 'Test Item 1'],
        ['id' => 2, 'name' => 'Test Item 2'],
        ['id' => 3, 'name' => 'Test Item 3']
    ]
];

// Output as JSON
echo json_encode($test_data);
