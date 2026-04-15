<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // Ensure mapping table exists.
    $pdo->exec("CREATE TABLE IF NOT EXISTS geofence_approval_mapping (
        id INT AUTO_INCREMENT PRIMARY KEY,
        manager_id INT NOT NULL,
        employee_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_geofence_manager_employee (manager_id, employee_id),
        KEY idx_geofence_manager (manager_id),
        KEY idx_geofence_employee (employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 1) Fetch all active users.
    $usersStmt = $pdo->query("SELECT id, username AS name, position, role
                              FROM users
                              WHERE deleted_at IS NULL AND status = 'Active'
                              ORDER BY username ASC");
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2) Fetch existing geofence approval mappings.
    $mapStmt = $pdo->query("SELECT employee_id AS subordinate_id, manager_id FROM geofence_approval_mapping");
    $mappings = $mapStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users,
        'mappings' => $mappings
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>