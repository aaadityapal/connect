<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['HR', 'Senior Manager (Studio)'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $leaveId = $_POST['leave_id'] ?? null;
    $status = $_POST['status'] ?? null;
    $remarks = $_POST['remarks'] ?? '';
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    if (!$leaveId || !$status) {
        throw new Exception('Missing required parameters');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // First, verify if the leave belongs to a user under the Senior Manager
    $checkQuery = "
        SELECT l.*, u.reporting_manager 
        FROM leaves l
        JOIN users u ON l.user_id = u.id
        WHERE l.id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$leaveId]);
    $leave = $checkStmt->fetch();

    if (!$leave) {
        throw new Exception('Leave not found');
    }

    // For Senior Manager, check if the user reports to them
    if ($role === 'Senior Manager (Studio)' && $leave['reporting_manager'] != $userId) {
        throw new Exception('Unauthorized: User does not report to you');
    }

    // Update based on role
    if ($role === 'Senior Manager (Studio)') {
        $updateStmt = $pdo->prepare("
            UPDATE leaves 
            SET manager_status = ?,
                manager_approved_by = ?,
                manager_remarks = ?,
                manager_action_date = NOW()
            WHERE id = ?");
        $updateStmt->execute([$status, $userId, $remarks, $leaveId]);
    } else if ($role === 'HR') {
        $updateStmt = $pdo->prepare("
            UPDATE leaves 
            SET hr_status = ?,
                hr_approved_by = ?,
                hr_remarks = ?,
                hr_action_date = NOW()
            WHERE id = ?");
        $updateStmt->execute([$status, $userId, $remarks, $leaveId]);
    }

    // Update final status if both HR and Manager have approved
    $finalStatusStmt = $pdo->prepare("
        UPDATE leaves 
        SET status = CASE 
            WHEN manager_status = 'Approved' AND hr_status = 'Approved' THEN 'Approved'
            WHEN manager_status = 'Rejected' OR hr_status = 'Rejected' THEN 'Rejected'
            ELSE 'Pending'
        END
        WHERE id = ?");
    $finalStatusStmt->execute([$leaveId]);

    // Commit transaction
    $pdo->commit();

    // Send email notification
    sendLeaveStatusNotification($leave['user_id'], $status, $role);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function sendLeaveStatusNotification($userId, $status, $approverRole) {
    // Implement your email notification logic here
}

if (isset($_POST['leave_id']) && isset($_POST['action'])) {
    $leave_id = $_POST['leave_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        if (updateLeaveBalance($pdo, $leave_id, 'Approved')) {
            $_SESSION['success'] = "Leave approved and balance updated successfully.";
        } else {
            $_SESSION['error'] = "Error updating leave balance.";
        }
    } else if ($action === 'reject') {
        // Handle rejection
        $stmt = $pdo->prepare("UPDATE leaves SET status = 'Rejected' WHERE id = ?");
        $stmt->execute([$leave_id]);
        $_SESSION['success'] = "Leave application rejected.";
    }
    
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}
?>
