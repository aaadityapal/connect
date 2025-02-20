<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['leave_id']) || !isset($data['action']) || !isset($data['reason'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Update leave_request table
    $query = "UPDATE leave_request SET 
                hr_approval = :action,
                hr_action_reason = :reason,
                hr_action_by = :action_by,
                hr_action_at = NOW(),
                status = :status,
                updated_at = NOW(),
                updated_by = :updated_by
              WHERE id = :leave_id";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':action' => $data['action'],
        ':reason' => $data['reason'],
        ':action_by' => $_SESSION['user_id'],
        ':status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
        ':updated_by' => $_SESSION['user_id'],
        ':leave_id' => $data['leave_id']
    ]);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Leave request ' . ($data['action'] === 'approve' ? 'approved' : 'rejected') . ' successfully'
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    error_log("Leave action error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?> 