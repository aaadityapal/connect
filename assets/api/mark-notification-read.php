<?php
require_once '../../config/db_connect.php';
require_once '../../functions/assignment_notifications.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not logged in'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$notificationId = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : null;

// Validate notification ID
if (!$notificationId) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Notification ID is required'
    ]);
    exit;
}

// Mark notification as read
$result = markAssignmentNotificationAsRead($userId, $notificationId);

if ($result) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Notification marked as read'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to mark notification as read'
    ]);
} 