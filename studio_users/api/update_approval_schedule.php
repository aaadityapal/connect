<?php
/**
 * UPDATE APPROVAL SCHEDULE
 * studio_users/travel_exp/api/update_approval_schedule.php
 */
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['active_days']) || !isset($data['start_time']) || !isset($data['end_time'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
    exit();
}

try {
    // Check if a schedule exists
    $check = $pdo->query("SELECT id FROM travel_expense_approval_schedule LIMIT 1")->fetch();
    
    if ($check) {
        $stmt = $pdo->prepare("UPDATE travel_expense_approval_schedule SET active_days = ?, start_time = ?, end_time = ? WHERE id = ?");
        $stmt->execute([$data['active_days'], $data['start_time'], $data['end_time'], $check['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO travel_expense_approval_schedule (active_days, start_time, end_time) VALUES (?, ?, ?)");
        $stmt->execute([$data['active_days'], $data['start_time'], $data['end_time']]);
    }

    echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
