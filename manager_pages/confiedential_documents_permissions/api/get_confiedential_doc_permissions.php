<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

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

    $sql = "SELECT u.id, u.username, u.email, u.role,
                   COALESCE(p.can_upload, 0) AS can_upload,
                   COALESCE(p.can_delete, 0) AS can_delete,
                   p.updated_at
            FROM users u
            LEFT JOIN confiedential_document_permissions p ON p.user_id = u.id
            WHERE LOWER(TRIM(u.status)) = 'active'
            ORDER BY u.username ASC";

    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$u) {
        $u['id'] = (int)$u['id'];
        $u['can_upload'] = (int)$u['can_upload'];
        $u['can_delete'] = (int)$u['can_delete'];
    }
    unset($u);

    echo json_encode(['success' => true, 'users' => $users]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch permissions']);
}
