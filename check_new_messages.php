<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Get last check time from request
$last_check = isset($_GET['last_check']) ? $_GET['last_check'] : date('Y-m-d H:i:s', strtotime('-1 minute'));

// Get last processed message ID if available
$last_message_id = isset($_GET['last_message_id']) ? intval($_GET['last_message_id']) : 0;

try {
    // Fetch new messages since last check time and with ID greater than last_message_id
    $query = "SELECT 
        m.id,
        m.conversation_id,
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
        ms.status as message_status
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ?
    WHERE 
        (m.receiver_id = ? OR m.sender_id = ?)
        AND m.sent_at > ?
        AND (? = 0 OR m.id > ?)
        AND m.is_deleted = 0
    ORDER BY m.sent_at ASC, m.id ASC
    LIMIT 50"; // Limit to prevent too many messages

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiisii", $current_user_id, $current_user_id, $current_user_id, $last_check, $last_message_id, $last_message_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $new_messages = [];
    $max_message_id = $last_message_id;
    
    while ($row = $result->fetch_assoc()) {
        // Track the highest message ID
        if ($row['id'] > $max_message_id) {
            $max_message_id = $row['id'];
        }
        
        $new_messages[] = [
            'id' => $row['id'],
            'conversation_id' => $row['conversation_id'],
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
            'status' => $row['message_status'] ?? 'sent'
        ];
    }

    // Fetch unread counts for each user
    $unread_query = "SELECT 
        sender_id,
        COUNT(*) as count
    FROM messages
    WHERE 
        receiver_id = ?
        AND read_at IS NULL
        AND is_deleted = 0
    GROUP BY sender_id";

    $unread_stmt = $conn->prepare($unread_query);
    $unread_stmt->bind_param("i", $current_user_id);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result();

    $unread_counts = [];
    while ($row = $unread_result->fetch_assoc()) {
        $unread_counts[$row['sender_id']] = $row['count'];
    }

    // Return current timestamp for next check
    $current_time = date('Y-m-d H:i:s');

    echo json_encode([
        'success' => true,
        'timestamp' => $current_time,
        'new_messages' => $new_messages,
        'unread_counts' => $unread_counts,
        'last_message_id' => $max_message_id
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch new messages: ' . $e->getMessage()
    ]);
}