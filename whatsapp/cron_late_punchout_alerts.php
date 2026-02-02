<?php
/**
 * Late Punch-Out Alert Notifications
 * 
 * This script sends individual notifications to admins for employees who punched out
 * AFTER the 9:00 PM summary was sent.
 * 
 * Schedule:
 * - Runs at 11:00 PM IST daily
 * - Checks for punch-outs between 9:00 PM and 11:00 PM
 * - Sends individual alert for each late punch-out
 * 
 * Setup cron job (IST):
 * 0 23 * * * /usr/bin/php /path/to/cron_late_punchout_alerts.php
 * 
 * Production (UTC - subtract 5:30 from IST):
 * 30 17 * * * /usr/bin/php /path/to/cron_late_punchout_alerts.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/WhatsAppService.php';

// Log file
$logFile = __DIR__ . '/cron_alerts.log';
$timestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "\n[{$timestamp}] ===== Late Punch-Out Alerts Started =====\n", FILE_APPEND);

try {
    $pdo = getDBConnection();
    $waService = new WhatsAppService();

    // Get today's date
    $today = date('Y-m-d');

    // Define the time window: 9:00 PM to 11:00 PM
    $summaryTime = $today . ' 21:00:00';
    $currentTime = date('Y-m-d H:i:s');

    // Fetch all active admin phone numbers
    $adminStmt = $pdo->prepare("SELECT id, admin_name, phone FROM admin_notifications WHERE is_active = 1");
    $adminStmt->execute();
    $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($admins)) {
        $msg = "No active admins found";
        file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
        echo $msg . "\n";
        exit;
    }

    // Get all punch-outs that occurred AFTER 9:00 PM today
    $query = "SELECT 
                u.id,
                u.username,
                a.punch_out,
                a.date
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE a.date = ?
            AND a.punch_out IS NOT NULL
            AND a.punch_out > ?
            ORDER BY a.punch_out ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$today, $summaryTime]);
    $latePunchOuts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($latePunchOuts)) {
        $msg = "No late punch-outs found after 9:00 PM";
        file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
        echo $msg . "\n";
        exit;
    }

    $totalSent = 0;

    // Send individual alert for each late punch-out to all admins
    foreach ($latePunchOuts as $record) {
        $employeeName = $record['username'];
        $punchOutTime = date('h:i A', strtotime($record['punch_out']));
        $punchOutDate = date('l, F j, Y', strtotime($record['date']));

        // Send to each admin
        foreach ($admins as $admin) {
            $params = [
                $employeeName,   // {{1}} Employee name
                $punchOutTime,   // {{2}} Time
                $punchOutDate    // {{3}} Date
            ];

            $result = $waService->sendTemplateMessage(
                $admin['phone'],
                'employee_punchout_alert',
                'en_US',
                $params
            );

            if ($result['success']) {
                $totalSent++;
                $msg = "Alert sent to {$admin['admin_name']} for {$employeeName} punch-out at {$punchOutTime}";
                file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
            } else {
                $msg = "Failed to send alert to {$admin['admin_name']} for {$employeeName}";
                file_put_contents($logFile, "[{$timestamp}] ERROR: {$msg}\n", FILE_APPEND);
            }
        }
    }

    $msg = "Late punch-out alerts completed. Total alerts sent: {$totalSent} (for " . count($latePunchOuts) . " employees to " . count($admins) . " admins)";
    file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
    echo $msg . "\n";

} catch (Exception $e) {
    $errorMsg = "Exception: " . $e->getMessage();
    file_put_contents($logFile, "[{$timestamp}] ERROR: {$errorMsg}\n", FILE_APPEND);
    echo $errorMsg . "\n";
}

$endTimestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "[{$endTimestamp}] ===== Late Punch-Out Alerts Completed =====\n", FILE_APPEND);
