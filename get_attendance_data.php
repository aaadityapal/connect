<?php
include 'config/db_connect.php';

$today = date('Y-m-d');
$response = [
    'present' => 0,
    'absent' => 0,
    'onLeave' => 0
];

try {
    // Get present users
    $query = "SELECT COUNT(DISTINCT user_id) as present 
              FROM attendance 
              WHERE DATE(punch_in) = '$today'";
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $response['present'] = $row['present'];
    }

    // Get users on leave
    $query = "SELECT COUNT(DISTINCT user_id) as on_leave 
              FROM leaves 
              WHERE '$today' BETWEEN start_date AND end_date 
              AND status = 'approved'";
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $response['onLeave'] = $row['on_leave'];
    }

    // Get total users
    $query = "SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL";
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $totalUsers = $row['total'];
        $response['absent'] = $totalUsers - ($response['present'] + $response['onLeave']);
    }

} catch (Exception $e) {
    error_log("Error fetching attendance data: " . $e->getMessage());
}

header('Content-Type: application/json');
echo json_encode($response);
?> 