<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['html_file']) || !is_array($_FILES['html_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'HTML file is required']);
    exit;
}

$file = $_FILES['html_file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit;
}

$uploadDir = '../../uploads/employee_salary_exports/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
        exit;
    }
}

$requestedName = trim((string) ($_POST['filename'] ?? ''));
if ($requestedName === '') {
    $requestedName = 'employee_salary_export_' . date('Ymd_His') . '.html';
}

$baseName = pathinfo($requestedName, PATHINFO_FILENAME);
$extension = strtolower(pathinfo($requestedName, PATHINFO_EXTENSION));
$extension = $extension === 'html' ? 'html' : 'html';
$safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
$finalName = $safeBase . '_' . date('Ymd_His') . '.' . $extension;
$targetPath = $uploadDir . $finalName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit;
}

$whatsappSent = false;
$whatsappError = null;

try {
    require_once __DIR__ . '/../../whatsapp/WhatsAppService.php';

    $waService = new WhatsAppService();
    $recipients = [
        '917224864553',
        '919958600397'
    ];

    $prevMonth = new DateTime('first day of last month');
    $monthName = $prevMonth->format('F');
    $year = $prevMonth->format('Y');

    $publicUrl = 'https://conneqts.io/uploads/employee_salary_exports/' . $finalName;

    foreach ($recipients as $recipient) {
        $recipient = preg_replace('/\D+/', '', $recipient);
        if ($recipient === '') {
            continue;
        }

        $waResult = $waService->sendTemplateMessage(
            $recipient,
            'monthly_salarynew',
            'en_US',
            [
                $monthName,
                $year,
                $publicUrl
            ]
        );

        $whatsappSent = $whatsappSent || !empty($waResult['success']);
        if (empty($waResult['success'])) {
            $whatsappError = $waResult['response'] ?? $waResult['message'] ?? 'Unknown error';
        }
    }
} catch (Throwable $e) {
    $whatsappError = $e->getMessage();
}

echo json_encode([
    'success' => true,
    'filename' => $finalName,
    'path' => $targetPath,
    'whatsapp_sent' => $whatsappSent,
    'whatsapp_error' => $whatsappError
]);
