<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if (!isset($_GET['message_id'])) {
    echo json_encode(['success' => false, 'error' => 'Message ID not provided']);
    exit();
}

try {
    $query = "SELECT mr.reaction, mr.user_id, u.username 
              FROM message_reactions mr 
              JOIN users u ON mr.user_id = u.id 
              WHERE mr.message_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_GET['message_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reactions = [];
    while ($row = $result->fetch_assoc()) {
        $reactions[] = $row;
    }

    echo json_encode([
        'success' => true,
        'reactions' => $reactions
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}