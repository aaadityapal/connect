<?php
/**
 * Check Missing Punches (Diagnostic & Manual Trigger)
 * 
 * Lists all users who are currently missing a punch-in or punch-out
 * and meet the alert criteria (late).
 * 
 * Usage:
 * 1. List only (Dry Run):
 *    php check_missing_punches.php
 * 
 * 2. Send Alert to Specific User:
 *    php check_missing_punches.php --send <user_id> <type>
 *    (type: 'in' or 'out')
 *    Example: php check_missing_punches.php --send 57 in
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/WhatsAppService.php';

// Constants (Match cron)
define('PUNCH_IN_GRACE_MINUTES', 90);
define('PUNCH_OUT_GRACE_MINUTES', 90);

// CLI Arguments
$sendMode = false;
$targetUserId = null;
$targetType = null;

if (isset($argv[1]) && $argv[1] === '--send') {
    if (isset($argv[2], $argv[3])) {
        $sendMode = true;
        $targetUserId = $argv[2];
        $targetType = $argv[3]; // 'in' or 'out'
    } else {
        die("Usage: php check_missing_punches.php --send <user_id> <type>\nType must be 'in' or 'out'\n");
    }
}

echo "===== CHECKING MISSING PUNCHES (Real Data) =====\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
if ($sendMode) {
    echo "MODE: Sending alert to User ID: $targetUserId (Type: $targetType)\n";
} else {
    echo "MODE: List Only (Dry Run)\n";
}
echo "---------------------------------------------------------------------------------\n";
echo sprintf("%-5s | %-20s | %-12s | %-15s | %-25s | %-10s | %-10s\n", "ID", "Name", "Phone", "Week Off", "Shift / Punch", "Status", "Alert Logged?");
echo "------------------------------------------------------------------------------------------------------------------\n";

try {
    $pdo = getDBConnection();

    // 1. Get current state
    $currentDate = date('Y-m-d');
    $currentDayName = date('l');
    $currentTimeTimestamp = time();

    // 2. Holidays
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM office_holidays WHERE holiday_date = ?");
    $stmt->execute([$currentDate]);
    $isHoliday = $stmt->fetchColumn() > 0;

    if ($isHoliday) {
        echo "NOTE: Today is marked as a Holiday. Punch In alerts are typically skipped.\n";
    }

    // 3. Fetch Users with Active Shifts
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

    $foundCount = 0;
    $waService = new WhatsAppService();

    foreach ($users as $user) {
        $userId = $user['user_id'];
        $userName = substr($user['username'], 0, 20);
        $phone = $user['phone'];
        $shiftStartStr = $user['start_time'];
        $shiftEndStr = $user['end_time'];
        $weeklyOff = empty($user['weekly_offs']) ? 'None' : substr($user['weekly_offs'], 0, 15);

        $weeklyOffs = array_map('trim', explode(',', $user['weekly_offs']));
        $isWeeklyOff = in_array($currentDayName, $weeklyOffs);

        // --- Check Missed Punch In ---
        if (!$isHoliday && !$isWeeklyOff) {
            // Get Attendance
            $attStmt = $pdo->prepare("SELECT punch_in FROM attendance WHERE user_id = ? AND date = ?");
            $attStmt->execute([$userId, $currentDate]);
            $attendance = $attStmt->fetch(PDO::FETCH_ASSOC);

            $shiftStartTimestamp = strtotime($currentDate . ' ' . $shiftStartStr);
            $alertTime = $shiftStartTimestamp + (PUNCH_IN_GRACE_MINUTES * 60);

            // Logic: Is Late AND No Punch In?
            if ($currentTimeTimestamp >= $alertTime && (!$attendance || empty($attendance['punch_in']))) {

                $foundCount++;
                $isLogged = checkAlertSent($pdo, $userId, $currentDate, 'missing_punch_in');
                $minutesLate = floor(($currentTimeTimestamp - $shiftStartTimestamp) / 60);

                echo sprintf(
                    "%-5s | %-20s | %-12s | %-15s | Shift: %-18s | %-10s | %-10s\n",
                    $userId,
                    $userName,
                    $phone,
                    $weeklyOff,
                    date('H:i', $shiftStartTimestamp),
                    "LATE ($minutesLate m)",
                    ($isLogged ? 'YES' : 'NO')
                );

                // Handle Send
                if ($sendMode && $userId == $targetUserId && $targetType == 'in') {
                    sendPunchInAlert($waService, $user, $currentDate, $shiftStartTimestamp);
                    recordAlertSent($pdo, $userId, $currentDate, 'missing_punch_in');
                    echo "      >>> SENT ALERT to $userName\n";
                }
            }
        }

        // --- Check Missed Punch Out ---
        // Need to fetch punch_in again to be sure (already fetched above potentially)
        $attStmt = $pdo->prepare("SELECT punch_in, punch_out FROM attendance WHERE user_id = ? AND date = ?");
        $attStmt->execute([$userId, $currentDate]);
        $attendance = $attStmt->fetch(PDO::FETCH_ASSOC);

        if ($attendance && !empty($attendance['punch_in']) && empty($attendance['punch_out'])) {
            $shiftStartTimestamp = strtotime($currentDate . ' ' . $shiftStartStr);
            $shiftEndTimestamp = strtotime($currentDate . ' ' . $shiftEndStr);
            if ($shiftEndTimestamp < $shiftStartTimestamp)
                $shiftEndTimestamp += 86400;

            $alertOutTime = strtotime($currentDate . ' 23:00:00');

            // Skip if shift ends after 11 PM (Night Shift)
            if ($shiftEndTimestamp > $alertOutTime) {
                continue;
            }

            if ($currentTimeTimestamp >= $alertOutTime) {
                $foundCount++;
                $isLogged = checkAlertSent($pdo, $userId, $currentDate, 'missing_punch_out');
                $minutesDiff = floor(($currentTimeTimestamp - $shiftEndTimestamp) / 60);

                echo sprintf(
                    "%-5s | %-20s | %-12s | %-15s | End: %-20s | %-10s | %-10s\n",
                    $userId,
                    $userName,
                    $phone,
                    $weeklyOff,
                    date('H:i', $shiftEndTimestamp),
                    "NOSIGN($minutesDiff m)",
                    ($isLogged ? 'YES' : 'NO')
                );

                if ($sendMode && $userId == $targetUserId && $targetType == 'out') {
                    sendPunchOutAlert($waService, $user, $currentDate, $attendance['punch_in']);
                    recordAlertSent($pdo, $userId, $currentDate, 'missing_punch_out');
                    echo "      >>> SENT ALERT to $userName\n";
                }
            }
        }
    }

    if ($foundCount === 0) {
        echo "\nNo users found matching the missing punch criteria right now.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// --- Helper Functions ---

function checkAlertSent($pdo, $userId, $date, $type)
{
    $stmt = $pdo->prepare("SELECT id FROM daily_alert_logs WHERE user_id = ? AND date = ? AND alert_type = ?");
    $stmt->execute([$userId, $date, $type]);
    return $stmt->fetch() !== false;
}

function recordAlertSent($pdo, $userId, $date, $type)
{
    // Only insert if not exists
    if (!checkAlertSent($pdo, $userId, $date, $type)) {
        $stmt = $pdo->prepare("INSERT INTO daily_alert_logs (user_id, date, alert_type) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $date, $type]);
    }
}

function sendPunchInAlert($waService, $user, $dateStr, $shiftStartTs)
{
    $templateName = 'missing_punch_in_alert';
    $params = [
        $user['username'],
        date('d-m-Y', strtotime($dateStr)),
        date('h:i A', $shiftStartTs)
    ];
    $waService->sendTemplateMessage($user['phone'], $templateName, 'en_US', $params);
}

function sendPunchOutAlert($waService, $user, $dateStr, $punchInTime)
{
    $templateName = 'missing_punch_out_alert';
    $params = [
        $user['username'],
        date('d-m-Y', strtotime($dateStr)),
        date('h:i A', strtotime($punchInTime))
    ];
    $waService->sendTemplateMessage($user['phone'], $templateName, 'en_US', $params);
}
