<?php
/**
 * Test Script: Send Monthly Report for User ID 7 to Custom Number
 * Usage: php test_report_custom_number.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/WhatsAppService.php';
require_once __DIR__ . '/generate_monthly_report_v2.php';

// Set Timezone
date_default_timezone_set('Asia/Kolkata');

// Configuration
$targetUserId = 7; // User ID to generate report for
$targetMonth = date('m', strtotime('last month'));
$targetYear = date('Y', strtotime('last month'));
$overridePhoneNumber = '7224864553'; // sending to this number instead of DB number

echo "Starting Test Report Generation for User ID: $targetUserId (Month: $targetMonth/$targetYear)...\n";
echo "Target Phone Number: $overridePhoneNumber\n";

try {
    $pdo = getDBConnection();

    // 1. Fetch User Details
    $stmt = $pdo->prepare("SELECT id, username, phone FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("Error: User ID $targetUserId not found in database.\n");
    }

    echo "User Found in DB: {$user['username']} (Original Phone: {$user['phone']})\n";

    // 2. Generate PDF
    echo "Generating PDF...\n";
    $result = generateMonthlyReportPDF($targetUserId, $targetMonth, $targetYear, $pdo);

    if (!$result['success']) {
        die("PDF Generation Failed: " . $result['error'] . "\n");
    }

    $pdfUrl = $result['url'];
    $filePath = $result['file_path'];
    $stats = $result['stats'];
    $fileName = basename($filePath);

    echo "PDF Generated Successfully: $filePath\n";
    echo "Stats: Present: {$stats['present']}, Absent: {$stats['absent']}, Working: {$stats['working']}\n";

    // 3. Send WhatsApp
    $waService = new WhatsAppService();

    // Template params matches cron_monthly_report.php
    $monthNameFull = date('F Y', mktime(0, 0, 0, $targetMonth, 10, $targetYear));

    $params = [
        $user['username'],      // {{1}} Name
        $monthNameFull,         // {{2}} Month Name Year
        $stats['present'],      // {{3}} Present Count
        $stats['absent'],       // {{4}} Absent Count
        $stats['working']       // {{5}} Working Count
    ];

    $headerParams = [
        [
            'type' => 'document',
            'document' => [
                'link' => $pdfUrl,
                'filename' => $fileName
            ]
        ]
    ];

    echo "Sending WhatsApp Message to $overridePhoneNumber...\n";
    // Using the override number here
    $res = $waService->sendTemplateMessage($overridePhoneNumber, 'monthly_work_report', 'en_US', $params, $headerParams);

    if ($res['success']) {
        echo "SUCCESS: Message sent successfully!\n";
        echo "Response: " . print_r($res['response'], true) . "\n";
    } else {
        echo "FAILED: Could not send message.\n";
        echo "Error: " . print_r($res['response'], true) . "\n";
    }

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
