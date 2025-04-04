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
    $query = "UPDATE chat_messages 
              SET is_read = TRUE 
              WHERE id = ? 
              AND receiver_id = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $data['message_id'], $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to mark message as read");
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}