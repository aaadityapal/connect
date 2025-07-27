<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$message_id = isset($data['message_id']) ? $data['message_id'] : null;

// If message_id is provided, mark specific message as read
if ($message_id) {
    try {
        $query = "UPDATE messages 
                SET read_at = NOW() 
                WHERE id = ? 
                AND receiver_id = ? 
                AND read_at IS NULL";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $message_id, $current_user_id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Message marked as read'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to mark message as read: ' . $e->getMessage()
        ]);
    }
} else {
    // If no message_id provided, mark all messages from a specific sender as read
    $sender_id = isset($data['sender_id']) ? $data['sender_id'] : null;
    
    if (!$sender_id) {
        echo json_encode(['success' => false, 'error' => 'No message_id or sender_id provided']);
        exit();
    }
    
    try {
        $query = "UPDATE messages 
                SET read_at = NOW() 
                WHERE sender_id = ? 
                AND receiver_id = ? 
                AND read_at IS NULL";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $sender_id, $current_user_id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'All messages from sender marked as read'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to mark messages as read: ' . $e->getMessage()
        ]);
    }
}
?>