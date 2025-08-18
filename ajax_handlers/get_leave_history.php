<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db_connect.php';

try {
    $userId = (int)$_SESSION['user_id'];

    $id    = isset($_GET['id'])    ? (int)$_GET['id']    : null;
    $month = isset($_GET['month']) ? (int)$_GET['month'] : null; // 1-12
    $year  = isset($_GET['year'])  ? (int)$_GET['year']  : null; // YYYY

    $where = ['lr.user_id = :uid'];
    $params = ['uid' => $userId];

    if ($id) {
        $where[] = 'lr.id = :id';
        $params['id'] = $id;
    }
    if ($month !== null && $month >= 1 && $month <= 12) {
        $where[] = 'MONTH(lr.start_date) = :m';
        $params['m'] = $month;
    }
    if ($year !== null && $year >= 2000 && $year <= 2100) {
        $where[] = 'YEAR(lr.start_date) = :y';
        $params['y'] = $year;
    }

    $sql = "
        SELECT
            lr.id,
            lr.start_date,
            lr.end_date,
            lr.reason,
            lr.duration,
            lr.status,
            lr.manager_approval,
            lr.duration_type,
            lr.day_type,
            lr.time_from,
            lr.time_to,
            lr.created_at,
            lr.leave_type,
            lt.name AS leave_type_name,
            lt.color_code AS leave_color
        FROM leave_request lr
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type
        WHERE " . implode(' AND ', $where) . "
        ORDER BY lr.start_date DESC, lr.id DESC
        " . ($id ? 'LIMIT 1' : 'LIMIT 300') . "
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($id) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'row' => $row]);
        exit();
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'rows' => $rows]);
} catch (Throwable $e) {
    error_log('get_leave_history failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch history']);
}
