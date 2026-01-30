<?php
/**
 * Missing Punch Alerts Cron Job
 * 
 * Checks for missing punch-in (late/absent) and missing punch-out events
 * and sends WhatsApp notifications to the respective users.
 * 
 * Usage:
 * Run via cron every 15-30 minutes:
 * *\/15 * * * * /usr/bin/php /path/to/connect/whatsapp/cron_missing_punch_alerts.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/WhatsAppService.php';

// Constants
define('PUNCH_IN_GRACE_MINUTES', 90); // 1 hour 30 minutes
define('PUNCH_OUT_GRACE_MINUTES', 90); // 1 hour 30 minutes after shift ends

// Log functionality
$logFile = __DIR__ . '/cron_alerts.log';
function logMessage($msg)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
    echo "[{$timestamp}] {$msg}\n";
}

try {
    logMessage("Starting Missing Punch Alerts check...");
    $pdo = getDBConnection();

    // 1. Ensure Log Table Exists
    // This table tracks sent alerts to prevent duplicate messages for the same day
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_alert_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        date DATE NOT NULL,
        alert_type VARCHAR(50) NOT NULL, -- 'missing_punch_in', 'missing_punch_out'
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_alert (user_id, date, alert_type)
    )");

    $currentDate = date('Y-m-d');
    $currentDayName = date('l'); // e.g., "Monday"
    $currentTimeTimestamp = time();

    // 2. Check for Office Holidays
    // If today is a holiday, we generally skip punch-in alerts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM office_holidays WHERE holiday_date = ?");
    $stmt->execute([$currentDate]);
    $isHoliday = $stmt->fetchColumn() > 0;

    if ($isHoliday) {
        logMessage("Today is a holiday. Skipping missing punch-in alerts.");
        // Note: We might still want to process punch-out alerts if someone DID come in, 
        // but typically on holidays we skip. I'll skip purely for missing punch-in logic.
        // For simplicity, let's assume no alerts on holidays unless logic requires modification.
    }

    // 3. Fetch Users with Active Shifts
    // We get users who are active and have a shift assigned for today
    $query = "SELECT 
                u.id AS user_id, 
                u.username, 
                u.phone, 
                s.start_time, 
                s.end_time, 
                us.weekly_offs
              FROM users u
              JOIN user_shifts us ON u.id = us.user_id
              JOIN shifts s ON us.shift_id = s.id
              WHERE u.status = 'active'
              AND us.effective_from <= ? 
              AND (us.effective_to IS NULL OR us.effective_to >= ?)";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$currentDate, $currentDate]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $waService = new WhatsAppService();

    foreach ($users as $user) {
        $userId = $user['user_id'];
        $userName = $user['username'];
        $userPhone = $user['phone'];
        $shiftStartStr = $user['start_time'];
        $shiftEndStr = $user['end_time'];
        $weeklyOffsStr = $user['weekly_offs'];

        if (empty($userPhone)) {
            continue;
        }

        // Parse Weekly Offs
        $weeklyOffs = array_map('trim', explode(',', $weeklyOffsStr));
        $isWeeklyOff = in_array($currentDayName, $weeklyOffs);

        // --- Logic: Missing Punch In ---
        // Only run if not holiday and not weekly off
        if (!$isHoliday && !$isWeeklyOff) {
            $shiftStartTimestamp = strtotime($currentDate . ' ' . $shiftStartStr);
            $alertTime = $shiftStartTimestamp + (PUNCH_IN_GRACE_MINUTES * 60);

            // Check if current time is past the alert threshold
            if ($currentTimeTimestamp >= $alertTime) {
                // Check if user has punched in
                $attStmt = $pdo->prepare("SELECT punch_in FROM attendance WHERE user_id = ? AND date = ?");
                $attStmt->execute([$userId, $currentDate]);
                $attendance = $attStmt->fetch(PDO::FETCH_ASSOC);

                if (!$attendance || empty($attendance['punch_in'])) {
                    // No punch in found. Check if we already sent an alert.
                    if (!checkAlertSent($pdo, $userId, $currentDate, 'missing_punch_in')) {
                        // SEND ALERT
                        logMessage("Sending Missing Punch In Alert to {$userName} ({$userPhone})");

                        $templateName = 'missing_punch_in_alert';
                        $params = [
                            $userName,                      // {{1}} Name
                            date('d-m-Y', strtotime($currentDate)), // {{2}} Date
                            date('h:i A', $shiftStartTimestamp)     // {{3}} Expected Time
                        ];

                        $result = $waService->sendTemplateMessage($userPhone, $templateName, 'en_US', $params);

                        if ($result['success']) {
                            recordAlertSent($pdo, $userId, $currentDate, 'missing_punch_in');
                        } else {
                            logMessage("Failed to send Punch In Alert to {$userName}: " . ($result['error'] ?? 'Unknown error'));
                        }
                    }
                }
            }
        }

        // --- Logic: Missing Punch Out ---
        // Run regardless of holiday/off status if they actually punched in (rare but possible)
        // Check if user has punched IN but NOT punched out
        $attStmt = $pdo->prepare("SELECT punch_in, punch_out FROM attendance WHERE user_id = ? AND date = ?");
        $attStmt->execute([$userId, $currentDate]);
        $attendance = $attStmt->fetch(PDO::FETCH_ASSOC);

        if ($attendance && !empty($attendance['punch_in']) && empty($attendance['punch_out'])) {
            // Calculate shift end timestamp
            $shiftStartTimestamp = strtotime($currentDate . ' ' . $shiftStartStr);
            $shiftEndTimestamp = strtotime($currentDate . ' ' . $shiftEndStr);

            // Handle overnight shifts (end time < start time)
            if ($shiftEndTimestamp < $shiftStartTimestamp) {
                $shiftEndTimestamp += 86400; // Add 24 hours
            }

            // Fixed Alert Time: 11:00 PM
            $alertOutTime = strtotime($currentDate . ' 23:00:00');

            // Handle overnight shifts or late shifts ending AFTER 11:00 PM
            // If the shift ends after 11:00 PM, they are still working, so DO NOT alert.
            if ($shiftEndTimestamp > $alertOutTime) {
                continue;
            }

            if ($currentTimeTimestamp >= $alertOutTime) {
                // Check if we already sent an alert
                if (!checkAlertSent($pdo, $userId, $currentDate, 'missing_punch_out')) {
                    // SEND ALERT
                    logMessage("Sending Missing Punch Out Alert to {$userName} ({$userPhone})");

                    $templateName = 'missing_punch_out_alert';
                    // Template vars: Name, Date, Last Punch In Time
                    $punchInTimeDisplay = date('h:i A', strtotime($attendance['punch_in']));

                    $params = [
                        $userName,                      // {{1}} Name
                        date('d-m-Y', strtotime($currentDate)), // {{2}} Date
                        $punchInTimeDisplay              // {{3}} Last Punch In Time
                    ];

                    $result = $waService->sendTemplateMessage($userPhone, $templateName, 'en_US', $params);

                    if ($result['success']) {
                        recordAlertSent($pdo, $userId, $currentDate, 'missing_punch_out');
                    } else {
                        logMessage("Failed to send Punch Out Alert to {$userName}: " . ($result['error'] ?? 'Unknown error'));
                    }
                }
            }
        }
    }

    logMessage("Missing Punch Alerts check completed.");

} catch (Exception $e) {
    logMessage("CRITICAL ERROR: " . $e->getMessage());
}

/**
 * Check if a specific alert has already been sent
 */
function checkAlertSent($pdo, $userId, $date, $type)
{
    $stmt = $pdo->prepare("SELECT id FROM daily_alert_logs WHERE user_id = ? AND date = ? AND alert_type = ?");
    $stmt->execute([$userId, $date, $type]);
    return $stmt->fetch() !== false;
}

/**
 * Record that an alert has been sent
 */
function recordAlertSent($pdo, $userId, $date, $type)
{
    $stmt = $pdo->prepare("INSERT INTO daily_alert_logs (user_id, date, alert_type) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $date, $type]);
}
