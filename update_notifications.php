<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['type']) || !isset($data['enabled'])) {
        throw new Exception('Invalid request');
    }
    
    $type = $data['type'];
    $enabled = $data['enabled'] ? 1 : 0;
    
    // Update notification preferences
    $stmt = $pdo->prepare("
        INSERT INTO user_preferences (user_id, preference_key, preference_value)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE preference_value = ?
    ");
    
    $stmt->execute([$user_id, $type, $enabled, $enabled]);
    
    $response = ['success' => true, 'message' => 'Preferences updated successfully'];
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response); 