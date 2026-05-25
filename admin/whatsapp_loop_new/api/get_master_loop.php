<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/db_connect.php';

date_default_timezone_set('Asia/Kolkata');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

$conn = $pdo;

try {
    $stmt = $conn->prepare('SELECT id, name, status FROM master_loops WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $loop = $stmt->fetch();

    if (!$loop) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Loop not found']);
        exit;
    }

    $stepsStmt = $conn->prepare('
        SELECT step_order, template_key, header_type, delay_value, delay_unit, media_path, media_filename
          FROM master_loop_steps
         WHERE master_loop_id = ?
         ORDER BY step_order ASC
    ');
    $stepsStmt->execute([$id]);
    $steps = $stepsStmt->fetchAll();

    $loop['steps'] = $steps;

    echo json_encode(['success' => true, 'data' => $loop]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
