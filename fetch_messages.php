<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Get user_id from request
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit();
}

try {
    // Fetch messages between current user and the selected user
    $query = "SELECT 
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
        u.profile_picture as sender_avatar,
        ms.status as message_status,
        GROUP_CONCAT(DISTINCT mr.reaction) as reactions
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ?
    LEFT JOIN message_reactions mr ON m.id = mr.message_id
    WHERE 
        ((m.sender_id = ? AND m.receiver_id = ?) 
        OR 
        (m.sender_id = ? AND m.receiver_id = ?))
        AND m.is_deleted = 0
    GROUP BY m.id
    ORDER BY m.sent_at ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiii", $current_user_id, $current_user_id, $user_id, $user_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $reactions = $row['reactions'] ? explode(',', $row['reactions']) : [];
        
        $messages[] = [
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
            'sender_avatar' => $row['sender_avatar'] ?? 'assets/default-avatar.png',
            'status' => $row['message_status'] ?? 'sent',
            'reactions' => $reactions
        ];
    }

    // Mark all messages from this user as read
    $mark_read_query = "UPDATE messages 
                        SET read_at = NOW() 
                        WHERE sender_id = ? 
                        AND receiver_id = ? 
                        AND read_at IS NULL
                        AND is_deleted = 0";
    
    $mark_stmt = $conn->prepare($mark_read_query);
    $mark_stmt->bind_param("ii", $user_id, $current_user_id);
    $mark_stmt->execute();

    // Insert or update message status
    $status_query = "INSERT INTO message_status (message_id, user_id, status, updated_at)
                    SELECT id, ?, 'read', NOW()
                    FROM messages
                    WHERE sender_id = ? AND receiver_id = ? AND is_deleted = 0
                    ON DUPLICATE KEY UPDATE status = 'read', updated_at = NOW()";
    
    $status_stmt = $conn->prepare($status_query);
    $status_stmt->bind_param("iii", $current_user_id, $user_id, $current_user_id);
    $status_stmt->execute();

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
?>