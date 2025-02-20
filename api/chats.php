<?php
header('Content-Type: application/json');
session_start();

require_once '../config/db_connect.php';

try {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('User not authenticated');
    }

    // Check if tables exist
    $tables_exist = true;
    $check_tables = $conn->query("
        SELECT COUNT(*) as count 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
        AND table_name IN ('chats', 'messages')
    ");
    $result = $check_tables->fetch_assoc();
    
    if ($result['count'] < 2) {
        // Return empty data if tables don't exist yet
        echo json_encode([
            'status' => 'success',
            'data' => []
        ]);
        exit;
    }

    // Get all chats for the user
    $query = "SELECT 
        c.id,
        u.username as name,
        COALESCE(u.profile_picture, 'assets/images/default-avatar.png') as avatar,
        m.content as lastMessage,
        m.created_at as lastMessageTime,
        COUNT(CASE WHEN m.read_at IS NULL AND m.sender_id != ? THEN 1 END) as unreadCount
    FROM chats c
    JOIN users u ON (c.user1_id = u.id OR c.user2_id = u.id)
    LEFT JOIN (
        SELECT chat_id, content, created_at, sender_id
        FROM messages
        WHERE deleted_at IS NULL
        ORDER BY created_at DESC
    ) m ON m.chat_id = c.id
    WHERE (c.user1_id = ? OR c.user2_id = ?)
    AND u.id != ?
    AND c.deleted_at IS NULL
    GROUP BY c.id
    ORDER BY COALESCE(m.created_at, c.created_at) DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $chats = [];
    while ($row = $result->fetch_assoc()) {
        $row['lastMessageTime'] = $row['lastMessageTime'] ? date('h:i A', strtotime($row['lastMessageTime'])) : '';
        $chats[] = $row;
    }

    echo json_encode([
        'status' => 'success',
        'data' => $chats
    ]);

} catch (Exception $e) {
    error_log("Chat API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while fetching chats'
    ]);
} 