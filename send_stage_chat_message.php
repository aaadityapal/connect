<?php
/**
 * Send Stage Chat Message
 * Store a new chat message for a project stage
 */

// Set content type to JSON
header('Content-Type: application/json');

// Include database connection
require_once 'config/db_connect.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit;
}

// Get request body
$request_body = file_get_contents('php://input');
$data = json_decode($request_body, true);

// Validate inputs
if (!$data || !isset($data['project_id']) || !isset($data['stage_id']) || !isset($data['content'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

$project_id = intval($data['project_id']);
$stage_id = intval($data['stage_id']);
$message = trim(htmlspecialchars($data['content']));
$user_id = intval($_SESSION['user_id']);
$substage_id = isset($data['substage_id']) ? intval($data['substage_id']) : null;

// Additional validation
if ($project_id <= 0 || $stage_id <= 0 || empty($message)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid parameters'
    ]);
    exit;
}

try {
    // Use the PDO connection from db_connect.php
    
    // Prepare query to insert message
    if ($substage_id) {
        // Insert message with substage_id
        $sql = "INSERT INTO stage_chat_messages (project_id, stage_id, substage_id, user_id, message, timestamp) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([$project_id, $stage_id, $substage_id, $user_id, $message]);
    } else {
        // Insert message without substage_id
        $sql = "INSERT INTO stage_chat_messages (project_id, stage_id, user_id, message, timestamp) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([$project_id, $stage_id, $user_id, $message]);
    }
    
    if ($success) {
        $message_id = $pdo->lastInsertId();
        
        // Get the inserted message details with user name
        $query = "SELECT m.id, m.message, m.timestamp, m.user_id, m.substage_id,
                  u.username as user_name, u.profile_picture
                  FROM stage_chat_messages m
                  JOIN users u ON m.user_id = u.id
                  WHERE m.id = ?";
                  
        $stmt2 = $pdo->prepare($query);
        $stmt2->execute([$message_id]);
        $message_data = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($message_data) {
            // Format the message data for JSON response
            $formatted_message = [
                'id' => intval($message_data['id']),
                'message' => htmlspecialchars_decode($message_data['message']),
                'timestamp' => $message_data['timestamp'],
                'user_id' => intval($message_data['user_id']),
                'user_name' => htmlspecialchars_decode($message_data['user_name']),
                'profile_picture' => $message_data['profile_picture'] ? htmlspecialchars_decode($message_data['profile_picture']) : null,
                'substage_id' => $message_data['substage_id'] ? intval($message_data['substage_id']) : null
            ];
        }
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message_id' => $message_id,
            'message' => 'Message sent successfully',
            'message_data' => $formatted_message ?? null
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Return error response
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save message'
        ]);
    }
    
} catch (Exception $e) {
    // Handle errors
    echo json_encode([
        'success' => false,
        'message' => 'Error sending message: ' . $e->getMessage()
    ]);
}
?> 