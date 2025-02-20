<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get users who have had conversations with the current user
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            u.id,
            u.username,
            (
                SELECT message 
                FROM chat_messages 
                WHERE (sender_id = u.id AND receiver_id = ?) 
                   OR (sender_id = ? AND receiver_id = u.id)
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message,
            (
                SELECT created_at 
                FROM chat_messages 
                WHERE (sender_id = u.id AND receiver_id = ?) 
                   OR (sender_id = ? AND receiver_id = u.id)
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message_time,
            (
                SELECT COUNT(*) 
                FROM chat_messages 
                WHERE sender_id = u.id 
                AND receiver_id = ? 
                AND is_read = 0
            ) as unread_count
        FROM users u
        WHERE u.id IN (
            SELECT DISTINCT 
                CASE 
                    WHEN sender_id = ? THEN receiver_id 
                    ELSE sender_id 
                END
            FROM chat_messages
            WHERE sender_id = ? OR receiver_id = ?
        )
        AND u.id != ?
        ORDER BY last_message_time DESC
    ");

    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 