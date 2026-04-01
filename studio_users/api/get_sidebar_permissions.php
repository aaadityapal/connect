<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

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
    $stmtRoles = $pdo->query("SELECT DISTINCT role FROM users ORDER BY role ASC");
    $roles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

    // Get all permissions
    $stmtPerms = $pdo->query("SELECT * FROM sidebar_permissions");
    $perms = $stmtPerms->fetchAll(PDO::FETCH_ASSOC);

    // Structure data: roles -> menu_id -> can_access
    $structured = [];
    foreach ($roles as $role) {
        $structured[$role] = [];
    }
    
    foreach ($perms as $p) {
        if (isset($structured[$p['role']])) {
            $structured[$p['role']][$p['menu_id']] = (int)$p['can_access'];
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
