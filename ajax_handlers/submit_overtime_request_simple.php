<?php
/**
 * Simplified version of submit_overtime_request.php for testing
 */

// Set header to return JSON
header('Content-Type: application/json');

// Get parameters
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 1;
$date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
$overtime_hours = isset($_POST['overtime_hours']) ? $_POST['overtime_hours'] : '01:30:00';
$shift_end_time = isset($_POST['shift_end_time']) ? $_POST['shift_end_time'] : '18:00:00';
$overtime_reason = isset($_POST['overtime_reason']) ? $_POST['overtime_reason'] : 'Testing overtime request';

// In a real implementation, this would:
// 1. Update the attendance table with overtime_status = 'submitted'
// 2. Insert a record into overtime_notifications table
// 3. Send notification to manager

// Return success response for testing
echo json_encode([
    'success' => true,
    'message' => 'Overtime request submitted successfully',
    'notification_id' => rand(1000, 9999), // Simulated notification ID
    'data' => [
        'user_id' => $user_id,
        'date' => $date,
        'overtime_hours' => $overtime_hours,
        'shift_end_time' => $shift_end_time,
        'overtime_reason' => $overtime_reason,
        'status' => 'submitted',
        'timestamp' => date('Y-m-d H:i:s')
    ]
]);
?> 