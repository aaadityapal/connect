<?php
/**
 * manager_pages/food_reimbursement_mapping/api/update_food_mapping.php
 *
 * UPSERT a food reimbursement approval mapping for one employee.
 *
 * POST JSON body:
 *   { employee_id: int, manager_id: int|null, hr_id: int|null }
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../../../config/db_connect.php';

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

$employeeId = isset($body['employee_id']) ? (int)$body['employee_id'] : 0;
$managerId  = isset($body['manager_id'])  && $body['manager_id']  !== '' ? (int)$body['manager_id']  : null;
$hrId       = isset($body['hr_id'])       && $body['hr_id']       !== '' ? (int)$body['hr_id']       : null;

if ($employeeId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid employee_id']);
    exit();
}

try {
    $sql = "
        INSERT INTO food_reimbursement_mapping (employee_id, manager_id, hr_id)
        VALUES (:emp, :mgr, :hr)
        ON DUPLICATE KEY UPDATE
            manager_id = VALUES(manager_id),
            hr_id      = VALUES(hr_id),
            updated_at = CURRENT_TIMESTAMP
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':emp' => $employeeId,
        ':mgr' => $managerId,
        ':hr'  => $hrId,
    ]);

    echo json_encode(['success' => true, 'message' => 'Mapping saved successfully.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
