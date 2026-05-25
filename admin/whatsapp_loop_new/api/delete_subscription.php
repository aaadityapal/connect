<?php
/**
 * delete_subscription.php
 * Permanently removes a client's enrollment from a sequence.
 */
header("Content-Type: application/json");
require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

$data = json_decode(file_get_contents("php://input"), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid subscription id"]);
    exit;
}

try {
    // Delete delivery history first (foreign key safety)
    $conn->prepare("DELETE FROM sequence_deliveries WHERE subscription_id = ?")->execute([$id]);
    // Delete the subscription
    $conn->prepare("DELETE FROM sequence_subscriptions WHERE id = ?")->execute([$id]);

    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
