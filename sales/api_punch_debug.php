<?php
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'user_id' => $_SESSION['user_id'] ?? null,
    'input_size' => strlen($input),
    'photo_size' => strlen($data['photo'] ?? ''),
    'photo_prefix' => substr($data['photo'] ?? '', 0, 50),
    'latitude' => $data['latitude'] ?? null,
    'longitude' => $data['longitude'] ?? null,
    'camera' => $data['camera'] ?? null,
];

// Log to file
file_put_contents(__DIR__ . '/punch_debug.log', json_encode($logData) . "\n", FILE_APPEND);

echo json_encode([
    'success' => true,
    'message' => 'Debug logged',
    'received' => $logData
]);
?>
