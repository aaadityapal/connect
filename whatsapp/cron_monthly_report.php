<?php
/**
 * Cron Job: Monthly Work Report
 * 
 * Sends a WhatsApp notification with a PDF attachment containing the monthly attendance report.
 * 
 * Schedule Logic:
 * 1. 1st of the Month: Sends report for the PREVIOUS month.
 * 2. 4th Saturday of the Month: Sends report for the CURRENT month.
 * 
 * Recommended Cron Schedule: Daily at 09:00 AM
 * 0 9 * * * php /path/to/cron_monthly_report.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/WhatsAppService.php';
require_once __DIR__ . '/generate_monthly_report_v2.php';

// Set Timezone to India to ensure "Today" matches user's local day
date_default_timezone_set('Asia/Kolkata');

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

logCron("Starting Monthly Report Cron Check...");

try {
    $pdo = getDBConnection();

    $currentDay = date('d');
    $currentMonth = date('m');
    $currentYear = date('Y');

    // Allow forced run via argument --force
    // Usage: php cron_monthly_report.php --force [month] [year]
    // Example: php cron_monthly_report.php --force 01 2026
    $isForced = (isset($argv[1]) && $argv[1] == '--force');

    $targetMonth = null;
    $targetYear = null;
    $triggerReason = "";

    if ($isForced) {
        $triggerReason = "Manual Force";
        // Default to previous month if not specified in ARGV
        if (isset($argv[2]) && isset($argv[3])) {
            $targetMonth = $argv[2];
            $targetYear = $argv[3];
        } else {
            // Default force logic: Previous Month
            $targetMonth = date('m', strtotime('last month'));
            $targetYear = date('Y', strtotime('last month'));
        }
    } else {
        // 1. Check if today is 1st of the month
        if ($currentDay == '01') {
            $triggerReason = "1st of Month (Previous Month Report)";
            $targetMonth = date('m', strtotime('last month'));
            $targetYear = date('Y', strtotime('last month'));
        }
        // 2. Check if today is 4th Saturday
        else {
            // Find the date of the 4th Saturday of this month
            $fourthSaturdayDate = date('d', strtotime('fourth saturday of ' . date('F Y')));

            if ($currentDay == $fourthSaturdayDate) {
                $triggerReason = "4th Saturday (Current Month Report)";
                $targetMonth = $currentMonth;
                $targetYear = $currentYear;
            }
        }
    }

    if (!$targetMonth || !$targetYear) {
        logCron("No trigger condition met today ($currentDay-$currentMonth-$currentYear).");
        logCron("  - Rules: 1st of Month OR 4th Saturday.");
        logCron("Exiting.");
        exit;
    }

    logCron("Triggered by: $triggerReason");
    logCron("Generating Report for: $targetMonth / $targetYear");

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
        $result = generateMonthlyReportPDF($userId, $targetMonth, $targetYear, $pdo);

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

        $monthNameFull = date('F Y', mktime(0, 0, 0, $targetMonth, 10, $targetYear));

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
                    'filename' => $fileName
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

    logCron("Job Completed Successfully.");

} catch (Exception $e) {
    logCron("CRITICAL ERROR: " . $e->getMessage());
}
