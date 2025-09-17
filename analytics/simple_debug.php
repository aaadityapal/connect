<?php
// Simple debug script to test leave calculation
require_once '../config/db_connect.php';

$user_id = 1; // Bhuvnesh
$month_start = '2024-08-01';
$month_end = '2024-08-31';

echo "DEBUG: Testing Leave Calculation for User ID: $user_id\n";
echo "Month: $month_start to $month_end\n\n";

// Test 1: Get all leave records
echo "=== ALL LEAVE RECORDS ===\n";
$query1 = "SELECT lr.id, lr.leave_type, lr.duration_type, lr.duration, lr.start_date, lr.end_date, 
           lt.name as leave_type_name
           FROM leave_request lr
           LEFT JOIN leave_types lt ON lr.leave_type = lt.id
           WHERE lr.user_id = ? AND lr.status = 'approved'
           AND (lr.start_date <= ? AND lr.end_date >= ?)";
$stmt1 = $pdo->prepare($query1);
$stmt1->execute([$user_id, $month_end, $month_start]);
$leaves = $stmt1->fetchAll(PDO::FETCH_ASSOC);

foreach ($leaves as $leave) {
    echo "ID: {$leave['id']}\n";
    echo "Type: '{$leave['leave_type_name']}' (ID: {$leave['leave_type']})\n";
    echo "Duration Type: {$leave['duration_type']}\n";
    echo "Duration: {$leave['duration']}\n";
    echo "Dates: {$leave['start_date']} to {$leave['end_date']}\n";
    echo "---\n";
}

// Test 2: Check specific pattern matching
echo "\n=== PATTERN MATCHING TEST ===\n";
foreach ($leaves as $leave) {
    $name_lower = strtolower($leave['leave_type_name']);
    echo "Leave: '{$leave['leave_type_name']}'\n";
    echo "Lowercase: '$name_lower'\n";
    echo "Contains 'half day': " . (strpos($name_lower, 'half day') !== false ? 'YES' : 'NO') . "\n";
    echo "Contains 'short': " . (strpos($name_lower, 'short') !== false ? 'YES' : 'NO') . "\n";
    echo "Contains 'compensate': " . (strpos($name_lower, 'compensate') !== false ? 'YES' : 'NO') . "\n";
    echo "---\n";
}

// Test 3: Simple deduction calculation
echo "\n=== SIMPLE DEDUCTION CALCULATION ===\n";
$query2 = "SELECT 
           SUM(CASE 
               WHEN LOWER(lt.name) LIKE '%half day%' AND lr.duration_type = 'half_day' THEN 0.5
               WHEN LOWER(lt.name) LIKE '%half day%' AND lr.duration_type = 'full_day' THEN 1.0
               ELSE 0
           END) as half_day_deduction
           FROM leave_request lr
           LEFT JOIN leave_types lt ON lr.leave_type = lt.id
           WHERE lr.user_id = ? AND lr.status = 'approved'
           AND (lr.start_date <= ? AND lr.end_date >= ?)";
$stmt2 = $pdo->prepare($query2);
$stmt2->execute([$user_id, $month_end, $month_start]);
$result = $stmt2->fetch(PDO::FETCH_ASSOC);

echo "Half Day Deduction: " . ($result['half_day_deduction'] ?? 0) . "\n";

echo "\nDone.\n";
?>