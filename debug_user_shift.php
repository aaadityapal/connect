<?php
// Debug script to check user ID 21's shift information
require_once 'config/db_connect.php';
require_once 'includes/functions/shift_functions.php';

$user_id = 21;

echo "<h1>Debug User Shift Information</h1>\n";
echo "<p>Checking shift information for user ID: $user_id</p>\n";

// Test getUserShiftEndTime function
echo "<h2>getUserShiftEndTime() Result</h2>\n";
$user_shift = getUserShiftEndTime($pdo, $user_id);
echo "<pre>" . print_r($user_shift, true) . "</pre>\n";

// Let's also check the raw database query to see what's actually in the database
echo "<h2>Raw Database Query Results</h2>\n";
try {
    $current_date = date('Y-m-d');
    
    $query = "SELECT us.*, s.shift_name, s.start_time, s.end_time 
              FROM user_shifts us
              JOIN shifts s ON us.shift_id = s.id
              WHERE us.user_id = :user_id 
              AND us.effective_from <= :current_date
              AND (us.effective_to IS NULL OR us.effective_to >= :current_date)
              ORDER BY us.effective_from DESC 
              LIMIT 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'user_id' => $user_id,
        'current_date' => $current_date
    ]);
    
    $raw_result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Raw query result:</p>\n";
    echo "<pre>" . print_r($raw_result, true) . "</pre>\n";
} catch (Exception $e) {
    echo "<p>Error in raw query: " . $e->getMessage() . "</p>\n";
}

// Let's also check all shifts for this user
echo "<h2>All Shift Assignments for User</h2>\n";
try {
    $query = "SELECT us.*, s.shift_name, s.start_time, s.end_time 
              FROM user_shifts us
              JOIN shifts s ON us.shift_id = s.id
              WHERE us.user_id = :user_id
              ORDER BY us.effective_from DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    
    $all_shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>All shift assignments:</p>\n";
    echo "<pre>" . print_r($all_shifts, true) . "</pre>\n";
} catch (Exception $e) {
    echo "<p>Error fetching all shifts: " . $e->getMessage() . "</p>\n";
}

// Let's also check all available shifts
echo "<h2>All Available Shifts</h2>\n";
try {
    $query = "SELECT * FROM shifts ORDER BY id";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $all_shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>All shifts in system:</p>\n";
    echo "<pre>" . print_r($all_shifts, true) . "</pre>\n";
} catch (Exception $e) {
    echo "<p>Error fetching all shifts: " . $e->getMessage() . "</p>\n";
}
?>