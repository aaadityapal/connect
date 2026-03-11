<?php
/**
 * send_bulk_whatsapp.php
 * Endpoint to handle bulk sending of the meeting_schedule_notification
 * WhatsApp template to selected users from the user_registry grid.
 */

session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../whatsapp/WhatsAppService.php';

header('Content-Type: application/json; charset=utf-8');

// Basic validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$user_ids = $_POST['user_ids'] ?? [];
$meeting_date = trim($_POST['meeting_date'] ?? '');
$meeting_time = trim($_POST['meeting_time'] ?? '');
$meeting_day = trim($_POST['meeting_day'] ?? '');
$reach_by = trim($_POST['reach_by'] ?? '');
$na_from = trim($_POST['na_from'] ?? '');
$na_to = trim($_POST['na_to'] ?? '');

if (empty($user_ids)) {
    echo json_encode(['success' => false, 'message' => 'No users selected.']);
    exit;
}

if (!$meeting_date || !$meeting_time || !$meeting_day || !$reach_by || !$na_from || !$na_to) {
    echo json_encode(['success' => false, 'message' => 'All meeting details are required.']);
    exit;
}

// ── Handle PDF Upload ────────────────────────────────────────────────────────
$uploadedPdfUrl = '';
$uploadedPdfName = '';

if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../uploads/meeting_pdfs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $originalName = basename($_FILES['pdf_file']['name']);
    $safeName = 'meeting_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $originalName);
    $uploadPath = $uploadDir . $safeName;

    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $uploadPath)) {
        // Build public URL - Adjust logic based on environment
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];

        // When testing on localhost, WhatsApp cannot download local files.
        // As requested, we use the hardcoded URL when on localhost.
        if ($domainName === 'localhost' || $domainName === '127.0.0.1') {
            $uploadedPdfUrl = 'https://conneqts.io/agenda/Meeting_Agenda.pdf';
            // The {{1}} you are seeing is actually hardcoded in your Facebook Template Manager!
            // I am using a browser path trick (/../) to "skip" that {{1}} folder so the URL works globally immediately.
            $buttonParam = '/../agenda/Meeting_Agenda.pdf';
        } else {
            $uploadedPdfUrl = $protocol . $domainName . '/connect/uploads/meeting_pdfs/' . $safeName;
            $buttonParam = '/../connect/uploads/meeting_pdfs/' . $safeName;
        }
        $uploadedPdfName = $originalName; // Sent to WhatsApp API to display file name
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload PDF schedule.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'A PDF schedule file is required for this template.']);
    exit;
}

// ── Broadcast via WhatsApp ───────────────────────────────────────────────────
$sentCount = 0;
$failedCount = 0;
$logs = [];

try {
    // Dynamically build IN clause
    $inQuery = implode(',', array_fill(0, count($user_ids), '?'));

    // Only fetch active users who actually have a phone number
    $stmt = $pdo->prepare("SELECT id, username, phone FROM users WHERE id IN ($inQuery) AND phone IS NOT NULL AND phone != '' AND LOWER(status) = 'active'");
    $stmt->execute($user_ids);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        echo json_encode(['success' => false, 'message' => 'None of the selected users have valid phone numbers.']);
        exit;
    }

    $waService = new WhatsAppService();

    // Template Params:
    // {{1}} Name
    // {{2}} Date
    // {{3}} Time
    // {{4}} Day
    // {{5}} Report time
    // {{6}} Not available From
    // {{7}} Not available To
    foreach ($users as $user) {
        $phone = $user['phone'];
        $name = trim($user['username']);

        // Use first name as greeting to keep it natural
        $firstName = explode(' ', $name)[0];

        $params = [
            $firstName,    // {{1}}
            $meeting_date, // {{2}}
            $meeting_time, // {{3}}
            $meeting_day,  // {{4}}
            $reach_by,     // {{5}}
            $na_from,      // {{6}}
            $na_to         // {{7}}
        ];

        // Send template with PDF attached and pass the button parameter!
        $result = $waService->sendTemplateMessageWithDocument(
            $phone,
            'meeting_schedule_notification_v2', // Upgraded to v2
            'en_US',
            $params,
            $uploadedPdfUrl,
            $uploadedPdfName,
            $buttonParam
        );

        if ($result['success']) {
            $sentCount++;
            $logs[] = ['user' => $name, 'status' => 'OK'];
        } else {
            $failedCount++;
            $logs[] = ['user' => $name, 'status' => 'FAIL', 'error' => $result['response']];
        }
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
?>