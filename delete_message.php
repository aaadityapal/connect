<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['message_id'])) {
    echo json_encode(['success' => false, 'error' => 'Message ID not provided']);
    exit();
}

try {
    // First check if the user owns this message
    $check_query = "SELECT sender_id FROM chat_messages WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $data['message_id']);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $message = $result->fetch_assoc();

    if (!$message) {
        throw new Exception("Message not found");
    }

    if ($message['sender_id'] != $_SESSION['user_id']) {
        throw new Exception("Not authorized to delete this message");
    }

    // Soft delete the message
    $delete_query = "UPDATE chat_messages 
                    SET deleted_at = CURRENT_TIMESTAMP 
                    WHERE id = ? AND sender_id = ?";
    
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("ii", $data['message_id'], $_SESSION['user_id']);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to delete message");
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}