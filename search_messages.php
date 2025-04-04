<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if (!isset($_GET['query']) || !isset($_GET['chat_with'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$chat_with_id = $_GET['chat_with'];
$search_query = '%' . $_GET['query'] . '%';

try {
    $query = "SELECT 
        m.*,
        sender.username as sender_name,
        sender.profile_picture as sender_avatar
    FROM chat_messages m
    JOIN users sender ON m.sender_id = sender.id
    WHERE (
        (m.sender_id = ? AND m.receiver_id = ?)
        OR 
        (m.sender_id = ? AND m.receiver_id = ?)
    )
    AND m.message LIKE ?
    AND m.deleted_at IS NULL
    ORDER BY m.sent_at ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiis", 
        $current_user_id, $chat_with_id,
        $chat_with_id, $current_user_id,
        $search_query
    );
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'sender_name' => $row['sender_name'],
            'message' => $row['message'],
            'sent_at' => $row['sent_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to search messages: ' . $e->getMessage()
    ]);
}