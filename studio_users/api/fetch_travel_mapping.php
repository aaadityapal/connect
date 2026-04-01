<?php
/**
 * FETCH TRAVEL APPROVERS
 * studio_users/api/fetch_travel_mapping.php
 */
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $query = "
        SELECT 
            DISTINCT u.id, u.username, u.employee_id, u.role,
            tas.active_days, tas.start_time, tas.end_time
        FROM users u
        LEFT JOIN travel_approver_schedules tas ON u.id = tas.user_id
        WHERE u.id IN (
            SELECT manager_id FROM travel_expense_mapping WHERE manager_id IS NOT NULL
            UNION
            SELECT hr_id FROM travel_expense_mapping WHERE hr_id IS NOT NULL
            UNION
            SELECT senior_manager_id FROM travel_expense_mapping WHERE senior_manager_id IS NOT NULL
        )
        ORDER BY u.role, u.username ASC
    ";

    $stmt = $pdo->query($query);
    $approvers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'approvers' => $approvers]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
