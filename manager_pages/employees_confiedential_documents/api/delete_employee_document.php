<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$documentId = isset($data['document_id']) ? (int)$data['document_id'] : 0;

if ($documentId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid document id'
    ]);
    exit();
}

$actorUserId = (int)($_SESSION['user_id'] ?? 0);
$actorUsername = trim((string)($_SESSION['username'] ?? 'System'));
$actorRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$isAdmin = ($actorRole === 'admin');

try {
    require_once '../../../config/db_connect.php';

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id, employee_id, uploaded_by, visibility_mode, visibility_user_ids, document_type_key, document_type_label, document_name, document_date, expiry_date, notes, file_original_name, file_stored_name, file_path, file_size, file_mime, is_deleted, created_at
         FROM employee_confiedential_documents
         WHERE id = :id
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([':id' => $documentId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Document not found'
        ]);
        exit();
    }

    if ((int)($doc['is_deleted'] ?? 0) === 1) {
        $pdo->rollBack();
        echo json_encode([
            'success' => true,
            'message' => 'Document is already hidden',
            'document_id' => (int)$doc['id'],
            'employee_id' => (int)$doc['employee_id']
        ]);
        exit();
    }

    $canDelete = $isAdmin || $actorUserId === (int)$doc['uploaded_by'];
    if (!$isAdmin) {
        $permStmt = $pdo->prepare('SELECT can_delete FROM confiedential_document_permissions WHERE user_id = :uid LIMIT 1');
        $permStmt->execute([':uid' => $actorUserId]);
        $perm = $permStmt->fetch(PDO::FETCH_ASSOC);
        $canDeleteByPolicy = $perm ? (int)($perm['can_delete'] ?? 0) : 0;
        $canDelete = $canDelete || ($canDeleteByPolicy === 1);
    }

    if (!$canDelete) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'You are not allowed to delete this document'
        ]);
        exit();
    }

    $hideStmt = $pdo->prepare(
        "UPDATE employee_confiedential_documents
         SET is_deleted = 1, deleted_at = NOW(), deleted_by = :deleted_by
         WHERE id = :id
         LIMIT 1"
    );
    $hideStmt->execute([
        ':deleted_by' => $actorUserId,
        ':id' => $documentId,
    ]);

    $metadata = [
        'module' => 'employees_confiedential_documents',
        'event' => 'document_hidden',
        'document' => [
            'id' => (int)$doc['id'],
            'employee_id' => (int)$doc['employee_id'],
            'uploaded_by' => (int)$doc['uploaded_by'],
            'document_type_key' => (string)$doc['document_type_key'],
            'document_type_label' => (string)$doc['document_type_label'],
            'document_name' => (string)$doc['document_name'],
            'document_date' => (string)$doc['document_date'],
            'expiry_date' => $doc['expiry_date'],
            'visibility_mode' => (string)$doc['visibility_mode'],
            'visibility_user_ids' => (string)($doc['visibility_user_ids'] ?? ''),
            'notes' => (string)($doc['notes'] ?? ''),
            'file_original_name' => (string)$doc['file_original_name'],
            'file_stored_name' => (string)$doc['file_stored_name'],
            'file_path' => (string)$doc['file_path'],
            'file_size' => (int)$doc['file_size'],
            'file_mime' => (string)$doc['file_mime'],
            'created_at' => (string)$doc['created_at'],
            'hidden_at' => date('Y-m-d H:i:s'),
            'hidden_by' => $actorUserId,
        ],
        'actor' => [
            'user_id' => $actorUserId,
            'username' => $actorUsername,
            'role' => $actorRole,
        ],
    ];

    $description = sprintf(
        "Hidden confidential document '%s' (%s) for employee ID %d",
        (string)$doc['document_name'],
        (string)$doc['document_type_label'],
        (int)$doc['employee_id']
    );

    $logStmt = $pdo->prepare(
        "INSERT INTO global_activity_logs
            (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read, is_dismissed)
         VALUES
            (:user_id, :action_type, :entity_type, :entity_id, :description, :metadata, NOW(), 0, 0)"
    );

    $logStmt->execute([
        ':user_id' => $actorUserId,
        ':action_type' => 'employee_confidential_document_hidden',
        ':entity_type' => 'employee_confidential_document',
        ':entity_id' => (int)$doc['id'],
        ':description' => $description,
        ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Document hidden successfully',
        'document_id' => (int)$doc['id'],
        'employee_id' => (int)$doc['employee_id']
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete document'
    ]);
}
