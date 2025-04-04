<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['message_id']) || !isset($data['user_ids']) || !is_array($data['user_ids'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit();
}

try {
    // Get the original message
    $get_message_query = "SELECT message, file_id FROM chat_messages WHERE id = ? AND deleted_at IS NULL";
    $get_message_stmt = $conn->prepare($get_message_query);
    $get_message_stmt->bind_param("i", $data['message_id']);
    $get_message_stmt->execute();
    $result = $get_message_stmt->get_result();
    $original_message = $result->fetch_assoc();

    if (!$original_message) {
        throw new Exception("Original message not found");
    }

    // Begin transaction
    $conn->begin_transaction();

    // Insert forwarded messages
    $insert_query = "INSERT INTO chat_messages (sender_id, receiver_id, message, file_id, forwarded_from) 
                    VALUES (?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);

    foreach ($data['user_ids'] as $receiver_id) {
        $insert_stmt->bind_param("iisis", 
            $_SESSION['user_id'],
            $receiver_id,
            $original_message['message'],
            $original_message['file_id'],
            $data['message_id']
        );
        
        if (!$insert_stmt->execute()) {
            throw new Exception("Failed to forward message to user " . $receiver_id);
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}