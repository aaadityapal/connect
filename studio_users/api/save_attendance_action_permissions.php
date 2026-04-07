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
    $permStmt = $pdo->prepare("SELECT can_access FROM sidebar_permissions WHERE role = ? AND menu_id = 'attendance-action-permissions' LIMIT 1");
    $permStmt->execute([$currentRole]);
    $permRow = $permStmt->fetch(PDO::FETCH_ASSOC);
    $canAccess = $permRow && isset($permRow['can_access']) && (int)$permRow['can_access'] === 1;
    if (!$canAccess) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || !isset($payload['permissions']) || !is_array($payload['permissions'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit();
}

try {
    $tableExistsStmt = $pdo->query("SHOW TABLES LIKE 'attendance_action_permissions'");
    $hasTable = (bool)$tableExistsStmt->fetchColumn();

    if (!$hasTable) {
        echo json_encode([
            'success' => false,
            'message' => 'attendance_action_permissions table not found. Please run 2026_04_07_create_attendance_action_permissions_table.sql first.'
        ]);
        exit();
    }

    $pdo->beginTransaction();

    $upsert = $pdo->prepare(
        "INSERT INTO attendance_action_permissions (user_id, can_approve_attendance, can_reject_attendance, can_edit_attendance, granted_by, notes)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            can_approve_attendance = VALUES(can_approve_attendance),
            can_reject_attendance = VALUES(can_reject_attendance),
            can_edit_attendance = VALUES(can_edit_attendance),
            granted_by = VALUES(granted_by),
            notes = VALUES(notes)"
    );

    $grantedBy = (int)$_SESSION['user_id'];

    foreach ($payload['permissions'] as $rawUserId => $rawPerms) {
        $userId = (int)$rawUserId;
        if ($userId <= 0 || !is_array($rawPerms)) {
            continue;
        }

        $canApprove = ((int)($rawPerms['can_approve_attendance'] ?? 0)) === 1 ? 1 : 0;
        $canReject = ((int)($rawPerms['can_reject_attendance'] ?? 0)) === 1 ? 1 : 0;
        $canEdit = ((int)($rawPerms['can_edit_attendance'] ?? 0)) === 1 ? 1 : 0;

        $enabled = [];
        if ($canApprove) $enabled[] = 'approve';
        if ($canReject) $enabled[] = 'reject';
        if ($canEdit) $enabled[] = 'edit';

        $note = empty($enabled)
            ? 'Revoked attendance action permissions'
            : ('Granted attendance permissions: ' . implode(', ', $enabled));

        $upsert->execute([$userId, $canApprove, $canReject, $canEdit, $grantedBy, $note]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Attendance action permissions saved successfully'
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
