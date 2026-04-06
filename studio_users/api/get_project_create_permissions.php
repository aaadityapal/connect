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
    $permStmt = $pdo->prepare("SELECT can_access FROM sidebar_permissions WHERE role = ? AND menu_id = 'project-permissions' LIMIT 1");
    $permStmt->execute([$currentRole]);
    $permRow = $permStmt->fetch(PDO::FETCH_ASSOC);
    $canAccess = $permRow && isset($permRow['can_access']) && (int)$permRow['can_access'] === 1;
    if (!$canAccess) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
}

try {
    $tableExistsStmt = $pdo->query("SHOW TABLES LIKE 'project_permissions'");
    $hasTable = (bool)$tableExistsStmt->fetchColumn();

    if (!$hasTable) {
        echo json_encode([
            'success' => false,
            'message' => 'project_permissions table not found. Please run 2026_04_06_create_project_permissions_table.sql first.'
        ]);
        exit();
    }

    $columnCheck = $pdo->query("SHOW COLUMNS FROM project_permissions LIKE 'can_upload_substage_media'");
    $hasMediaColumn = (bool)$columnCheck->fetchColumn();
    if (!$hasMediaColumn) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing column can_upload_substage_media. Please run 2026_04_06_add_media_upload_permission_column.sql.'
        ]);
        exit();
    }

    $sql = "
        SELECT
            u.id,
            u.username,
            u.email,
            u.role,
            COALESCE(pp.can_create_project, 0) AS can_create_project,
            COALESCE(pp.can_upload_substage_media, 0) AS can_upload_substage_media
        FROM users u
        LEFT JOIN project_permissions pp ON pp.user_id = u.id
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
