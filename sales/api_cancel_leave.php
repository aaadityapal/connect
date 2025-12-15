<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}

// Database connection
require_once '../config/db_connect.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;

    if (!$leave_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Leave request ID is required']);
        exit;
    }

    // Verify the leave request belongs to the user and is pending
    $stmt = $pdo->prepare("SELECT id, status FROM leave_request WHERE id = ? AND user_id = ?");
    $stmt->execute([$leave_id, $user_id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Leave request not found']);
        exit;
    }

    if ($leave['status'] !== 'pending') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only pending leave requests can be cancelled']);
        exit;
    }

    // Update leave request status to rejected (cancelled by user)
    // Note: Using 'rejected' status as 'cancelled' may not be in the ENUM
    $sql = "UPDATE leave_request 
            SET status = 'rejected', 
                updated_at = NOW(), 
                updated_by = ?,
                action_reason = 'Cancelled by user',
                action_by = ?,
                action_at = NOW()
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$user_id, $user_id, $leave_id]);

    if ($result) {
        // Log the activity (optional - won't fail if table doesn't exist)
        try {
            $log_sql = "INSERT INTO activity_logs (user_id, action, description, created_at) 
                       VALUES (?, 'leave_request_cancelled', ?, NOW())";
            $log_stmt = $pdo->prepare($log_sql);
            $log_stmt->execute([
                $user_id,
                "Leave request #$leave_id cancelled by user"
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the request
            error_log("Failed to log activity (table may not exist): " . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Leave request cancelled successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to cancel leave request']);
    }

} catch (PDOException $e) {
    error_log("Database error in api_cancel_leave.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in api_cancel_leave.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>