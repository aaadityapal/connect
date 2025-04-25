<?php
session_start();
// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Senior Manager (Studio)') {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
require_once 'config/db_connect.php';

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate required parameters
if (!isset($_POST['overtime_id']) || !isset($_POST['action_type']) || !isset($_POST['remarks'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Get and sanitize input
$overtime_id = mysqli_real_escape_string($conn, $_POST['overtime_id']);
$action_type = mysqli_real_escape_string($conn, $_POST['action_type']);
$remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
$manager_id = $_SESSION['user_id'];

// Validate action type
if ($action_type !== 'approve' && $action_type !== 'reject') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid action type']);
    exit();
}

// Check if record exists and is pending
$check_query = "SELECT * FROM attendance WHERE id = '$overtime_id' AND (overtime IS NULL OR overtime = 'pending')";
$check_result = mysqli_query($conn, $check_query);

if (!$check_result || mysqli_num_rows($check_result) == 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Overtime record not found or already processed']);
    exit();
}

// Set overtime status based on action type
$status = ($action_type === 'approve') ? 'approved' : 'rejected';

// Update the overtime record
$update_query = "UPDATE attendance SET 
                overtime = '$status', 
                remarks = '$remarks', 
                modified_by = '$manager_id', 
                modified_at = NOW() 
                WHERE id = '$overtime_id'";

$update_result = mysqli_query($conn, $update_query);

if ($update_result) {
    // Get user_id for notification
    $attendance_data = mysqli_fetch_assoc($check_result);
    $user_id = $attendance_data['user_id'];
    
    // Create notification for the employee
    $notification_title = "Overtime " . ucfirst($status);
    $notification_message = "Your overtime request for " . date('d M, Y', strtotime($attendance_data['date'])) . 
                         " has been " . $status . " by " . $_SESSION['username'] . ".";
    
    // Insert notification (assuming you have a notifications table)
    $notification_query = "INSERT INTO notifications (user_id, title, message, created_at)
                        VALUES ('$user_id', '$notification_title', '$notification_message', NOW())";
    
    mysqli_query($conn, $notification_query);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Overtime has been ' . $status . ' successfully']);
    exit();
} else {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit();
}
?>