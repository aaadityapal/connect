<?php
/**
 * UPDATE SPECIFIC APPROVER SCHEDULE
 * studio_users/api/update_approver_schedule.php
 */
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['approver_id']) || !isset($data['active_days']) || !isset($data['start_time']) || !isset($data['end_time'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
    exit();
}

try {
    // Upsert the schedule for the specific approver
    $stmt = $pdo->prepare("
        INSERT INTO travel_approver_schedules (user_id, active_days, start_time, end_time) 
        VALUES (:uid, :days, :start_t, :end_t)
        ON DUPLICATE KEY UPDATE 
            active_days = VALUES(active_days),
            start_time = VALUES(start_time),
            end_time = VALUES(end_time)
    ");
    
    $stmt->execute([
        ':uid' => $data['approver_id'],
        ':days' => $data['active_days'],
        ':start_t' => $data['start_time'],
        ':end_t' => $data['end_time']
    ]);

    echo json_encode(['success' => true, 'message' => 'Approver schedule updated successfully']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
