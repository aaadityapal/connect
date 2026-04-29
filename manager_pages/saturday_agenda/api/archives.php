<?php
session_start();
require_once '../../../config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$archiveDir = __DIR__ . '/../../../uploads/agenda_archive/';

if (!is_dir($archiveDir)) {
    echo json_encode(['success' => true, 'archives' => []]);
    exit;
}

$files = glob($archiveDir . 'Meeting_Agenda_*.pdf');
if ($files === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to read archive directory.']);
    exit;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$domainName = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = $protocol . $domainName . '/uploads/agenda_archive/';

$archives = [];
foreach ($files as $path) {
    $base = basename($path);
    if (!preg_match('/Meeting_Agenda_(\d{8})_(\d{6})\.pdf$/', $base, $matches)) {
        continue;
    }
    $datePart = $matches[1];
    $timePart = $matches[2];
    $monthKey = substr($datePart, 0, 6);
    $label = DateTime::createFromFormat('YmdHis', $datePart . $timePart);
    $labelText = $label ? $label->format('d M Y, h:i A') : $base;

    if (!isset($archives[$monthKey])) {
        $monthLabel = DateTime::createFromFormat('Ym', $monthKey);
        $archives[$monthKey] = [
            'month' => $monthLabel ? $monthLabel->format('F Y') : $monthKey,
            'items' => []
        ];
    }

    $archives[$monthKey]['items'][] = [
        'filename' => $base,
        'label' => $labelText,
        'url' => $baseUrl . rawurlencode($base)
    ];
}

$archives = array_values($archives);

echo json_encode([
    'success' => true,
    'archives' => $archives
]);

try {
    $actorId = (int)($_SESSION['user_id'] ?? 0);
    $description = 'Viewed Saturday agenda archive list.';
    $metadata = [
        'module' => 'saturday_agenda',
        'event' => 'archive_viewed',
        'archive_months' => count($archives)
    ];

    $logStmt = $pdo->prepare(
        "INSERT INTO global_activity_logs
            (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read, is_dismissed)
         VALUES
            (:user_id, :action_type, :entity_type, :entity_id, :description, :metadata, NOW(), 0, 0)"
    );

    $logStmt->execute([
        ':user_id' => $actorId,
        ':action_type' => 'agenda_archive_viewed',
        ':entity_type' => 'saturday_agenda',
        ':entity_id' => null,
        ':description' => $description,
        ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ]);
} catch (Throwable $logError) {
    error_log('saturday_agenda archive log skipped: ' . $logError->getMessage());
}
