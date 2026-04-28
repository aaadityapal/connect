<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$permissions = isset($data['permissions']) && is_array($data['permissions']) ? $data['permissions'] : [];

try {
    require_once '../../../config/db_connect.php';

    $actorId = (int)($_SESSION['user_id'] ?? 0);
    $actorStmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
    $actorStmt->execute([$actorId]);
    $actor = $actorStmt->fetch(PDO::FETCH_ASSOC);

    if (!$actor || strtolower(trim((string)($actor['role'] ?? ''))) !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit();
    }

    $pdo->beginTransaction();

    $upsert = $pdo->prepare(
        "INSERT INTO confiedential_document_permissions (user_id, can_upload, can_delete, updated_by)
         VALUES (:user_id, :can_upload, :can_delete, :updated_by)
         ON DUPLICATE KEY UPDATE
            can_upload = VALUES(can_upload),
            can_delete = VALUES(can_delete),
            updated_by = VALUES(updated_by),
            updated_at = NOW()"
    );

    foreach ($permissions as $item) {
        $userId = isset($item['user_id']) ? (int)$item['user_id'] : 0;
        if ($userId <= 0) {
            continue;
        }

        $canUpload = !empty($item['can_upload']) ? 1 : 0;
        $canDelete = !empty($item['can_delete']) ? 1 : 0;

        $upsert->execute([
            ':user_id' => $userId,
            ':can_upload' => $canUpload,
            ':can_delete' => $canDelete,
            ':updated_by' => $actorId,
        ]);
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Permissions saved']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save permissions']);
}
