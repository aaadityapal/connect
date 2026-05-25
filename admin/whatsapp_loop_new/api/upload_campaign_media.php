<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../../../config/db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['data_url']) || empty($data['file_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid media payload']);
    exit;
}

$baseDir = __DIR__ . '/../uploads/campaign_media';
if (!is_dir($baseDir)) {
    @mkdir($baseDir, 0777, true);
}

$base64_pos = strpos($data['data_url'], ';base64,');
$raw = $base64_pos !== false ? substr($data['data_url'], $base64_pos + 8) : $data['data_url'];
$binary = base64_decode($raw);
if ($binary === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unable to decode media file']);
    exit;
}

$safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($data['file_name']));
$storedName = time() . '_' . $safeName;
$fullPath = $baseDir . '/' . $storedName;

if (file_put_contents($fullPath, $binary) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to store media file']);
    exit;
}

$relative = 'uploads/campaign_media/' . $storedName;

echo json_encode([
    'success' => true,
    'path' => $relative,
    'name' => $data['file_name'],
    'wa_id' => null
]);
