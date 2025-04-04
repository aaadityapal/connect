<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once 'config/db_connect.php';

$last_check = isset($_GET['last_check']) ? $_GET['last_check'] : null;

try {
    // Get new messages
    $query = "SELECT m.*, u.username as sender_name, u.profile_picture as sender_avatar 
              FROM chat_messages m 
              JOIN users u ON m.sender_id = u.id 
              WHERE m.sent_at > ? 
              AND m.receiver_id = ? 
              AND m.deleted_at IS NULL 
              ORDER BY m.sent_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $last_check, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $new_messages = [];
    while ($row = $result->fetch_assoc()) {
        // Set default avatar if none exists
        $row['sender_avatar'] = $row['sender_avatar'] ?: 'assets/default-avatar.png';
        $new_messages[] = $row;
    }

    // Get unread counts
    $unread_query = "SELECT sender_id, COUNT(*) as count 
                     FROM chat_messages 
                     WHERE receiver_id = ? 
                     AND read_at IS NULL 
                     AND deleted_at IS NULL 
                     GROUP BY sender_id";
    
    $unread_stmt = $conn->prepare($unread_query);
    $unread_stmt->bind_param("i", $_SESSION['user_id']);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result();
    
    $unread_counts = [];
    while ($row = $unread_result->fetch_assoc()) {
        $unread_counts[$row['sender_id']] = $row['count'];
    }

    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'new_messages' => $new_messages,
        'unread_counts' => $unread_counts
    ]);

} catch (Exception $e) {
    error_log("Error in check_new_messages.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

$conn->close();
?>