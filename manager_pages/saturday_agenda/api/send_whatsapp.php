<?php
session_start();
require_once '../../../config/db_connect.php';
require_once '../../../whatsapp/WhatsAppService.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$userIds = $_POST['user_ids'] ?? [];

$meetingDate = trim($_POST['meeting_date'] ?? '');
$meetingTime = trim($_POST['meeting_time'] ?? '');
$meetingDay = trim($_POST['meeting_day'] ?? '');
$reachBy = trim($_POST['reach_by'] ?? '');
$naFrom = trim($_POST['na_from'] ?? '');
$naTo = trim($_POST['na_to'] ?? '');

if (!$meetingDate || !$meetingTime || !$meetingDay || !$reachBy || !$naFrom || !$naTo) {
    echo json_encode(['success' => false, 'message' => 'All meeting details are required.']);
    exit;
}

if (empty($userIds)) {
    echo json_encode(['success' => false, 'message' => 'No users selected.']);
    exit;
}

$uploadedPdfUrl = '';
$uploadedPdfName = '';
$buttonParam = null;

if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
    $originalName = basename($_FILES['pdf_file']['name']);

    $agendaDir = __DIR__ . '/../../../agenda/';
    if (!is_dir($agendaDir)) {
        mkdir($agendaDir, 0755, true);
    }

    $probeFile = $agendaDir . '.write_test';
    $canWrite = @file_put_contents($probeFile, 'ok');
    if ($canWrite === false) {
        $user = function_exists('posix_geteuid') ? posix_geteuid() : 'n/a';
        $owner = function_exists('posix_getpwuid') ? (posix_getpwuid(fileowner($agendaDir))['name'] ?? 'n/a') : 'n/a';
        echo json_encode([
            'success' => false,
            'message' => 'Agenda folder is not writable: ' . $agendaDir,
            'debug' => [
                'uid' => $user,
                'owner' => $owner,
                'perms' => substr(sprintf('%o', fileperms($agendaDir)), -4),
                'realpath' => realpath($agendaDir)
            ]
        ]);
        exit;
    }
    @unlink($probeFile);

    $fixedName = 'Meeting_Agenda.pdf';
    $uploadPath = $agendaDir . $fixedName;
    $archiveDir = __DIR__ . '/../../../uploads/agenda_archive/';
    if (!is_dir($archiveDir)) {
        mkdir($archiveDir, 0755, true);
    }

    if (is_file($uploadPath) && is_dir($archiveDir) && is_writable($archiveDir)) {
        $timestamp = date('Ymd_His');
        $archiveName = 'Meeting_Agenda_' . $timestamp . '.pdf';
        @copy($uploadPath, $archiveDir . $archiveName);
    }

    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $uploadPath)) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
        $domainName = $_SERVER['HTTP_HOST'] ?? '';

        $cacheBuster = 'v=' . date('Ymd_His');
        if ($domainName === 'localhost' || $domainName === '127.0.0.1') {
            $uploadedPdfUrl = 'https://conneqts.io/agenda/Meeting_Agenda.pdf?' . $cacheBuster;
            $buttonParam = null;
        } else {
            $uploadedPdfUrl = $protocol . $domainName . '/agenda/' . $fixedName . '?' . $cacheBuster;
            $buttonParam = null;
        }
        $uploadedPdfName = $fixedName;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload PDF schedule.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'A PDF schedule file is required.']);
    exit;
}

try {
    $actorId = (int)($_SESSION['user_id'] ?? 0);
    $inQuery = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdo->prepare("SELECT id, username, phone FROM users WHERE id IN ($inQuery) AND phone IS NOT NULL AND phone != '' AND LOWER(status) = 'active'");
    $stmt->execute($userIds);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        echo json_encode(['success' => false, 'message' => 'No valid users with phone numbers found.']);
        exit;
    }

    $waService = new WhatsAppService();
    $sentCount = 0;
    $failedCount = 0;
    $logs = [];

    foreach ($users as $user) {
        $phone = preg_replace('/[^0-9]/', '', $user['phone']);
        $name = trim($user['username']);
        $firstName = explode(' ', $name)[0] ?: $name;

        $params = [
            $firstName,
            $meetingDate,
            $meetingTime,
            $meetingDay,
            $reachBy,
            $naFrom,
            $naTo
        ];

        $result = $waService->sendTemplateMessageWithDocument(
            $phone,
            'meeting_schedule_notification_v2',
            'en_US',
            $params,
            $uploadedPdfUrl,
            $uploadedPdfName,
            $buttonParam
        );

        if (!empty($result['success'])) {
            $sentCount++;
            $logs[] = ['user' => $name, 'status' => 'OK'];
        } else {
            $failedCount++;
            $logs[] = ['user' => $name, 'status' => 'FAIL', 'error' => $result['response'] ?? ''];
        }
    }

    try {
        $description = sprintf(
            'Sent Saturday agenda WhatsApp to %d user(s). Success: %d, Failed: %d',
            count($users),
            $sentCount,
            $failedCount
        );
        $metadata = [
            'module' => 'saturday_agenda',
            'event' => 'whatsapp_sent',
            'user_ids' => array_values($userIds),
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'pdf_name' => $uploadedPdfName,
            'pdf_url' => $uploadedPdfUrl,
        ];

        $logStmt = $pdo->prepare(
            "INSERT INTO global_activity_logs
                (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read, is_dismissed)
             VALUES
                (:user_id, :action_type, :entity_type, :entity_id, :description, :metadata, NOW(), 0, 0)"
        );

        $logStmt->execute([
            ':user_id' => $actorId,
            ':action_type' => 'agenda_whatsapp_sent',
            ':entity_type' => 'saturday_agenda',
            ':entity_id' => null,
            ':description' => $description,
            ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);
    } catch (Throwable $logError) {
        error_log('saturday_agenda send log skipped: ' . $logError->getMessage());
    }

    echo json_encode([
        'success' => ($sentCount > 0),
        'message' => ($sentCount > 0) ? 'Messages broadcast process completed.' : 'All messages failed to send.',
        'sentCount' => $sentCount,
        'failedCount' => $failedCount,
        'logs' => $logs
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error: ' . $e->getMessage()
    ]);
}
