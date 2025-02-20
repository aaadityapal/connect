<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Studio Manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $leaveId = $_POST['leave_id'] ?? null;
    $status = $_POST['status'] ?? null;
    $remarks = $_POST['remarks'] ?? '';

    if (!$leaveId || !$status) {
        throw new Exception('Missing required parameters');
    }

    // Get Studio Manager's ID
    $managerStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $managerStmt->execute([$_SESSION['username']]);
    $managerId = $managerStmt->fetchColumn();

    // Begin transaction
    $pdo->beginTransaction();

    // Update leave status
    $updateStmt = $pdo->prepare("
        UPDATE leaves 
        SET studio_manager_status = ?,
            studio_manager_approved_by = ?,
            studio_manager_remarks = ?,
            studio_manager_action_date = NOW()
        WHERE id = ?
    ");

    $updateStmt->execute([$status, $managerId, $remarks, $leaveId]);

    // Commit transaction
    $pdo->commit();

    // Send email notification
    // ... (implement email notification logic)

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
