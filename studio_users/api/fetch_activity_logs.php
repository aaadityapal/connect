<?php
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT id, action_type, description, created_at, is_read, metadata
                           FROM global_activity_logs 
                           WHERE user_id = ? AND is_dismissed = 0
                           ORDER BY created_at DESC 
                           LIMIT 50");
    $stmt->execute([$userId]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $logs]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
