<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo->beginTransaction();

    // Update leave_request table
    $stmt = $pdo->prepare("
        UPDATE leave_request 
        SET status = :status,
            manager_approval = :manager_approval,
            manager_action_reason = :reason,
            manager_action_by = :manager_id,
            manager_action_at = :action_at,
            updated_at = NOW(),
            updated_by = :manager_id
        WHERE id = :leave_id
    ");

    $status = $data['action'] === 'accept' ? 'approved' : 'rejected';
    
    $stmt->execute([
        ':status' => $status,
        ':manager_approval' => $data['action'],
        ':reason' => $data['reason'],
        ':manager_id' => $_SESSION['user_id'],
        ':action_at' => $data['manager_action_at'],
        ':leave_id' => $data['leave_id']
    ]);

    $pdo->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Leave action error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to process leave action']);
}
?>