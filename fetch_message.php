<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No user specified']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$other_user_id = $_GET['user_id'];

try {
    $query = "SELECT 
        cm.*,
        sender.username as sender_name,
        sender.profile_picture as sender_avatar
    FROM chat_messages cm
    JOIN users sender ON cm.sender_id = sender.id
    WHERE (
        (cm.sender_id = ? AND cm.receiver_id = ?)
        OR 
        (cm.sender_id = ? AND cm.receiver_id = ?)
    )
    AND cm.deleted_at IS NULL
    ORDER BY cm.sent_at ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $current_user_id, $other_user_id, $other_user_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'sender_name' => $row['sender_name'],
            'sender_avatar' => $row['sender_avatar'] ?? 'assets/default-avatar.png',
            'message' => $row['message'],
            'file_url' => $row['file_url'],
            'file_type' => $row['file_type'],
            'sent_at' => $row['sent_at'],
            'is_read' => (bool)$row['is_read']
        ];
    }

    // Mark messages as read
    $update_query = "UPDATE chat_messages 
                    SET is_read = TRUE 
                    WHERE receiver_id = ? 
                    AND sender_id = ? 
                    AND is_read = FALSE";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ii", $current_user_id, $other_user_id);
    $update_stmt->execute();

    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch messages: ' . $e->getMessage()
    ]);
}