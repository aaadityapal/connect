<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$currentRole = trim((string)($currentUser['role'] ?? ''));
$isAdmin = strtolower($currentRole) === 'admin';

if (!$isAdmin) {
    $permStmt = $pdo->prepare("SELECT can_access FROM sidebar_permissions WHERE role = ? AND menu_id = 'manual-leave-permissions' LIMIT 1");
    $permStmt->execute([$currentRole]);
    $permRow = $permStmt->fetch(PDO::FETCH_ASSOC);
    $canAccess = $permRow && isset($permRow['can_access']) && (int)$permRow['can_access'] === 1;
    if (!$canAccess) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $permissions = $input['permissions'] ?? [];

    if (empty($permissions)) {
        echo json_encode(['success' => true, 'message' => 'No changes to save.']);
        exit();
    }

    $pdo->beginTransaction();

    $upsertStmt = $pdo->prepare("
        INSERT INTO manual_leave_permissions (user_id, can_add_manual_leave)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE can_add_manual_leave = VALUES(can_add_manual_leave)
    ");

    foreach ($permissions as $userId => $perms) {
        $canAdd = (int)($perms['can_add_manual_leave'] ?? 0);
        $upsertStmt->execute([(int)$userId, $canAdd]);
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Permissions saved successfully.']);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
