<?php
require_once 'config.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['error' => 'Unauthorized access']));
}

$type = $_GET['type'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');

$response = [
    'title' => '',
    'headers' => [],
    'data' => []
];

switch ($type) {
    case 'present':
        $response['title'] = 'Present Employees';
        $response['headers'] = ['Employee Name', 'Department', 'Punch In', 'Punch Out', 'Working Hours'];
        
        $query = "SELECT u.username, u.department, a.punch_in, a.punch_out,
                  TIMEDIFF(COALESCE(a.punch_out, NOW()), a.punch_in) as working_hours
                  FROM users u
                  JOIN attendance a ON u.id = a.user_id
                  WHERE DATE(a.date) = ?";
        break;

    case 'absent':
        $response['title'] = 'Absent Employees';
        $response['headers'] = ['Employee Name', 'Department', 'Last Present Date'];
        
        $query = "SELECT u.username, u.department, 
                  (SELECT MAX(date) FROM attendance WHERE user_id = u.id) as last_present
                  FROM users u
                  WHERE u.id NOT IN (
                      SELECT user_id FROM attendance WHERE date = ?
                  ) AND u.id NOT IN (
                      SELECT user_id FROM leaves WHERE ? BETWEEN from_date AND to_date AND status = 'approved'
                  )";
        break;

    case 'on_leave':
        $response['title'] = 'Employees on Leave';
        $response['headers'] = ['Employee Name', 'Department', 'Leave Type', 'From', 'To', 'Reason'];
        
        $query = "SELECT u.username, u.department, l.leave_type, l.from_date, l.to_date, l.reason
                  FROM users u
                  JOIN leaves l ON u.id = l.user_id
                  WHERE ? BETWEEN l.from_date AND l.to_date AND l.status = 'approved'";
        break;

    default:
        die(json_encode(['error' => 'Invalid type specified']));
}

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $date);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $response['data'][] = $row;
}

header('Content-Type: application/json');
echo json_encode($response);
?>