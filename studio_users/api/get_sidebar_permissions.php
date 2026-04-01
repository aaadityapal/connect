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
    // Collapse internal whitespace (handles tabs / multiple spaces)
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

try {
    // Get all roles
    $stmtRoles = $pdo->query("SELECT DISTINCT role FROM users WHERE role IS NOT NULL ORDER BY role ASC");
    $rolesRaw = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

    $roles = [];
    foreach ($rolesRaw as $r) {
        $nr = normalizeRoleName($r);
        if ($nr === '') {
            continue;
        }
        $roles[$nr] = true;
    }
    $roles = array_keys($roles);
    sort($roles, SORT_NATURAL | SORT_FLAG_CASE);

    // Get all permissions
    $stmtPerms = $pdo->query("SELECT * FROM sidebar_permissions");
    $perms = $stmtPerms->fetchAll(PDO::FETCH_ASSOC);

    // Structure data: roles -> menu_id -> can_access
    $structured = [];
    foreach ($roles as $role) {
        $structured[$role] = [];
    }
    
    foreach ($perms as $p) {
        $roleKey = normalizeRoleName($p['role'] ?? '');
        $menuId = trim((string)($p['menu_id'] ?? ''));
        if ($roleKey !== '' && $menuId !== '' && isset($structured[$roleKey])) {
            $structured[$roleKey][$menuId] = (int)$p['can_access'];
        }
    }

    echo json_encode([
        'success' => true,
        'roles' => $roles,
        'permissions' => $structured
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
