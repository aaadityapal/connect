<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_punch_notification.php';
require_once __DIR__ . '/generate_punchout_summary_pdf.php';
require_once __DIR__ . '/WhatsAppService.php';

try {
    $pdo = getDBConnection();
    $date = date('Y-m-d');
    $teamType = 'Studio';
    
    // 1. Get punchout data
    $punchOutData = getPunchOutDataByTeam($pdo, $date, $teamType);
    
    // 2. Generate PDF
    $pdfResult = generatePunchOutSummaryPDF($punchOutData, $date, $teamType);
    
    if (!$pdfResult['success']) {
        die("PDF Generation Failed: " . $pdfResult['error']);
    }

    echo "PDF URL: " . $pdfResult['url'] . "\n";
    
    // 3. Send to specific number
    $waService = new WhatsAppService();
    $phone = "917224864553";
    $currentTime = date('h:i A'); // Current time
    
    // Template
    $templateName = 'admin_punchout_summary_studio';
    $totalCount = count($punchOutData);
    $summaryText = $totalCount > 0 ? "Total employees punched out: {$totalCount}. Please see attached PDF for detailed work reports." : "No punch-outs recorded yet for today.";

    $params = [
        $currentTime,
        $summaryText
    ];

    $result = $waService->sendTemplateMessageWithDocument(
        $phone,
        $templateName,
        'en_US',
        $params,
        $pdfResult['url'],
        $pdfResult['file_name']
    );

    if ($result['success']) {
        echo "Successfully sent to $phone\n";
    } else {
        echo "Failed to send: \n";
        print_r($result);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
