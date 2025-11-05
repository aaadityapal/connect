<?php
// Debug script to check user ID 1's shift information
require_once 'config/db_connect.php';
require_once 'includes/functions/shift_functions.php';

$user_id = 1;

echo "<h1>Debug User 1 Shift Information</h1>\n";
echo "<p>Checking shift information for user ID: $user_id</p>\n";

// Test getUserShiftEndTime function
echo "<h2>getUserShiftEndTime() Result</h2>\n";
$user_shift = getUserShiftEndTime($pdo, $user_id);
echo "<pre>" . print_r($user_shift, true) . "</pre>\n";

// Let's also check all shifts for user ID 1
echo "<h2>All Shift Assignments for User 1</h2>\n";
try {
    $query = "SELECT us.*, s.shift_name, s.start_time, s.end_time 
              FROM user_shifts us
              JOIN shifts s ON us.shift_id = s.id
              WHERE us.user_id = ?
              ORDER BY us.effective_from DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    
    $all_shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>All shift assignments:</p>\n";
    echo "<pre>" . print_r($all_shifts, true) . "</pre>\n";
} catch (Exception $e) {
    echo "<p>Error fetching all shifts: " . $e->getMessage() . "</p>\n";
}
?>