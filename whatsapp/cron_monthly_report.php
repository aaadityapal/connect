<?php
/**
 * Cron Job: Monthly Work Report
 * 
 * Sends a WhatsApp notification with a PDF attachment containing the monthly attendance report.
 * Recommended Schedule: Last day of the month at 11:00 PM or 1st of next month at 08:00 AM.
 * 
 * Logic assumes it runs for the CURRENT month (if run on last day)
 * or PREVIOUS month (if run on 1st day).
 * 
 * Let's assume standardized usage: Run on Last Day of Month.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/WhatsAppService.php';
require_once __DIR__ . '/generate_monthly_report.php';

// CLI Only (optional)
// if (php_sapi_name() !== 'cli') die('CLI only');

$logFile = __DIR__ . '/cron_monthly_stats.log';

function logCron($msg)
{
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $msg" . PHP_EOL, FILE_APPEND);
    echo "[$date] $msg\n";
}

logCron("Starting Monthly Report Cron...");

try {
    $pdo = getDBConnection();

    // Determine Month/Year
    // If run today, we report for THIS month.
    $month = date('m');
    $year = date('Y');

    // Check if Last Day of Month (Safety needed? Or trust Cron schedule?)
    // If you schedule it "0 23 28-31 * *" and check logic:
    $lastDay = date('t');
    $currentDay = date('d');

    // Allow forced run via argument --force
    $isForced = (isset($argv[1]) && $argv[1] == '--force');

    if ($currentDay != $lastDay && !$isForced) {
        logCron("Today ($currentDay) is not the last day of the month ($lastDay). Aborting (use --force to override).");
        exit;
    }

    // Fetch Active Users
    $stmt = $pdo->prepare("SELECT id, username, phone FROM users WHERE status = 'active'");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $waService = new WhatsAppService();

    foreach ($users as $user) {
        logCron("Processing User: {$user['username']} ({$user['id']})...");

        $userId = $user['id'];
        $phone = $user['phone'];

        if (empty($phone)) {
            logCron("  - Skipped: No phone number.");
            continue;
        }

        // 1. Generate PDF
        $result = generateMonthlyReportPDF($userId, $month, $year, $pdo);

        if (!$result['success']) {
            logCron("  - PDF Generation Failed: " . $result['error']);
            continue;
        }

        $pdfUrl = $result['url'];
        $filePath = $result['file_path'];
        $stats = $result['stats'];
        $fileName = basename($filePath);

        // 2. Prepare WhatsApp Message
        // Template: monthly_work_report
        // {{1}} Name
        // {{2}} Month Name Year
        // {{3}} Present Count
        // {{4}} Absent Count
        // {{5}} Working Count

        $monthNameFull = date('F Y', mktime(0, 0, 0, $month, 10, $year));

        $params = [
            $user['username'],
            $monthNameFull,
            $stats['present'],
            $stats['absent'],
            $stats['working']
        ];

        // Header Params (Document)
        $headerParams = [
            [
                'type' => 'document',
                'document' => [
                    'link' => $pdfUrl,
                    'filename' => $fileName // "Monthly_Report.pdf"
                ]
            ]
        ];

        // 3. Send
        logCron("  - Sending WhatsApp to $phone...");
        $res = $waService->sendTemplateMessage($phone, 'monthly_work_report', 'en_US', $params, $headerParams);

        if ($res['success']) {
            logCron("  - Success!");
        } else {
            logCron("  - Failed to send: " . print_r($res['response'], true));
        }
    }

    logCron("Monthly Report Job Completed.");

} catch (Exception $e) {
    logCron("CRITICAL ERROR: " . $e->getMessage());
}
