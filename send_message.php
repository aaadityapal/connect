<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['receiver_id']) || !isset($data['message'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Database connection
require_once 'config/db_connect.php';

try {
    // Prepare the insert statement
    $query = "INSERT INTO chat_messages (sender_id, receiver_id, message, sent_at) 
              VALUES (?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iis", 
        $_SESSION['user_id'],
        $data['receiver_id'],
        $data['message']
    );

    // Execute the query
    if ($stmt->execute()) {
        // Get the inserted message details
        $message_id = $stmt->insert_id;
        
        // Fetch the complete message details including sender name
        $select_query = "SELECT cm.*, u.username as sender_name 
                        FROM chat_messages cm 
                        JOIN users u ON cm.sender_id = u.id 
                        WHERE cm.id = ?";
        
        $select_stmt = $conn->prepare($select_query);
        $select_stmt->bind_param("i", $message_id);
        $select_stmt->execute();
        $result = $select_stmt->get_result();
        $message = $result->fetch_assoc();

        echo json_encode([
            'success' => true,
            'message' => [
                'id' => $message['id'],
                'sender_id' => $message['sender_id'],
                'sender_name' => $message['sender_name'],
                'receiver_id' => $message['receiver_id'],
                'message' => $message['message'],
                'sent_at' => $message['sent_at']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }

} catch (Exception $e) {
    error_log("Error in send_message.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

$conn->close();
?>