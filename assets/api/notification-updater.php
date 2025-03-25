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
$response = ['status' => 'error'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Mark a single notification as read
    if (isset($data['notification_id'])) {
        $notification_id = intval($data['notification_id']);
        
        // Verify the notification belongs to the user
        $check_query = "SELECT id FROM employee_notifications 
                        WHERE id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $notification_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $update_query = "UPDATE employee_notifications 
                            SET read_status = 1 
                            WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("i", $notification_id);
            
            if ($update_stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Notification marked as read';
            }
        } else {
            $response['message'] = 'Notification not found or access denied';
        }
    }
    
    // Mark all notifications as read
    elseif (isset($data['mark_all_read']) && $data['mark_all_read']) {
        $update_query = "UPDATE employee_notifications 
                        SET read_status = 1 
                        WHERE user_id = ? AND read_status = 0";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $user_id);
        
        if ($update_stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'All notifications marked as read';
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?> 