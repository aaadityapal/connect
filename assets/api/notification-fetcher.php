<?php
session_start();
require_once '../../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = [];

// Get unread notification count if requested
if (isset($_GET['count_only']) && $_GET['count_only'] == 1) {
    $count_query = "SELECT COUNT(*) as count FROM employee_notifications 
                    WHERE user_id = ? AND read_status = 0";
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count_data = $result->fetch_assoc();
    
    header('Content-Type: application/json');
    echo json_encode(['count' => $count_data['count']]);
    exit;
}

// Get notifications with pagination
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

$query = "SELECT * FROM employee_notifications 
          WHERE user_id = ? 
          ORDER BY created_at DESC 
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Format the time
        $created_time = strtotime($row['created_at']);
        $current_time = time();
        $time_diff = $current_time - $created_time;
        
        if ($time_diff < 60) {
            $time_display = "Just now";
        } elseif ($time_diff < 3600) {
            $minutes = floor($time_diff / 60);
            $time_display = $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
        } elseif ($time_diff < 86400) {
            $hours = floor($time_diff / 3600);
            $time_display = $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
        } elseif ($time_diff < 604800) {
            $days = floor($time_diff / 86400);
            $time_display = $days . " day" . ($days > 1 ? "s" : "") . " ago";
        } else {
            $time_display = date("M j, Y", $created_time);
        }
        
        $row['time_display'] = $time_display;
        $notifications[] = $row;
    }
    
    $response['notifications'] = $notifications;
    $response['status'] = 'success';
} else {
    $response['notifications'] = [];
    $response['status'] = 'empty';
}

header('Content-Type: application/json');
echo json_encode($response);
?> 