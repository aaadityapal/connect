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
