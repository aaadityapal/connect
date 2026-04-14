<?php
/**
 * UPDATE APPROVER PER-DAY SCHEDULE
 * studio_users/api/update_approver_schedule.php
 *
 * Expected payload:
 * {
 *   "approver_id": 5,
 *   "schedule": {
 *     "Monday":    { "is_active": 1, "start_time": "09:00", "end_time": "18:00" },
 *     "Tuesday":   { "is_active": 1, "start_time": "10:00", "end_time": "17:00" },
 *     ...
 *   }
 * }
 */
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['approver_id']) || !isset($data['schedule']) || !is_array($data['schedule'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data: approver_id and schedule required']);
    exit();
}

$validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$approverId = (int)$data['approver_id'];

try {
    $stmt = $pdo->prepare("
        INSERT INTO travel_approver_day_schedules
            (approver_id, day_name, is_active, start_time, end_time)
        VALUES (:aid, :day, :active, :start, :end)
        ON DUPLICATE KEY UPDATE
            is_active  = VALUES(is_active),
            start_time = VALUES(start_time),
            end_time   = VALUES(end_time)
    ");

    foreach ($validDays as $day) {
        if (!isset($data['schedule'][$day])) continue;

        $row = $data['schedule'][$day];
        $stmt->execute([
            ':aid'    => $approverId,
            ':day'    => $day,
            ':active' => isset($row['is_active']) ? (int)$row['is_active'] : 0,
            ':start'  => ($row['start_time'] ?? '09:00') . ':00',
            ':end'    => ($row['end_time']   ?? '18:00') . ':00',
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Schedule saved successfully']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
