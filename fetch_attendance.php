<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized access');
}

$action = $_GET['action'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');

switch($action) {
    case 'present':
        $query = "SELECT a.*, u.username, u.department, d.name as dept_name
                 FROM attendance a 
                 JOIN users u ON a.user_id = u.id 
                 LEFT JOIN departments d ON u.department = d.id
                 WHERE a.date = ? 
                 AND a.punch_in IS NOT NULL 
                 ORDER BY a.punch_in ASC";
        break;

    case 'absent':
        $query = "SELECT u.username, u.department, d.name as dept_name
                 FROM users u 
                 LEFT JOIN departments d ON u.department = d.id
                 WHERE u.role != 'admin' 
                 AND u.id NOT IN (
                     SELECT user_id FROM attendance WHERE date = ?
                 ) 
                 AND u.id NOT IN (
                     SELECT user_id FROM leaves 
                     WHERE ? BETWEEN start_date AND end_date 
                     AND status = 'approved'
                 )";
        break;

    case 'onleave':
        $query = "SELECT l.*, u.username, u.department, d.name as dept_name
                 FROM leaves l 
                 JOIN users u ON l.user_id = u.id 
                 LEFT JOIN departments d ON u.department = d.id
                 WHERE ? BETWEEN l.start_date AND l.end_date 
                 AND l.status = 'approved'";
        break;

    case 'pending':
        $query = "SELECT l.*, u.username, u.department, d.name as dept_name
                 FROM leaves l 
                 JOIN users u ON l.user_id = u.id 
                 LEFT JOIN departments d ON u.department = d.id
                 WHERE l.status = 'pending'
                 ORDER BY l.created_at DESC";
        break;

    default:
        die('Invalid action');
}

$stmt = $conn->prepare($query);

if ($action === 'absent') {
    $stmt->bind_param("ss", $date, $date);
} elseif ($action === 'pending') {
    // No parameters needed
} else {
    $stmt->bind_param("s", $date);
}

$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);

header('Content-Type: application/json');
echo json_encode($data);
?>