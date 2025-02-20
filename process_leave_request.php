<?php
session_start();
require_once 'config/db_connect.php'; // Adjust path as needed

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['HR', 'Manager'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Function to validate date format
function isValidDate($date) {
    return (bool)strtotime($date);
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['leave_id']) || !isset($data['action']) || !isset($data['action_reason'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

$leave_id = intval($data['leave_id']);
$action = strtolower($data['action']); // 'approve' or 'reject'
$action_reason = trim($data['action_reason']);
$user_role = $_SESSION['role'];
$current_user_id = $_SESSION['user_id'];
$current_time = date('Y-m-d H:i:s');

try {
    // Start transaction
    $pdo->beginTransaction();

    // First, get the current leave request details
    $stmt = $pdo->prepare("SELECT * FROM leave_request WHERE id = ?");
    $stmt->execute([$leave_id]);
    $leave_request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave_request) {
        throw new Exception('Leave request not found');
    }

    // Prepare the update query based on role
    if ($user_role === 'Manager') {
        // Update manager approval fields
        $update_query = "
            UPDATE leave_request 
            SET 
                manager_approval = ?,
                manager_action_reason = ?,
                manager_action_by = ?,
                manager_action_at = ?,
                updated_at = ?,
                updated_by = ?,
                status = CASE 
                    WHEN ? = 'approve' THEN 'pending_hr'
                    ELSE 'rejected'
                END
            WHERE id = ?";
        
        $params = [
            $action === 'approve' ? 'approved' : 'rejected',
            $action_reason,
            $current_user_id,
            $current_time,
            $current_time,
            $current_user_id,
            $action,
            $leave_id
        ];

    } elseif ($user_role === 'HR') {
        // Check if manager has approved (if required)
        if ($leave_request['manager_approval'] !== 'approved' && $action === 'approve') {
            throw new Exception('Manager approval required before HR approval');
        }

        // Update HR approval fields
        $update_query = "
            UPDATE leave_request 
            SET 
                hr_approval = ?,
                hr_action_reason = ?,
                hr_action_by = ?,
                hr_action_at = ?,
                updated_at = ?,
                updated_by = ?,
                status = ?,
                action_reason = ?,
                action_by = ?,
                action_at = ?,
                action_comments = ?
            WHERE id = ?";
        
        $params = [
            $action === 'approve' ? 'approved' : 'rejected',
            $action_reason,
            $current_user_id,
            $current_time,
            $current_time,
            $current_user_id,
            $action === 'approve' ? 'approved' : 'rejected',
            $action_reason,
            $current_user_id,
            $current_time,
            $action_reason,
            $leave_id
        ];
    }

    // Execute the update
    $stmt = $pdo->prepare($update_query);
    $stmt->execute($params);

    // If everything is successful, commit the transaction
    $pdo->commit();

    // Send notification to employee (you can implement this based on your needs)
    // sendLeaveRequestNotification($leave_request['user_id'], $action, $action_reason);

    echo json_encode([
        'success' => true,
        'message' => 'Leave request ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully',
        'data' => [
            'leave_id' => $leave_id,
            'status' => $action === 'approve' ? 
                ($user_role === 'Manager' ? 'pending_hr' : 'approved') : 
                'rejected'
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    error_log("Leave request processing error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error processing leave request: ' . $e->getMessage()
    ]);
}

// Optional: Add a function to send notifications
function sendLeaveRequestNotification($user_id, $action, $reason) {
    // Implement your notification logic here
    // This could be email, SMS, or internal system notification
}
?> 