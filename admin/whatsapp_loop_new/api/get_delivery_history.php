<?php
/**
 * get_delivery_history.php
 * Returns all delivery records for a specific subscription.
 */
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

$subscription_id = isset($_GET['subscription_id']) ? (int)$_GET['subscription_id'] : 0;
if (!$subscription_id) {
    http_response_code(400);
    echo json_encode(["error" => "subscription_id is required"]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT
            sd.id,
            sd.template_name,
            sd.status,
            sd.sent_at,
            sd.delivered_at,
            sd.read_at,
            sd.replied_at,
            sd.error_message,
            ss.step_order
        FROM sequence_deliveries sd
        LEFT JOIN sequence_steps ss ON sd.sequence_step_id = ss.id
        WHERE sd.subscription_id = ?
        ORDER BY sd.sent_at DESC
    ");
    $stmt->execute([$subscription_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
