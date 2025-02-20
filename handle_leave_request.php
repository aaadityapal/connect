<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and has HR or Senior Manager (Studio) privileges
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'HR' && $_SESSION['role'] !== 'Senior Manager (Studio)')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$leave_id = $data['leave_id'] ?? null;
$action = $data['action'] ?? null;
$reason = $data['reason'] ?? null;

if (!$leave_id || !$action || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    $isHR = $_SESSION['role'] === 'HR';
    $status = ($action === 'approve') ? 'approved' : 'rejected';

    // Prepare the query based on user role
    if ($isHR) {
        $query = "UPDATE leave_request SET 
                    hr_approval = :status,
                    hr_action_reason = :reason,
                    hr_action_by = :user_id,
                    hr_action_at = NOW(),
                    status = :status,
                    updated_at = NOW(),
                    updated_by = :user_id
                  WHERE id = :leave_id";
    } else {
        $query = "UPDATE leave_request SET 
                    manager_approval = :status,
                    manager_action_reason = :reason,
                    manager_action_by = :user_id,
                    manager_action_at = NOW(),
                    status = CASE 
                        WHEN :status = 'rejected' THEN 'rejected'
                        ELSE 'pending'  -- If manager approves, status stays pending for HR
                    END,
                    updated_at = NOW(),
                    updated_by = :user_id
                  WHERE id = :leave_id";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':status' => $status,
        ':reason' => $reason,
        ':user_id' => $_SESSION['user_id'],
        ':leave_id' => $leave_id
    ]);

    // If approved and it's HR, you might want to update other related tables
    if ($action === 'approve' && $isHR) {
        // Get leave details
        $leaveQuery = "SELECT user_id, start_date, end_date, duration 
                      FROM leave_request 
                      WHERE id = :leave_id";
        $leaveStmt = $pdo->prepare($leaveQuery);
        $leaveStmt->execute([':leave_id' => $leave_id]);
        $leaveDetails = $leaveStmt->fetch(PDO::FETCH_ASSOC);

        // Here you can add additional logic for approved leaves
        // For example, updating leave balance, creating calendar events, etc.
    }

    // Commit transaction
    $pdo->commit();

    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Leave request has been ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    error_log("Leave request error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing the leave request'
    ]);
} 