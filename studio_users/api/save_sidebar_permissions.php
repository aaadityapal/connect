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

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['permissions'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$permissions = $input['permissions'];

try {
    $pdo->beginTransaction();

    foreach ($permissions as $role => $menu_items) {
        foreach ($menu_items as $menu_id => $can_access) {
            $stmt = $pdo->prepare("INSERT INTO sidebar_permissions (menu_id, role, can_access) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE can_access = ?");
            $stmt->execute([$menu_id, $role, $can_access, $can_access]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Permissions updated successfully']);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
