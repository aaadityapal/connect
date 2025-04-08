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
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Get notifications based on filter
$notifications = getAssignmentNotifications($userId, $limit);

// Filter notifications if needed
if ($filter === 'unread') {
    $notifications = array_filter($notifications, function($notification) {
        return $notification['read_status'] == 0;
    });
}

// Format notifications for display
$formattedNotifications = [];
foreach ($notifications as $notification) {
    // Determine icon based on source type
    $icon = 'fas fa-bell';
    if ($notification['source_type'] === 'project') {
        $icon = 'fas fa-project-diagram';
    } else if ($notification['source_type'] === 'stage') {
        $icon = 'fas fa-layer-group';
    } else if ($notification['source_type'] === 'substage') {
        $icon = 'fas fa-tasks';
    }
    
    // Format time
    $createdAt = new DateTime($notification['created_at']);
    $now = new DateTime();
    $interval = $now->diff($createdAt);
    
    $timeDisplay = 'Just now';
    if ($interval->y > 0) {
        $timeDisplay = $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
    } else if ($interval->m > 0) {
        $timeDisplay = $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
    } else if ($interval->d > 0) {
        $timeDisplay = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
    } else if ($interval->h > 0) {
        $timeDisplay = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
    } else if ($interval->i > 0) {
        $timeDisplay = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
    }
    
    $formattedNotifications[] = [
        'id' => $notification['id'],
        'title' => $notification['title'],
        'message' => $notification['message'],
        'source_type' => $notification['source_type'],
        'source_id' => $notification['source_id'],
        'entity_title' => $notification['entity_title'],
        'entity_id' => $notification['entity_id'],
        'read_status' => $notification['read_status'],
        'created_at' => $notification['created_at'],
        'time_display' => $timeDisplay,
        'icon' => $icon
    ];
}

// Get unread count
$unreadCount = 0;
foreach ($notifications as $notification) {
    if ($notification['read_status'] == 0) {
        $unreadCount++;
    }
}

echo json_encode([
    'status' => 'success',
    'notifications' => $formattedNotifications,
    'unread_count' => $unreadCount
]); 