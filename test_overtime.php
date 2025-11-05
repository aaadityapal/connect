<?php
session_start();
require_once 'config/db_connect.php';

// Set a test user ID for debugging
$_SESSION['user_id'] = 1;

// Simulate the data that would be sent
$testData = [
    'attendance_id' => 1209,
    'date' => '2025-11-01',
    'shift_end_time' => '18:00:00',
    'punch_out_time' => '20:00:00',
    'overtime_hours' => 2.0,
    'work_report' => 'Test work report',
    'overtime_description' => 'Test overtime description with at least fifteen words to meet the requirement',
    'manager_id' => 2
];

// Log the test data
error_log('Test data: ' . print_r($testData, true));

// Try to insert the data directly
try {
    $query = "INSERT INTO overtime_requests (
                user_id, 
                attendance_id, 
                date, 
                shift_end_time, 
                punch_out_time, 
                overtime_hours, 
                work_report, 
                overtime_description, 
                manager_id, 
                status
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        $_SESSION['user_id'],
        $testData['attendance_id'],
        $testData['date'],
        $testData['shift_end_time'],
        $testData['punch_out_time'],
        $testData['overtime_hours'],
        $testData['work_report'],
        $testData['overtime_description'],
        $testData['manager_id']
    ]);
    
    if ($result) {
        echo "Test successful: Overtime request inserted";
    } else {
        echo "Test failed: " . print_r($stmt->errorInfo(), true);
    }
} catch (Exception $e) {
    echo "Test failed with exception: " . $e->getMessage();
}
?>