<?php
require_once 'config/db_connect.php';
session_start();

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$leave_balance_query = "SELECT 
    lt.id,
    lt.name,
    CASE 
        WHEN lt.name = 'Compensate Leave' THEN (
            SELECT COUNT(*) 
            FROM attendance a
            JOIN user_shifts us ON a.user_id = us.user_id
                AND a.date >= us.effective_from
                AND (us.effective_to IS NULL OR a.date <= us.effective_to)
            WHERE a.user_id = ? 
            AND a.status = 'present'
            AND DAYNAME(a.date) = us.weekly_offs
            AND YEAR(a.date) = YEAR(CURRENT_DATE())
        )
        ELSE lt.max_days
    END as max_days,
    COALESCE(
        CASE 
            WHEN lt.name = 'Compensate Leave' THEN (
                SELECT COALESCE(SUM(duration), 0)
                FROM leave_request lr
                WHERE lr.user_id = ? 
                AND lr.leave_type = lt.id
                AND lr.status = 'approved'
                AND YEAR(lr.start_date) = YEAR(CURRENT_DATE())
            )
            ELSE (
                SELECT COALESCE(SUM(duration), 0)
                FROM leave_request lr
                WHERE lr.user_id = ? 
                AND lr.leave_type = lt.id
                AND lr.status = 'approved'
                AND YEAR(lr.start_date) = YEAR(CURRENT_DATE())
            )
        END, 0
    ) as used_days
FROM leave_types lt
WHERE lt.status = 'active'";

$stmt = $conn->prepare($leave_balance_query);
$stmt->bind_param('iii', $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$leave_balances = $result->fetch_all(MYSQLI_ASSOC);

error_log("Leave balances for user $user_id: " . print_r($leave_balances, true));

error_log("SQL Query: " . $leave_balance_query);
$stmt->execute();
$result = $stmt->get_result();
$leave_balances = $result->fetch_all(MYSQLI_ASSOC);
error_log("Query result: " . print_r($leave_balances, true));

header('Content-Type: application/json');
echo json_encode($leave_balances); 