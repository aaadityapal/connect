<?php
/**
 * Late Punch-In Alert Notifications
 * 
 * This script sends individual notifications to admins for employees who punched in
 * AFTER the 10:45 AM summary was sent.
 * 
 * Schedule:
 * - Runs at 2:30 PM IST daily
 * - Checks for punch-ins between 10:45 AM and 2:30 PM
 * - Sends individual alert for each late punch-in
 * 
 * Setup cron job (IST):
 * 30 14 * * * /usr/bin/php /path/to/cron_late_punchin_alerts.php
 * 
 * Production (UTC - subtract 5:30 from IST):
 * 0 9 * * * /usr/bin/php /path/to/cron_late_punchin_alerts.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/WhatsAppService.php';

// Log file
$logFile = __DIR__ . '/cron_alerts.log';
$timestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "\n[{$timestamp}] ===== Late Punch-In Alerts Started =====\n", FILE_APPEND);

try {
    $pdo = getDBConnection();
    $waService = new WhatsAppService();

    // Get today's date
    $today = date('Y-m-d');

    // Define the time window: 10:45 AM to 2:30 PM
    $summaryTime = $today . ' 10:45:00';
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

    // Get all punch-ins that occurred AFTER 10:45 AM today
    $query = "SELECT 
                u.id,
                u.username,
                a.punch_in,
                a.date
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE a.date = ?
            AND a.punch_in IS NOT NULL
            AND a.punch_in > ?
            ORDER BY a.punch_in ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$today, $summaryTime]);
    $latePunchIns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($latePunchIns)) {
        $msg = "No late punch-ins found after 10:45 AM";
        file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
        echo $msg . "\n";
        exit;
    }

    $totalSent = 0;

    // Send individual alert for each late punch-in to all admins
    foreach ($latePunchIns as $record) {
        $employeeName = $record['username'];
        $punchInTime = date('h:i A', strtotime($record['punch_in']));
        $punchInDate = date('l, F j, Y', strtotime($record['date']));

        // Send to each admin
        foreach ($admins as $admin) {
            $params = [
                $employeeName,  // {{1}} Employee name
                $punchInTime,   // {{2}} Time
                $punchInDate    // {{3}} Date
            ];

            $result = $waService->sendTemplateMessage(
                $admin['phone'],
                'employee_punchin_alert',
                'en_US',
                $params
            );

            if ($result['success']) {
                $totalSent++;
                $msg = "Alert sent to {$admin['admin_name']} for {$employeeName} punch-in at {$punchInTime}";
                file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
            } else {
                $msg = "Failed to send alert to {$admin['admin_name']} for {$employeeName}";
                file_put_contents($logFile, "[{$timestamp}] ERROR: {$msg}\n", FILE_APPEND);
            }
        }
    }

    $msg = "Late punch-in alerts completed. Total alerts sent: {$totalSent} (for " . count($latePunchIns) . " employees to " . count($admins) . " admins)";
    file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
    echo $msg . "\n";

} catch (Exception $e) {
    $errorMsg = "Exception: " . $e->getMessage();
    file_put_contents($logFile, "[{$timestamp}] ERROR: {$errorMsg}\n", FILE_APPEND);
    echo $errorMsg . "\n";
}

$endTimestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "[{$endTimestamp}] ===== Late Punch-In Alerts Completed =====\n", FILE_APPEND);
