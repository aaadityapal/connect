<?php
require_once 'config/db_connect.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$date = $data['date'];

// Get all active users and their weekly offs
$query = "SELECT u.id, us.weekly_offs 
          FROM users u 
          LEFT JOIN user_shifts us ON u.id = us.user_id 
          WHERE u.status = 'active' 
          AND u.deleted_at IS NULL 
          AND us.effective_from <= ?
          AND (us.effective_to >= ? OR us.effective_to IS NULL)";

$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $date, $date);
$stmt->execute();
$result = $stmt->get_result();

$weeklyOffs = [];
while ($row = $result->fetch_assoc()) {
    $weeklyOffs[$row['id']] = $row['weekly_offs'] ? explode(',', $row['weekly_offs']) : [];
}

header('Content-Type: application/json');
echo json_encode($weeklyOffs); 