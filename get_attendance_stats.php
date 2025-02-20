<?php
require_once 'config/db_connect.php';
header('Content-Type: application/json');

$date = $_GET['date'] ?? date('Y-m-d');

try {
    // Get total employees
    $totalQuery = "SELECT COUNT(*) as total FROM users WHERE role != 'admin'";
    $totalEmployees = $conn->query($totalQuery)->fetch_assoc()['total'];

    // Get present employees
    $presentQuery = "SELECT COUNT(DISTINCT user_id) as count 
                    FROM attendance 
                    WHERE date = ? AND punch_in IS NOT NULL";
    $stmt = $conn->prepare($presentQuery);
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $presentCount = $stmt->get_result()->fetch_assoc()['count'];

    // Get late arrivals
    $lateQuery = "SELECT COUNT(DISTINCT user_id) as count 
                  FROM attendance 
                  WHERE date = ? AND TIME(punch_in) > '09:30:00'";
    $stmt = $conn->prepare($lateQuery);
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $lateCount = $stmt->get_result()->fetch_assoc()['count'];

    // Get employees on leave
    $leaveQuery = "SELECT COUNT(DISTINCT user_id) as count 
                   FROM leaves 
                   WHERE ? BETWEEN start_date AND end_date 
                   AND status = 'approved'";
    $stmt = $conn->prepare($leaveQuery);
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $onLeave = $stmt->get_result()->fetch_assoc()['count'];

    // Calculate absent employees
    $absentCount = $totalEmployees - ($presentCount + $onLeave);

    echo json_encode([
        'present' => $presentCount,
        'late' => $lateCount,
        'on_leave' => $onLeave,
        'absent' => max(0, $absentCount) // Ensure we don't show negative numbers
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch attendance statistics']);
}
?> 