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

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || !isset($payload['permissions']) || !is_array($payload['permissions'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit();
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

    $pdo->beginTransaction();

    $upsert = $pdo->prepare(
        "INSERT INTO project_permissions (user_id, can_create_project, can_upload_substage_media, granted_by, notes)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             can_create_project = VALUES(can_create_project),
             can_upload_substage_media = VALUES(can_upload_substage_media),
             granted_by = VALUES(granted_by),
             notes = VALUES(notes)"
    );

    $grantedBy = (int)$_SESSION['user_id'];

    foreach ($payload['permissions'] as $rawUserId => $rawPerms) {
        $userId = (int)$rawUserId;
        if ($userId <= 0) {
            continue;
        }

        $canCreate = 0;
        $canUploadMedia = 0;

        if (is_array($rawPerms)) {
            $canCreate = ((int)($rawPerms['can_create_project'] ?? 0)) === 1 ? 1 : 0;
            $canUploadMedia = ((int)($rawPerms['can_upload_substage_media'] ?? 0)) === 1 ? 1 : 0;
        } else {
            // backward compatibility with old payload shape
            $canCreate = ((int)$rawPerms) === 1 ? 1 : 0;
        }

        if ($canCreate === 1 && $canUploadMedia === 1) {
            $note = 'Granted create + media upload via Sidebar Role Access';
        } elseif ($canCreate === 1) {
            $note = 'Granted create project via Sidebar Role Access';
        } elseif ($canUploadMedia === 1) {
            $note = 'Granted media upload via Sidebar Role Access';
        } else {
            $note = 'Revoked project permissions via Sidebar Role Access';
        }

        $upsert->execute([$userId, $canCreate, $canUploadMedia, $grantedBy, $note]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Project permissions saved successfully'
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
