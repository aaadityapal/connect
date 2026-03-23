<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // 1. Fetch all active users
    $query = "SELECT id, username as name, position, role FROM users 
              WHERE deleted_at IS NULL AND status = 'Active' 
              ORDER BY username ASC";
    
    $stmt = $pdo->query($query);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch existing mappings from the new dedicated leave approval table
    $stmt = $pdo->query("SELECT employee_id as subordinate_id, manager_id FROM leave_approval_mapping");
    $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users,
        'mappings' => $mappings
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
