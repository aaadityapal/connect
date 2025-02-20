<?php
require_once 'config/db_connect.php';

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['year'], $data['month'], $data['working_days'])) {
    $year = $data['year'];
    $month = $data['month'];
    $working_days = $data['working_days'];
    $month_year = "$year-$month-01";

    // Update or insert working days for all active users
    $query = "INSERT INTO salary_details (user_id, month_year, total_working_days)
              SELECT id, ?, ?
              FROM users
              WHERE deleted_at IS NULL AND status = 'active'
              ON DUPLICATE KEY UPDATE total_working_days = VALUES(total_working_days)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $month_year, $working_days);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
} 