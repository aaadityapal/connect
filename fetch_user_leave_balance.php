<?php
require_once 'config/db_connect.php';
require_once 'manage_leave_balance.php';

if (isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $year = $_GET['year'] ?? date('Y');
    
    // Initialize balance if not exists
    initializeUserLeaveBalance($user_id, $year);
    
    // Fetch leave balance
    $query = "SELECT 
        ulb.*,
        lt.name as leave_type_name,
        lt.max_days,
        lt.color_code
    FROM user_leave_balance ulb
    JOIN leave_types lt ON ulb.leave_type_id = lt.id
    WHERE ulb.user_id = ? AND ulb.year = ?
    ORDER BY lt.id";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $user_id, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $balances = $result->fetch_all(MYSQLI_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($balances);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'User ID is required']);
}
?> 