<?php
/**
 * Edit Chat Message
 * Updates the content of an existing chat message
 */

// Set content type to JSON
header('Content-Type: application/json');

// Include database connection
require_once '../../config/db_connect.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit;
}

// Get current user ID
$current_user_id = $_SESSION['user_id'];

// Get message ID and new content from request body
$input = json_decode(file_get_contents('php://input'), true);
$message_id = isset($input['id']) ? intval($input['id']) : 0;
$new_content = isset($input['content']) ? trim($input['content']) : '';

// Validate inputs
if ($message_id <= 0 || empty($new_content)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid message ID or content'
    ]);
    exit;
}

try {
    // First, check if the user owns this message or is an admin
    $check_sql = "SELECT user_id FROM stage_chat_messages WHERE id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$message_id]);
    $message_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // If message doesn't exist
    if (!$message_data) {
        echo json_encode([
            'success' => false,
            'message' => 'Message not found'
        ]);
        exit;
    }
    
    // Check if current user is message owner or admin
    $is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    if (intval($message_data['user_id']) !== $current_user_id && !$is_admin) {
        echo json_encode([
            'success' => false,
            'message' => 'You do not have permission to edit this message'
        ]);
        exit;
    }
    
    // Prepare query to update the message
    $update_sql = "UPDATE stage_chat_messages SET message = ?, edited = 1, edited_timestamp = NOW() WHERE id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    
    // HTML escape the content to prevent XSS
    $escaped_content = htmlspecialchars($new_content, ENT_QUOTES, 'UTF-8');
    
    // Execute the update
    $update_result = $update_stmt->execute([$escaped_content, $message_id]);
    
    if ($update_result) {
        // Get the updated message data
        $get_sql = "SELECT m.id, m.message, m.timestamp, m.user_id, 
                    u.username as user_name, u.profile_picture,
                    m.edited, m.edited_timestamp
                    FROM stage_chat_messages m
                    JOIN users u ON m.user_id = u.id
                    WHERE m.id = ?";
        
        $get_stmt = $pdo->prepare($get_sql);
        $get_stmt->execute([$message_id]);
        $updated_message = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return success response with updated message
        echo json_encode([
            'success' => true,
            'message' => 'Message updated successfully',
            'data' => [
                'id' => intval($updated_message['id']),
                'message' => htmlspecialchars_decode($updated_message['message']),
                'timestamp' => $updated_message['timestamp'],
                'user_id' => intval($updated_message['user_id']),
                'user_name' => htmlspecialchars_decode($updated_message['user_name']),
                'profile_picture' => $updated_message['profile_picture'] ? htmlspecialchars_decode($updated_message['profile_picture']) : null,
                'edited' => (bool)$updated_message['edited'],
                'edited_timestamp' => $updated_message['edited_timestamp']
            ]
        ]);
    } else {
        // Return error response
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update message'
        ]);
    }
} catch (Exception $e) {
    // Handle errors
    echo json_encode([
        'success' => false,
        'message' => 'Error updating message: ' . $e->getMessage()
    ]);
    
    // Log the error
    error_log('Error in edit-message.php: ' . $e->getMessage());
}
?> 