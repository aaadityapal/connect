<?php
session_start();
require_once 'config.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Senior Manager (Studio)') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['type']) || !isset($data['settings'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    $userId = $_SESSION['user_id'];
    $type = $data['type'];
    $settings = json_encode($data['settings']);

    // Update or insert settings
    $stmt = $pdo->prepare("
        INSERT INTO user_settings (user_id, settings_type, settings_data)
        VALUES (:user_id, :type, :settings)
        ON DUPLICATE KEY UPDATE settings_data = :settings
    ");

    $stmt->execute([
        'user_id' => $userId,
        'type' => $type,
        'settings' => $settings
    ]);

    echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save settings']);
}
?> 