<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$policy_id = $data['policy_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$policy_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Update policy status
    $stmt = $pdo->prepare("
        UPDATE policy_documents 
        SET status = 'acknowledged' 
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$policy_id]);

    // Record acknowledgment
    $stmt = $pdo->prepare("
        INSERT INTO policy_acknowledgments (
            policy_id, 
            user_id, 
            acknowledged_at
        ) VALUES (?, ?, NOW())
    ");
    $stmt->execute([$policy_id, $user_id]);

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to acknowledge policy']);
}
?> 