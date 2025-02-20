<?php
session_start();
require_once 'config/db_connect.php';

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $sender_id = $data['sender_id'];
    $receiver_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        UPDATE chat_messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ?
    ");
    
    $stmt->bind_param("ii", $sender_id, $receiver_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
    }
}

header('Content-Type: application/json');
echo json_encode($response); 