<?php
/**
 * Get Stage Chat Messages
 * Retrieve chat messages for a specific project stage
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

// Get project ID and stage ID from request
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$stage_id = isset($_GET['stage_id']) ? intval($_GET['stage_id']) : 0;
$substage_id = isset($_GET['substage_id']) ? intval($_GET['substage_id']) : null;

// Validate inputs
if ($project_id <= 0 || $stage_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid project or stage ID'
    ]);
    exit;
}

try {
    // Use the PDO connection from db_connect.php
    // Prepare query to get messages
    if ($substage_id) {
        // For substage chat messages
        $sql = "SELECT m.id, m.message, m.timestamp, m.user_id, 
                u.username as user_name, u.profile_picture
                FROM stage_chat_messages m
                JOIN users u ON m.user_id = u.id
                WHERE m.project_id = ? AND m.stage_id = ? AND m.substage_id = ?
                ORDER BY m.timestamp ASC
                LIMIT 100";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$project_id, $stage_id, $substage_id]);
    } else {
        // For stage chat messages (no substage)
        $sql = "SELECT m.id, m.message, m.timestamp, m.user_id, 
                u.username as user_name, u.profile_picture
                FROM stage_chat_messages m
                JOIN users u ON m.user_id = u.id
                WHERE m.project_id = ? AND m.stage_id = ? AND m.substage_id IS NULL
                ORDER BY m.timestamp ASC
                LIMIT 100";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$project_id, $stage_id]);
    }
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedMessages = [];
    foreach ($messages as $row) {
        // Make sure we're only including valid data in our JSON response
        $formattedMessages[] = [
            'id' => intval($row['id']),
            'message' => htmlspecialchars_decode($row['message']),
            'timestamp' => $row['timestamp'],
            'user_id' => intval($row['user_id']),
            'user_name' => htmlspecialchars_decode($row['user_name']),
            'profile_picture' => $row['profile_picture'] ? htmlspecialchars_decode($row['profile_picture']) : null
        ];
    }
    
    // Return messages as JSON
    echo json_encode([
        'success' => true,
        'messages' => $formattedMessages
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Handle errors
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving chat messages: ' . $e->getMessage()
    ]);
}
?> 