<?php
/**
 * Scheduled Admin Summary Notifications
 * 
 * This script sends admin notifications at specific times:
 * 
 * Punch-In Reminders:
 * - 09:00 AM (Field team - First reminder)
 * - 09:30 AM (Both teams - Second reminder)
 * - 12:00 PM (Both teams - Final reminder)
 * 
 * Setup cron jobs (IST):
 * 0 9 * * * /usr/bin/php /path/to/cron_scheduled_admin_summary.php field
 * 30 9 * * * /usr/bin/php /path/to/cron_scheduled_admin_summary.php both
 * 0 12 * * * /usr/bin/php /path/to/cron_scheduled_admin_summary.php both
 * 
 * Production (UTC - subtract 5:30 from IST):
 * 30 3 * * * /usr/bin/php /path/to/cron_scheduled_admin_summary.php field
 * 0 4 * * * /usr/bin/php /path/to/cron_scheduled_admin_summary.php both
 * 30 6 * * * /usr/bin/php /path/to/cron_scheduled_admin_summary.php both
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_punch_notification.php';

// Get team type from command line argument
$teamType = isset($argv[1]) ? $argv[1] : 'both'; // field, studio, or both

// Log file
$logFile = __DIR__ . '/cron.log';
$timestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "\n[{$timestamp}] ===== Scheduled Admin Summary Started (Team: {$teamType}) =====\n", FILE_APPEND);

try {
    $pdo = getDBConnection();
    $date = date('Y-m-d');

    // Send based on team type
    if ($teamType === 'field' || $teamType === 'both') {
        $result = sendScheduledAdminSummary($pdo, $date, 'Field');
        $msg = $result ? "Field team summary sent successfully" : "Failed to send field team summary";
        file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
        echo $msg . "\n";
    }

    if ($teamType === 'studio' || $teamType === 'both') {
        $result = sendScheduledAdminSummary($pdo, $date, 'Studio');
        $msg = $result ? "Studio team summary sent successfully" : "Failed to send studio team summary";
        file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
        echo $msg . "\n";
    }

} catch (Exception $e) {
    $errorMsg = "Exception: " . $e->getMessage();
    file_put_contents($logFile, "[{$timestamp}] ERROR: {$errorMsg}\n", FILE_APPEND);
    echo $errorMsg . "\n";
}

$endTimestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "[{$endTimestamp}] ===== Scheduled Admin Summary Completed =====\n", FILE_APPEND);
