<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$other_user_id = $_GET['user_id'];

try {
    $query = "SELECT m.*, u.username as sender_name 
              FROM messages m 
              JOIN users u ON m.sender_id = u.id 
              WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                 OR (m.sender_id = ? AND m.receiver_id = ?) 
              ORDER BY m.created_at ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$current_user_id, $other_user_id, $other_user_id, $current_user_id]);
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'debug' => $e->getMessage()
    ]);
}