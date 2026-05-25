<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/db_connect.php';

date_default_timezone_set('Asia/Kolkata');

$conn = $pdo;

try {
    $stmt = $conn->query('
        SELECT ml.id, ml.name, ml.status,
               COALESCE(COUNT(mls.id), 0) AS step_count
          FROM master_loops ml
     LEFT JOIN master_loop_steps mls ON mls.master_loop_id = ml.id
      GROUP BY ml.id
      ORDER BY ml.updated_at DESC
    ');

    $rows = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
