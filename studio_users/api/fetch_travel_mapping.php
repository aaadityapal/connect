<?php
/**
 * FETCH TRAVEL APPROVERS WITH PER-DAY SCHEDULE
 * studio_users/api/fetch_travel_mapping.php
 */
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

try {
    // 1. Fetch all unique approvers from the mapping table
    $query = "
        SELECT DISTINCT u.id, u.username, u.employee_id, u.role
        FROM users u
        WHERE u.id IN (
            SELECT manager_id        FROM travel_expense_mapping WHERE manager_id IS NOT NULL
            UNION
            SELECT hr_id             FROM travel_expense_mapping WHERE hr_id IS NOT NULL
            UNION
            SELECT senior_manager_id FROM travel_expense_mapping WHERE senior_manager_id IS NOT NULL
        )
        ORDER BY u.role, u.username ASC
    ";
    $stmt     = $pdo->query($query);
    $approvers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. For each approver, fetch per-day schedule
    $schedStmt = $pdo->prepare("
        SELECT day_name, is_active, start_time, end_time
        FROM travel_approver_day_schedules
        WHERE approver_id = ?
    ");

    foreach ($approvers as &$approver) {
        $schedStmt->execute([$approver['id']]);
        $rows = $schedStmt->fetchAll(PDO::FETCH_ASSOC);

        // Index by day_name
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[$r['day_name']] = $r;
        }

        // Build a full 7-day schedule (fill defaults if row missing)
        $schedule = [];
        foreach ($days as $day) {
            $schedule[$day] = [
                'is_active'  => isset($byDay[$day]) ? (int)$byDay[$day]['is_active']  : ($day === 'Saturday' || $day === 'Sunday' ? 0 : 1),
                'start_time' => isset($byDay[$day]) ? substr($byDay[$day]['start_time'], 0, 5) : '09:00',
                'end_time'   => isset($byDay[$day]) ? substr($byDay[$day]['end_time'],   0, 5) : '18:00',
            ];
        }
        $approver['day_schedule'] = $schedule;
    }
    unset($approver);

    echo json_encode(['success' => true, 'approvers' => $approvers]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
