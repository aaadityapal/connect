<?php
// Turn off error reporting for the client
ini_set('display_errors', 0);
error_reporting(0);

// Start output buffering
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/db_connect.php';

// Clear any previous output
ob_clean();

// Set JSON headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Log incoming request for debugging
    error_log("Received request: " . file_get_contents('php://input'));

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['leave_id']) || !isset($input['action']) || !isset($input['action_reason'])) {
        throw new Exception('Invalid input data');
    }

    $leaveId = (int)$input['leave_id'];
    $action = strtolower(trim($input['action'])); // 'approve' or 'reject'
    $actionReason = trim($input['action_reason']);
    $currentTime = date('Y-m-d H:i:s');

    // Start transaction
    $pdo->beginTransaction();

    // First, check if the leave request exists and get its current status
    $checkStmt = $pdo->prepare("SELECT manager_approval, status FROM leave_request WHERE id = ?");
    $checkStmt->execute([$leaveId]);
    $leaveRequest = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$leaveRequest) {
        throw new Exception('Leave request not found');
    }

    // Update query for HR approval
    $sql = "UPDATE leave_request SET 
            hr_approval = :action,
            hr_action_reason = :reason,
            hr_action_by = :hr_by,
            hr_action_at = :action_time,
            status = :final_status,
            updated_at = :updated_at,
            updated_by = :updated_by
            WHERE id = :leave_id";

    // Set final status based on manager and HR approval
    $finalStatus = 'pending'; // Default status
    if ($leaveRequest['manager_approval'] === 'approved' && $action === 'approve') {
        $finalStatus = 'approved';
    } elseif ($action === 'reject') {
        $finalStatus = 'rejected';
    }

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':action' => $action,
        ':reason' => $actionReason,
        ':hr_by' => $_SESSION['user_id'],
        ':action_time' => $currentTime,
        ':final_status' => $finalStatus,
        ':updated_at' => $currentTime,
        ':updated_by' => $_SESSION['user_id'],
        ':leave_id' => $leaveId
    ]);

    if (!$result) {
        throw new Exception('Failed to update leave request');
    }

    // Commit transaction
    $pdo->commit();

    // Clear output buffer again before sending response
    ob_clean();

    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Leave request ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully',
        'status' => $finalStatus
    ]);

} catch (Exception $e) {
    // Rollback on error
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log error
    error_log("Leave action error: " . $e->getMessage());

    // Clear output buffer
    ob_clean();

    // Send error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error processing request: ' . $e->getMessage()
    ]);
}

// End output buffering and send response
ob_end_flush();
exit;
?>