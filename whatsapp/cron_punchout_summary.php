<?php
/**
 * Scheduled Punch-Out Summary Notifications (With PDF Attachments)
 * 
 * This script sends admin notifications for punch-outs at specific times:
 * 
 * Punch-Out Summaries:
 * - 06:30 PM (First summary with PDF)
 * - 09:00 PM (Final summary with PDF)
 * 
 * Setup cron jobs (IST):
 * 30 18 * * * /usr/bin/php /path/to/cron_punchout_summary.php both
 * 0 21 * * * /usr/bin/php /path/to/cron_punchout_summary.php both
 * 
 * Production (UTC - subtract 5:30 from IST):
 * 0 13 * * * /usr/bin/php /path/to/cron_punchout_summary.php both
 * 30 15 * * * /usr/bin/php /path/to/cron_punchout_summary.php both
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_punch_notification.php';

// Get team type from command line argument (optional, defaults to both)
$teamType = isset($argv[1]) ? $argv[1] : 'both'; // field, studio, or both

// Log file
$logFile = __DIR__ . '/cron.log';
$timestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "\n[{$timestamp}] ===== Scheduled Punch-Out Summary Started (Team: {$teamType}) =====\n", FILE_APPEND);

try {
    $pdo = getDBConnection();
    $date = date('Y-m-d');

    // Send based on team type
    if ($teamType === 'field' || $teamType === 'both') {
        $lockFile = __DIR__ . '/last_run_punchout_field.lock';
        $lastRun = file_exists($lockFile) ? file_get_contents($lockFile) : 0;

        if (time() - $lastRun > 300) { // 5 minutes debounce
            file_put_contents($lockFile, time());
            $result = sendScheduledPunchOutSummary($pdo, $date, 'Field');
            $msg = $result ? "Field team punch-out summary sent successfully" : "Failed to send field team punch-out summary";
            file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
            echo $msg . "\n";
        } else {
            $msg = "Skipping Field team summary (Already ran within last 5 minutes)";
            file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
            echo $msg . "\n";
        }
    }

    if ($teamType === 'studio' || $teamType === 'both') {
        $lockFile = __DIR__ . '/last_run_punchout_studio.lock';
        $lastRun = file_exists($lockFile) ? file_get_contents($lockFile) : 0;

        if (time() - $lastRun > 300) { // 5 minutes debounce
            file_put_contents($lockFile, time());
            $result = sendScheduledPunchOutSummary($pdo, $date, 'Studio');
            $msg = $result ? "Studio team punch-out summary sent successfully" : "Failed to send studio team punch-out summary";
            file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
            echo $msg . "\n";
        } else {
            $msg = "Skipping Studio team summary (Already ran within last 5 minutes)";
            file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
            echo $msg . "\n";
        }
    }

} catch (Exception $e) {
    $errorMsg = "Exception: " . $e->getMessage();
    file_put_contents($logFile, "[{$timestamp}] ERROR: {$errorMsg}\n", FILE_APPEND);
    echo $errorMsg . "\n";
}

$endTimestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "[{$endTimestamp}] ===== Scheduled Punch-Out Summary Completed =====\n", FILE_APPEND);
