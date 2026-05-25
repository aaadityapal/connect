<?php
/**
 * update_subscription_status.php
 * Updates a subscription to Running, Paused, or Stopped.
 */
header("Content-Type: application/json");
require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

$data   = json_decode(file_get_contents("php://input"), true);
$id     = isset($data['id'])     ? (int)$data['id']   : 0;
$status = isset($data['status']) ? $data['status']     : '';

$allowed = ['Running', 'Paused', 'Stopped', 'Completed'];
if (!$id || !in_array($status, $allowed)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid id or status"]);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE sequence_subscriptions SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $id]);
    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
