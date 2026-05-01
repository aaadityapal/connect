<?php
/**
 * manager_pages/food_reimbursement_mapping/api/get_food_mapping_data.php
 *
 * Returns all active users and the current food reimbursement approval mapping
 * (who reviews which employee's food reimbursement claim).
 *
 * JSON Response:
 *   { success: true, users: [...], approvers: [...], mappings: { employeeId: { manager_id, hr_id } } }
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../../../config/db_connect.php';

try {
    // 1. All active users (employees)
    $stmtUsers = $pdo->query(
        "SELECT id, username AS name, position, role, email
         FROM users
         WHERE deleted_at IS NULL AND status = 'Active'
         ORDER BY username ASC"
    );
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    // 2. All active users who could be approvers (managers / HR / admin)
    $stmtApprovers = $pdo->query(
        "SELECT id, username AS name, position, role
         FROM users
         WHERE deleted_at IS NULL AND status = 'Active'
           AND (
               role IN ('admin','HR','Manager','Senior Manager')
               OR role LIKE '%manager%'
               OR position LIKE '%manager%'
               OR role LIKE '%HR%'
               OR position LIKE '%HR%'
               OR role LIKE '%senior%'
               OR position LIKE '%senior%'
           )
         ORDER BY username ASC"
    );
    $approvers = $stmtApprovers->fetchAll(PDO::FETCH_ASSOC);

    // 3. Existing food reimbursement mappings
    //    Table: food_reimbursement_mapping (auto-created below if missing)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS food_reimbursement_mapping (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            employee_id     INT NOT NULL,
            manager_id      INT DEFAULT NULL COMMENT 'Level-1: Direct Manager',
            hr_id           INT DEFAULT NULL COMMENT 'Level-2: HR Approver',
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_employee (employee_id),
            KEY idx_manager (manager_id),
            KEY idx_hr (hr_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmtMap = $pdo->query("SELECT employee_id, manager_id, hr_id FROM food_reimbursement_mapping");
    $rawMappings = $stmtMap->fetchAll(PDO::FETCH_ASSOC);

    // Convert to keyed-by-employee_id for easy JS lookup
    $mappings = [];
    foreach ($rawMappings as $row) {
        $mappings[$row['employee_id']] = [
            'manager_id' => $row['manager_id'],
            'hr_id'      => $row['hr_id'],
        ];
    }

    echo json_encode([
        'success'  => true,
        'users'    => $users,
        'approvers'=> $approvers,
        'mappings' => $mappings,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
