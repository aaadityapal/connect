<?php
/**
 * Ajax handler to submit overtime requests
 * Used by the recent_time_widget.php when users punch out with overtime
 */

// Include database connection
require_once '../config/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Start session with looser restrictions to ensure session is read properly
session_start();

// Get POST data
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$overtime_id = isset($_POST['overtime_id']) ? intval($_POST['overtime_id']) : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : 'Requesting overtime approval for today.';

if ($user_id <= 0 || $overtime_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

// Find a manager with the required role
$manager_query = "SELECT id FROM users WHERE role IN ('Senior manager (Studio)', 'Senior manager (Site)') LIMIT 1";
$manager_result = $conn->query($manager_query);
if (!$manager_result || $manager_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'No manager found with the required role.']);
    exit;
}
$manager_id = $manager_result->fetch_assoc()['id'];

// Insert into overtime_notifications
$insert = $conn->prepare("INSERT INTO overtime_notifications (overtime_id, employee_id, manager_id, message, status, created_at) VALUES (?, ?, ?, ?, 'unread', NOW())");
if (!$insert) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$insert->bind_param("iiis", $overtime_id, $user_id, $manager_id, $message);
if (!$insert->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to insert overtime notification.']);
    exit;
}

// Update attendance table
$update = $conn->prepare("UPDATE attendance SET overtime_status = 'submitted' WHERE id = ?");
if (!$update) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$update->bind_param("i", $overtime_id);
if (!$update->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to update attendance overtime status.']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Overtime request submitted successfully.']); 