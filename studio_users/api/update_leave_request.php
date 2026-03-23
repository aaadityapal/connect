<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$leave_id = $data['id'] ?? null;
$reason = $data['reason'] ?? '';

if (!$leave_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    // 1. Double check the status and ownership
    $stmt = $pdo->prepare("SELECT status FROM leave_request WHERE id = ? AND user_id = ?");
    $stmt->execute([$leave_id, $user_id]);
    $status = $stmt->fetchColumn();

    if ($status === false) {
        throw new Exception("Leave request not found");
    }

    if ($status !== 'pending') {
        throw new Exception("Only pending requests can be updated");
    }

    // 2. Update the reason
    $stmtUpdate = $pdo->prepare("UPDATE leave_request SET reason = ? WHERE id = ?");
    $stmtUpdate->execute([$reason, $leave_id]);

    // ─── Activity Logging ──────────────────
    try {
        $reasonPreview = mb_substr($reason, 0, 60);
        if (mb_strlen($reason) > 60) $reasonPreview .= "...";
        
        $logDesc = "Updated reason for leave #$leave_id: \"$reasonPreview\"";
        
        $logStmt = $pdo->prepare("INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read) VALUES (?, 'leave_edited', 'leave', ?, ?, ?, NOW(), 0)");
        $logStmt->execute([$user_id, $leave_id, $logDesc, json_encode(['new_reason' => $reason])]);
    } catch (Exception $e) { }

    echo json_encode(['success' => true, 'message' => 'Request reason updated successfully!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
