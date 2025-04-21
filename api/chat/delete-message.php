<?php
/**
 * Delete Chat Message
 * Removes an existing chat message from the database
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

// Get message ID from the URL
$message_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate inputs
if ($message_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid message ID'
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
            'message' => 'You do not have permission to delete this message'
        ]);
        exit;
    }
    
    // Prepare query to delete the message
    $delete_sql = "DELETE FROM stage_chat_messages WHERE id = ?";
    $delete_stmt = $pdo->prepare($delete_sql);
    
    // Execute the delete
    $delete_result = $delete_stmt->execute([$message_id]);
    
    if ($delete_result) {
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Message deleted successfully',
            'message_id' => $message_id
        ]);
    } else {
        // Return error response
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete message'
        ]);
    }
} catch (Exception $e) {
    // Handle errors
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting message: ' . $e->getMessage()
    ]);
    
    // Log the error
    error_log('Error in delete-message.php: ' . $e->getMessage());
}
?> 