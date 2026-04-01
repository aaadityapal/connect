<?php
/**
 * UPDATE USER METERS CONFIG
 * studio_users/api/update_user_meters_config.php
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
$target_user_id = $data['user_id'] ?? null;
$mode = $data['meter_mode'] ?? null;

if (!$target_user_id || $mode === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

try {
    // 1. Update or Insert
    $stmt = $pdo->prepare("REPLACE INTO travel_meter_mode_config (user_id, meter_mode) VALUES (?, ?)");
    $stmt->execute([$target_user_id, $mode]);

    echo json_encode(['success' => true, 'message' => 'User meter configuration updated successfully!']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>
