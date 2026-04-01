<?php
/**
 * FETCH APPROVAL SCHEDULE
 * studio_users/travel_exp/api/fetch_approval_schedule.php
 */
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $stmt = $pdo->query("SELECT * FROM travel_expense_approval_schedule LIMIT 1");
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        $schedule = [
            'active_days' => 'Monday,Tuesday,Wednesday,Thursday,Friday',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00'
        ];
    } else {
        // Strip out seconds from time if desired, though HH:MM:SS is fine
        $schedule['start_time'] = substr($schedule['start_time'], 0, 5);
        $schedule['end_time'] = substr($schedule['end_time'], 0, 5);
    }

    echo json_encode(['success' => true, 'schedule' => $schedule]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
