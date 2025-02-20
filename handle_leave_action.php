<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Verify if the user is a manager by checking their role
$manager_check_query = "SELECT role FROM users WHERE id = ? AND role = 'Senior Manager (Studio)' AND status = 'active'";
$stmt = $conn->prepare($manager_check_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Manager privileges required.']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

// Validate required fields
$required_fields = ['leave_id', 'action', 'manager_action_reason'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Update leave request
    $query = "UPDATE leave_request 
              SET status = ?, 
                  manager_approval = ?, 
                  manager_action_reason = ?, 
                  manager_action_by = ?, 
                  manager_action_at = NOW(),
                  updated_at = NOW()
              WHERE id = ?";

    $status = $data['action'] === 'approve' ? 'approved' : 'rejected';
    $manager_approval = $data['action'] === 'approve' ? 1 : 0;
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        'sisis',
        $status,
        $manager_approval,
        $data['manager_action_reason'],
        $_SESSION['user_id'],
        $data['leave_id']
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to update leave request");
    }

    // If no rows were affected, the leave request might not exist
    if ($stmt->affected_rows === 0) {
        throw new Exception("Leave request not found or already processed");
    }

    // Get leave request details for notification
    $leave_query = "SELECT lr.*, u.username, u.email 
                   FROM leave_request lr
                   JOIN users u ON lr.user_id = u.id
                   WHERE lr.id = ?";
    
    $leave_stmt = $conn->prepare($leave_query);
    $leave_stmt->bind_param('i', $data['leave_id']);
    $leave_stmt->execute();
    $leave_result = $leave_stmt->get_result();
    $leave_details = $leave_result->fetch_assoc();

    // Insert notification with correct columns
    $notification_query = "INSERT INTO notifications 
                          (user_id, type, reference_id, message, status, created_at) 
                          VALUES (?, 'leave_action', ?, ?, 'unread', NOW())";
    
    $message = "Your leave request from " . date('d M Y', strtotime($leave_details['start_date'])) . 
               " to " . date('d M Y', strtotime($leave_details['end_date'])) . 
               " has been " . $status . " by the manager.";
    
    $notification_stmt = $conn->prepare($notification_query);
    $notification_stmt->bind_param(
        'iis',
        $leave_details['user_id'],
        $data['leave_id'],
        $message
    );
    
    if (!$notification_stmt->execute()) {
        throw new Exception("Failed to create notification");
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Leave request has been successfully " . $status,
        'status' => $status
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close database connection
$conn->close();

/* 
// Email notification function (implement based on your email setup)
function sendLeaveActionEmail($to_email, $message) {
    // Your email sending logic here
    // Example: using PHPMailer or mail() function
}
*/
?> 