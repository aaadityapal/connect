<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['message_id']) || !isset($data['reaction'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit();
}

try {
    // Check if user already reacted to this message
    $check_query = "SELECT id FROM message_reactions 
                   WHERE message_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $data['message_id'], $_SESSION['user_id']);
    $check_stmt->execute();
    $existing_reaction = $check_stmt->get_result()->fetch_assoc();

    if ($existing_reaction) {
        // Update existing reaction
        $update_query = "UPDATE message_reactions 
                        SET reaction = ?, updated_at = CURRENT_TIMESTAMP 
                        WHERE message_id = ? AND user_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sii", $data['reaction'], $data['message_id'], $_SESSION['user_id']);
    } else {
        // Insert new reaction
        $insert_query = "INSERT INTO message_reactions (message_id, user_id, reaction) 
                        VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iis", $data['message_id'], $_SESSION['user_id'], $data['reaction']);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to save reaction");
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}