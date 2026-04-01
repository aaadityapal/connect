<?php
/**
 * UPDATE TRAVEL MAPPING
 * studio_users/api/update_travel_mapping.php
 */
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['employee_id']) || !isset($data['manager_id']) || !isset($data['hr_id']) || !isset($data['senior_manager_id'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required mapping data.']);
        exit();
    }

    $employee_id = (int)$data['employee_id'];
    $manager_id = (int)$data['manager_id'];
    $hr_id = (int)$data['hr_id'];
    $senior_manager_id = (int)$data['senior_manager_id'];

    // Upsert mapping: Update if employee already exists, else insert
    $sql = "INSERT INTO travel_expense_mapping (employee_id, manager_id, hr_id, senior_manager_id) 
            VALUES (:eid, :mid, :hid, :sid) 
            ON DUPLICATE KEY UPDATE 
                manager_id = VALUES(manager_id), 
                hr_id = VALUES(hr_id), 
                senior_manager_id = VALUES(senior_manager_id)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':eid' => $employee_id,
        ':mid' => $manager_id,
        ':hid' => $hr_id,
        ':sid' => $senior_manager_id
    ]);

    echo json_encode(['success' => true, 'message' => 'Mapping updated successfully.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>
