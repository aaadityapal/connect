<?php
require_once 'config/db_connect.php';

header('Content-Type: application/json');

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['error' => 'No user ID provided']);
    exit;
}

try {
    // Get leave type information
    $leave_type_query = "SELECT * FROM leave_types WHERE name = 'Short Leave'";
    $leave_type = $conn->query($leave_type_query)->fetch_assoc();

    // Get all short leaves for the user
    $leaves_query = "SELECT 
        id, 
        start_date, 
        end_date, 
        status, 
        manager_approval, 
        hr_approval
    FROM leave_request 
    WHERE user_id = ? 
    AND leave_type = ?
    ORDER BY start_date DESC";

    $stmt = $conn->prepare($leaves_query);
    $stmt->bind_param('ii', $user_id, $leave_type['id']);
    $stmt->execute();
    $short_leaves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Count approved short leaves
    $count_query = "SELECT COUNT(*) as count
    FROM leave_request
    WHERE user_id = ?
    AND leave_type = ?
    AND status = 'approved'
    AND manager_approval = 'approved'
    AND hr_approval = 'approved'";

    $stmt = $conn->prepare($count_query);
    $stmt->bind_param('ii', $user_id, $leave_type['id']);
    $stmt->execute();
    $count_result = $stmt->get_result()->fetch_assoc();

    // Get the current used days from leave balance calculation
    $balance_query = "SELECT used_days
    FROM (
        SELECT 
            lt.id,
            lt.name,
            COALESCE(COUNT(lr.id), 0) as used_days
        FROM leave_types lt
        LEFT JOIN leave_request lr ON lt.id = lr.leave_type 
            AND lr.user_id = ?
            AND lr.status = 'approved'
            AND lr.manager_approval = 'approved'
            AND lr.hr_approval = 'approved'
        WHERE lt.name = 'Short Leave'
        GROUP BY lt.id, lt.name
    ) as balance";

    $stmt = $conn->prepare($balance_query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $balance_result = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        'leaveType' => $leave_type,
        'shortLeaves' => $short_leaves,
        'shortLeaveCount' => $count_result['count'],
        'usedDays' => $balance_result['used_days'] ?? 0
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} 