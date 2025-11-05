<?php
session_start();
require_once 'config/db_connect.php';
require_once 'includes/functions/shift_functions.php';

// Test with a specific user ID (you can change this to test with different users)
$user_id = $_SESSION['user_id'] ?? 21; // Default to user 21 for testing
$date = '2025-10-28'; // Test date

echo "<h1>Work Report Test</h1>";
echo "<p>User ID: " . htmlspecialchars($user_id) . "</p>";
echo "<p>Date: " . htmlspecialchars($date) . "</p>";

// Fetch work report
$work_report = getUserWorkReport($pdo, $user_id, $date);

echo "<p>Work Report: " . (!empty($work_report) ? htmlspecialchars($work_report) : 'No work report found') . "</p>";

// Also test shift information
$user_shift = getUserShiftEndTime($pdo, $user_id);
echo "<p>Shift Name: " . htmlspecialchars($user_shift['shift_name']) . "</p>";
echo "<p>Shift End Time: " . htmlspecialchars($user_shift['end_time']) . "</p>";
?>