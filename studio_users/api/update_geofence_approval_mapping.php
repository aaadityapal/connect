<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$managerId = isset($input['manager_id']) ? (int)$input['manager_id'] : 0;
$subordinates = isset($input['subordinates']) && is_array($input['subordinates']) ? $input['subordinates'] : [];

if ($managerId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Manager ID is required']);
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

    $pdo->beginTransaction();

    // Remove existing mappings for this manager.
    $del = $pdo->prepare('DELETE FROM geofence_approval_mapping WHERE manager_id = ?');
    $del->execute([$managerId]);

    // Insert selected employees.
    if (!empty($subordinates)) {
        $ins = $pdo->prepare('INSERT INTO geofence_approval_mapping (manager_id, employee_id) VALUES (?, ?)');
        foreach ($subordinates as $sid) {
            $employeeId = (int)$sid;
            if ($employeeId > 0 && $employeeId !== $managerId) {
                $ins->execute([$managerId, $employeeId]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Geofence approval mapping updated successfully']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Geofence mapping update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database operation failed', 'details' => $e->getMessage()]);
}
?>