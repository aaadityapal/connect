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

// Validate request data
if (!isset($data['receiver_id']) || !isset($data['message']) || empty(trim($data['message']))) {
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit();
}

$receiver_id = $data['receiver_id'];
$content = trim($data['message']);
$file_url = isset($data['file_url']) ? $data['file_url'] : null;
$message_type = isset($data['message_type']) ? $data['message_type'] : 'text';
$original_filename = isset($data['original_filename']) ? $data['original_filename'] : null;

try {
    // Insert message into database
    $query = "INSERT INTO messages (sender_id, receiver_id, content, message_type, file_url, original_filename, sent_at) 
              VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iissss", $current_user_id, $receiver_id, $content, $message_type, $file_url, $original_filename);
    $stmt->execute();
    
    $message_id = $stmt->insert_id;
    
    // Insert initial message status
    $status_query = "INSERT INTO message_status (message_id, user_id, status, updated_at) 
                    VALUES (?, ?, 'sent', NOW())";
    $status_stmt = $conn->prepare($status_query);
    $status_stmt->bind_param("ii", $message_id, $current_user_id);
    $status_stmt->execute();
    
    // Fetch the complete message data
    $fetch_query = "SELECT 
                    m.id,
                    m.sender_id,
                    m.receiver_id,
                    m.content,
                    m.message_type,
                    m.file_url,
                    m.original_filename,
                    m.sent_at,
                    m.read_at,
                    u.username as sender_name,
                    u.profile_picture as sender_avatar
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.id = ?";
    
    $fetch_stmt = $conn->prepare($fetch_query);
    $fetch_stmt->bind_param("i", $message_id);
    $fetch_stmt->execute();
    $result = $fetch_stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Format the message data
        $message_data = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'receiver_id' => $row['receiver_id'],
            'message' => $row['content'], // Map content to message for frontend compatibility
            'message_type' => $row['message_type'],
            'file_url' => $row['file_url'],
            'original_filename' => $row['original_filename'],
            'sent_at' => $row['sent_at'],
            'read_at' => $row['read_at'],
            'sender_name' => $row['sender_name'],
            'sender_avatar' => $row['sender_avatar'] ?? 'assets/default-avatar.png'
        ];
        
        echo json_encode([
            'success' => true,
            'message' => $message_data
        ]);
    } else {
        throw new Exception("Failed to fetch message data");
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to send message: ' . $e->getMessage()
    ]);
}
?>