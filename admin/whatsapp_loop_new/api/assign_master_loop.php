<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../config/db_connect.php';

date_default_timezone_set('Asia/Kolkata');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$loop_id = isset($data['master_loop_id']) ? (int)$data['master_loop_id'] : 0;
$client_id = isset($data['client_id']) ? (int)$data['client_id'] : 0;

if ($loop_id <= 0 || $client_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit;
}

$conn = $pdo;

try {
    $stmt = $conn->prepare('SELECT name FROM master_loops WHERE id = ?');
    $stmt->execute([$loop_id]);
    $loop = $stmt->fetch();
    if (!$loop) throw new Exception('Loop not found');

    $stmt = $conn->prepare('SELECT name, phone FROM clients WHERE id = ?');
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    if (!$client) throw new Exception('Client not found');

    $stmt = $conn->prepare('SELECT delay_value, delay_unit FROM master_loop_steps WHERE master_loop_id = ? ORDER BY step_order ASC LIMIT 1');
    $stmt->execute([$loop_id]);
    $firstStep = $stmt->fetch();

    $stmt = $conn->prepare('SELECT status FROM master_loop_assignments WHERE master_loop_id = ? AND client_id = ? LIMIT 1');
    $stmt->execute([$loop_id, $client_id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $existingStatus = strtolower(trim((string)($existing['status'] ?? '')));
        if ($existingStatus === 'completed') {
            http_response_code(409);
            echo json_encode(['error' => 'This client has already completed this loop.']);
            exit;
        }

        http_response_code(409);
        echo json_encode(['error' => 'This client is already enrolled in this loop.']);
        exit;
    }

    $nextSendAt = null;
    if ($firstStep) {
        $delayVal = (int)($firstStep['delay_value'] ?? 0);
        $delayUnit = strtolower($firstStep['delay_unit'] ?? 'days');
        $dt = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        if ($delayVal > 0) {
            $intervalMap = [
                'minutes' => 'PT' . $delayVal . 'M',
                'hours'   => 'PT' . $delayVal . 'H',
                'days'    => 'P'  . $delayVal . 'D',
                'weeks'   => 'P'  . ($delayVal * 7) . 'D',
                'months'  => 'P'  . $delayVal . 'M'
            ];
            $dt->add(new DateInterval($intervalMap[$delayUnit] ?? ('P' . $delayVal . 'D')));
        }
        $nextSendAt = $dt->format('Y-m-d H:i:s');
    }

    $stmt = $conn->prepare("
        INSERT INTO master_loop_assignments
            (master_loop_id, master_loop_name, client_id, client_name, client_phone, status, current_step_order, next_send_at, assigned_at)
        VALUES (?, ?, ?, ?, ?, 'Assigned', 1, ?, NOW())
    ");
    $stmt->execute([
        $loop_id,
        $loop['name'],
        $client_id,
        $client['name'],
        $client['phone'],
        $nextSendAt
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['error' => 'This client is already enrolled in this loop.']);
        exit;
    }

    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
