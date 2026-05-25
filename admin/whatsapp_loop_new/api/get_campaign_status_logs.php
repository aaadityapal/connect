<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

$campaignId = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : 0;

try {
    if ($campaignId > 0) {
        $stmt = $conn->prepare('
            SELECT l.*, c.name AS campaign_name
              FROM campaign_message_logs l
              JOIN campaigns c ON c.id = l.campaign_id
             WHERE l.campaign_id = ?
             ORDER BY l.id DESC
             LIMIT 200
        ');
        $stmt->execute([$campaignId]);
    } else {
        $stmt = $conn->query('
            SELECT l.*, c.name AS campaign_name
              FROM campaign_message_logs l
              JOIN campaigns c ON c.id = l.campaign_id
             ORDER BY l.id DESC
             LIMIT 200
        ');
    }

    $rows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['details'] = json_decode($row['details'] ?? '', true);
        $row['created_label'] = date('M d, h:i A', strtotime($row['created_at']));
        $rows[] = $row;
    }

    echo json_encode(["success" => true, "data" => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
