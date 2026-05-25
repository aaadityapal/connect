<?php
/**
 * get_subscriptions.php
 * Returns all sequence subscriptions with step progress counts.
 */
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

try {
    $stmt = $conn->prepare("
        SELECT
            sub.id,
            sub.sequence_id,
            sub.sequence_name,
            sub.client_id,
            sub.client_name,
            sub.client_phone,
            sub.current_step_id,
            sub.current_step_order,
            sub.status,
            sub.next_send_at,
            sub.last_sent_at,
            sub.enrolled_at,
            -- Total steps in this sequence
            (SELECT COUNT(*) FROM sequence_steps ss WHERE ss.sequence_id = sub.sequence_id) AS total_steps,
            -- Steps already sent (deliveries marked Sent/Delivered/Read/Replied)
            (SELECT COUNT(*) FROM sequence_deliveries sd WHERE sd.subscription_id = sub.id AND sd.status NOT IN ('Failed','Pending')) AS done_steps
        FROM sequence_subscriptions sub
        ORDER BY sub.enrolled_at DESC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast numeric fields
    foreach ($rows as &$r) {
        $r['id']                  = (int)$r['id'];
        $r['sequence_id']         = (int)$r['sequence_id'];
        $r['client_id']           = (int)$r['client_id'];
        $r['current_step_order']  = (int)($r['current_step_order'] ?? 0);
        $r['total_steps']         = (int)$r['total_steps'];
        $r['done_steps']          = (int)$r['done_steps'];
    }

    echo json_encode($rows);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
