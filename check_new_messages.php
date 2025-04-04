<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once 'config/db_connect.php';

$last_check = isset($_GET['last_check']) ? $_GET['last_check'] : null;
$user_id = $_SESSION['user_id'];

try {
    // First, mark previously received messages as read
    if ($last_check) {
        $mark_read_query = "UPDATE chat_messages 
                           SET read_at = NOW() 
                           WHERE receiver_id = ? 
                           AND sent_at <= ? 
                           AND read_at IS NULL";
        $mark_stmt = $conn->prepare($mark_read_query);
        $mark_stmt->bind_param("is", $user_id, $last_check);
        $mark_stmt->execute();
    }

    // Get only new messages that haven't been received yet
    $query = "SELECT 
                m.*, 
                u.username as sender_name, 
                u.profile_picture as sender_avatar,
                u.status as sender_status,
                (SELECT COUNT(*) FROM chat_message_reactions WHERE message_id = m.id) as reaction_count,
                GROUP_CONCAT(DISTINCT r.reaction) as reactions
              FROM chat_messages m 
              JOIN users u ON m.sender_id = u.id 
              LEFT JOIN chat_message_reactions r ON m.id = r.message_id
              WHERE m.sent_at > ? 
              AND (
                  m.receiver_id = ? 
                  OR m.receiver_id IN (
                      SELECT group_id 
                      FROM chat_group_members 
                      WHERE user_id = ?
                  )
              )
              AND m.deleted_at IS NULL 
              AND (m.read_at IS NULL OR m.sender_id = ?)  # Only get unread messages or sent by current user
              GROUP BY m.id
              ORDER BY m.sent_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("siii", $last_check, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $new_messages = [];
    while ($row = $result->fetch_assoc()) {
        // Set default avatar if none exists
        $row['sender_avatar'] = $row['sender_avatar'] ?: 'assets/default-avatar.png';
        
        // Add message type (individual or group)
        $row['message_type'] = is_null($row['group_id']) ? 'individual' : 'group';
        
        // Add reactions array if any
        if ($row['reactions']) {
            $row['reactions'] = explode(',', $row['reactions']);
        } else {
            $row['reactions'] = [];
        }
        
        $new_messages[] = $row;
    }

    // Get unread counts only for messages that haven't been read
    $unread_query = "SELECT 
                        CASE 
                            WHEN group_id IS NOT NULL THEN CONCAT('group_', group_id)
                            ELSE CONCAT('user_', sender_id)
                        END as chat_id,
                        COUNT(*) as count 
                     FROM chat_messages 
                     WHERE (receiver_id = ? OR 
                           group_id IN (SELECT group_id FROM chat_group_members WHERE user_id = ?))
                     AND read_at IS NULL 
                     AND deleted_at IS NULL 
                     AND sender_id != ?
                     GROUP BY 
                        CASE 
                            WHEN group_id IS NOT NULL THEN CONCAT('group_', group_id)
                            ELSE CONCAT('user_', sender_id)
                        END";
    
    $unread_stmt = $conn->prepare($unread_query);
    $unread_stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result();
    
    $unread_counts = [];
    while ($row = $unread_result->fetch_assoc()) {
        $unread_counts[$row['chat_id']] = $row['count'];
    }

    // Get online users status
    $online_query = "SELECT id, status, last_active 
                    FROM users 
                    WHERE last_active >= NOW() - INTERVAL 5 MINUTE
                    AND id != ?";
    
    $online_stmt = $conn->prepare($online_query);
    $online_stmt->bind_param("i", $user_id);
    $online_stmt->execute();
    $online_result = $online_stmt->get_result();
    
    $online_users = [];
    while ($row = $online_result->fetch_assoc()) {
        $online_users[$row['id']] = [
            'status' => $row['status'],
            'last_active' => $row['last_active']
        ];
    }

    // Update user's last active timestamp
    $update_status = "UPDATE users SET last_active = NOW() WHERE id = ?";
    $status_stmt = $conn->prepare($update_status);
    $status_stmt->bind_param("i", $user_id);
    $status_stmt->execute();

    // Get the latest timestamp from received messages
    $current_timestamp = date('Y-m-d H:i:s');
    $latest_timestamp = $current_timestamp;
    if (!empty($new_messages)) {
        $latest_message = end($new_messages);
        $latest_timestamp = $latest_message['sent_at'];
    }

    echo json_encode([
        'success' => true,
        'timestamp' => $latest_timestamp, // Use the latest message timestamp
        'new_messages' => $new_messages,
        'unread_counts' => $unread_counts,
        'online_users' => $online_users,
        'current_user_id' => $user_id
    ]);

} catch (Exception $e) {
    error_log("Error in check_new_messages.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database error',
        'debug_message' => $e->getMessage()
    ]);
}

$conn->close();
?>