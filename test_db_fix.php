<?php
// Test file to verify the bind_param fix in calendar_data_handler.php

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set a test user in session for logging
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Test User';

// Include the calendar data handler
require_once 'includes/calendar_data_handler.php';

echo "<h1>Testing Calendar Functions</h1>";

// Test Data
$testEventData = [
    'siteName' => 'test-site',
    'day' => 8,
    'month' => 5,
    'year' => 2025,
    'vendors' => [
        [
            'type' => 'supplier',
            'name' => 'Test Vendor',
            'contact' => '1234567890'
        ]
    ]
];

echo "<h2>Testing saveCalendarData Function</h2>";
try {
    $result = saveCalendarData($testEventData);
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if ($result['status'] === 'success') {
        echo "<p style='color:green'>✅ Test passed: Calendar data saved successfully!</p>";
    } else {
        echo "<p style='color:red'>❌ Test failed: " . $result['message'] . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Test failed with exception: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?> 