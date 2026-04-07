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

    $sql = "
        SELECT
            u.id,
            u.username,
            u.email,
            u.role,
            COALESCE(aap.can_approve_attendance, 0) AS can_approve_attendance,
            COALESCE(aap.can_reject_attendance, 0) AS can_reject_attendance,
            COALESCE(aap.can_edit_attendance, 0) AS can_edit_attendance
        FROM users u
        LEFT JOIN attendance_action_permissions aap ON aap.user_id = u.id
        WHERE LOWER(COALESCE(u.status, '')) = 'active'
        ORDER BY u.username ASC
    ";

    $users = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
