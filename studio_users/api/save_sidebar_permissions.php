<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

function normalizeRoleName($role): string {
    $role = (string)$role;
    $role = trim($role);
    $role = preg_replace('/\s+/u', ' ', $role);
    return $role ?? '';
}

// Check if user is admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || strtolower($user['role']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit();
}

// Accept either:
// 1) { role: "...", permissions: { menuId: 0/1, ... } }
// 2) { permissions: { role: { menuId: 0/1, ... }, ... } }  (legacy)
$rolePermissionsMap = [];
if (isset($input['role']) && isset($input['permissions']) && is_array($input['permissions'])) {
    $role = (string)$input['role'];
    $rolePermissionsMap[$role] = $input['permissions'];
} elseif (isset($input['permissions']) && is_array($input['permissions'])) {
    $rolePermissionsMap = $input['permissions'];
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO sidebar_permissions (menu_id, role, can_access) VALUES (?, ?, ?) " .
        "ON DUPLICATE KEY UPDATE can_access = ?"
    );

    foreach ($rolePermissionsMap as $role => $menu_items) {
        if (!is_array($menu_items)) {
            continue;
        }

        $role = normalizeRoleName($role);
        if ($role === '') {
            continue;
        }
        // Prevent silent DB failures (column is VARCHAR(100))
        $roleLen = function_exists('mb_strlen') ? mb_strlen($role, 'UTF-8') : strlen($role);
        if ($roleLen > 100) {
            throw new RuntimeException('Role name too long to save');
        }

        foreach ($menu_items as $menu_id => $can_access) {
            $menu_id = trim((string)$menu_id);
            if ($menu_id === '') {
                continue;
            }
            $can_access = ((int)$can_access) === 1 ? 1 : 0;
            $stmt->execute([$menu_id, $role, $can_access, $can_access]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Permissions updated successfully']);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
