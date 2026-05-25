<?php
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/campaign_queue_runner.php';

date_default_timezone_set('Asia/Kolkata');

$logFile = __DIR__ . '/../campaign_queue.log';

function campaignQueueLog($file, $event, $payload = [])
{
    @file_put_contents($file, json_encode([
        'time' => date('Y-m-d H:i:s'),
        'event' => $event,
        'payload' => $payload
    ]) . PHP_EOL, FILE_APPEND);
}

$conn = $pdo;

try {
    $summary = runCampaignQueue($conn);
    if ($summary['campaigns'] > 0) {
        campaignQueueLog($logFile, 'queue_tick', $summary);
    }
    echo json_encode(['success' => true, 'summary' => $summary]);
} catch (Exception $e) {
    campaignQueueLog($logFile, 'queue_error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
