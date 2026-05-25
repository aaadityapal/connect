<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../../config/db_connect.php';

$conn = $pdo;

try {
    $totalsStmt = $conn->query("SELECT COUNT(*) AS total_campaigns, SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_campaigns, SUM(CASE WHEN status = 'Running' THEN 1 ELSE 0 END) AS queued_campaigns FROM campaigns");
    $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $clientTotalsStmt = $conn->query("SELECT COUNT(*) AS total_clients FROM campaign_deliveries");
    $clientTotals = $clientTotalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totals['total_clients'] = (int)($clientTotals['total_clients'] ?? 0);

    $stmt = $conn->query("
        SELECT
            c.id,
            c.name,
            c.status,
            DATE_FORMAT(c.created_at, '%M %d, %h:%i %p') AS created_label,
            COUNT(d.id) AS total_clients,
            SUM(CASE WHEN d.status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN d.status = 'Sent' THEN 1 ELSE 0 END) AS sent_count,
            SUM(CASE WHEN d.status = 'Delivered' THEN 1 ELSE 0 END) AS delivered_count,
            SUM(CASE WHEN d.status = 'Read' THEN 1 ELSE 0 END) AS read_count,
            SUM(CASE WHEN d.status = 'Failed' THEN 1 ELSE 0 END) AS failed_count
        FROM campaigns c
        LEFT JOIN campaign_deliveries d ON d.campaign_id = c.id
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT 200
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "totals" => $totals, "rows" => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
