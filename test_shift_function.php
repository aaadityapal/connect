<?php
// Test script to verify shift functions are working correctly
require_once 'config/db_connect.php';
require_once 'includes/functions/shift_functions.php';

// Test with user ID 1 (default)
$user_id = 1;

echo "<h1>Shift Function Test</h1>\n";
echo "<p>Testing with user ID: $user_id</p>\n";

// Test getUserShiftEndTime function
echo "<h2>getUserShiftEndTime() Test</h2>\n";
$user_shift = getUserShiftEndTime($pdo, $user_id);
echo "<pre>" . print_r($user_shift, true) . "</pre>\n";

// Test getUserShiftForDate function
echo "<h2>getUserShiftForDate() Test</h2>\n";
$test_date = date('Y-m-d');
$user_shift_date = getUserShiftForDate($pdo, $user_id, $test_date);
echo "<pre>" . print_r($user_shift_date, true) . "</pre>\n";

echo "<p>Test completed successfully!</p>\n";
?>