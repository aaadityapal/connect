<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$managerId = $input['manager_id'] ?? null;
$subordinates = $input['subordinates'] ?? []; 

if (!$managerId) {
    echo json_encode(['success' => false, 'error' => 'Manager ID is required']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Remove existing mapping for this specific manager
    $stmt = $pdo->prepare("DELETE FROM overtime_approval_mapping WHERE manager_id = ?");
    $stmt->execute([$managerId]);

    // Insert new mappings if any provided
    if (!empty($subordinates)) {
        $insertData = [];
        $placeholders = [];
        foreach ($subordinates as $subId) {
            $insertData[] = (int)$subId;
            $insertData[] = (int)$managerId;
            $placeholders[] = "(?, ?)";
        }
        $bulkSql = "INSERT INTO overtime_approval_mapping (employee_id, manager_id) VALUES " . implode(', ', $placeholders);
        $stmt = $pdo->prepare($bulkSql);
        $stmt->execute($insertData);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Overtime mapping updated successfully.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Mapping sync error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database operation failed', 'details' => $e->getMessage()]);
}
?>
