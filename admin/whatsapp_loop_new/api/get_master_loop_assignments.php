<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/db_connect.php';

date_default_timezone_set('Asia/Kolkata');

$conn = $pdo;

try {
    $stmt = $conn->query('
        SELECT mla.id,
               mla.master_loop_id,
               mla.master_loop_name,
               mla.client_id,
               mla.client_name,
               mla.client_phone,
               mla.status,
               mla.assigned_at,
             mla.current_step_order,
             mla.next_send_at,
             mla.last_sent_at,
               (SELECT COUNT(*) FROM master_loop_steps mls WHERE mls.master_loop_id = mla.master_loop_id) AS total_steps,
             GREATEST(mla.current_step_order - 1, 0) AS done_steps
          FROM master_loop_assignments mla
      ORDER BY mla.assigned_at DESC
    ');
    $rows = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
