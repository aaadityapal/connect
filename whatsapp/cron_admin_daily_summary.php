<?php
/**
 * Daily Admin Summary Cron Job
 * 
 * This script sends daily punch-in summary notifications to all admins
 * It should be scheduled to run once per day (recommended: end of day, e.g., 6:00 PM)
 * 
 * Cron Job Example (runs daily at 6:00 PM):
 * 0 18 * * * /usr/bin/php /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron_admin_daily_summary.php >> /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron.log 2>&1
 * 
 * Or using wget/curl:
 * 0 18 * * * curl http://localhost/connect/whatsapp/cron_admin_daily_summary.php >> /Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/cron.log 2>&1
 */

// Prevent direct browser access (optional security measure)
// Comment out these lines if you want to test via browser
// if (php_sapi_name() !== 'cli') {
//     die('This script can only be run from command line');
// }

// Include required files
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_punch_notification.php';

// Log script start
$logFile = __DIR__ . '/cron.log';
$timestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "\n[{$timestamp}] ===== Admin Daily Summary Cron Job Started =====\n", FILE_APPEND);

try {
    // Get database connection
    $pdo = getDBConnection();

    // You can optionally specify a date, or leave null for today
    $date = null; // Use today's date
    // $date = '2026-01-28'; // Or specify a specific date for testing

    // Send admin daily summary
    $result = sendAdminDailySummary($pdo, $date);

    if ($result) {
        $message = "Admin daily summary sent successfully for date: " . ($date ?? date('Y-m-d'));
        file_put_contents($logFile, "[{$timestamp}] SUCCESS: {$message}\n", FILE_APPEND);
        echo $message . "\n";
    } else {
        $message = "Failed to send admin daily summary";
        file_put_contents($logFile, "[{$timestamp}] ERROR: {$message}\n", FILE_APPEND);
        echo $message . "\n";
    }

} catch (Exception $e) {
    $errorMsg = "Exception in cron job: " . $e->getMessage();
    file_put_contents($logFile, "[{$timestamp}] EXCEPTION: {$errorMsg}\n", FILE_APPEND);
    echo $errorMsg . "\n";
}

$endTimestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "[{$endTimestamp}] ===== Admin Daily Summary Cron Job Completed =====\n", FILE_APPEND);
