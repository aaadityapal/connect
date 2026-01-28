<?php
require_once __DIR__ . '/WhatsAppService.php';

/**
 * Send Punch In Notification via WhatsApp
 * 
 * @param int $userId The ID of the user
 * @param PDO $pdo proper PDO database connection
 * @return bool
 */
function sendPunchNotification($userId, $pdo)
{
    try {
        // 1. Fetch User Details (Name and Phone)
        $stmt = $pdo->prepare("SELECT username, phone FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['phone'])) {
            error_log("WhatsApp Notification Error: User not found or no phone number (ID: $userId)");
            return false;
        }

        $phone = $user['phone'];
        $username = $user['username'];
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i:s');
        $currentDay = date('l'); // Monday, Tuesday...

        // 2. Calculate Month Statistics
        $currentMonth = date('m');
        $currentYear = date('Y');

        // Present Days (Count attendance records for this month)
        // We count records where punch_in is not null
        $statsStmt = $pdo->prepare("SELECT COUNT(*) as present_count FROM attendance 
                                   WHERE user_id = ? 
                                   AND MONTH(date) = ? 
                                   AND YEAR(date) = ? 
                                   AND punch_in IS NOT NULL");
        $statsStmt->execute([$userId, $currentMonth, $currentYear]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        $presentDays = $stats['present_count'] ?? 0;

        // Working Days Calculation (Advanced)
        $msgWorkingDays = 0;    // Total working days in the month (for display)
        $elapsedWorkingDays = 0; // Working days passed so far (for absent calc)

        $daysInMonth = (int) date('t'); // Total days in current month (e.g., 31)
        $currentDayNum = (int) date('d'); // Current day number (e.g., 27)

        // 1. Fetch Holidays for this month
        $holidays = [];
        $holidayStmt = $pdo->prepare("SELECT holiday_date FROM office_holidays 
                                     WHERE MONTH(holiday_date) = ? AND YEAR(holiday_date) = ?");
        $holidayStmt->execute([$currentMonth, $currentYear]);
        $holidaysRaw = $holidayStmt->fetchAll(PDO::FETCH_COLUMN);
        // Normalize holidays to just the day number or full Y-m-d comparison
        $holidays = $holidaysRaw ? $holidaysRaw : [];

        // 2. Fetch User's Shift & Weekly Offs
        // We look for an active shift for the current period
        $shiftStmt = $pdo->prepare("SELECT weekly_offs FROM user_shifts 
                                   WHERE user_id = ? 
                                   AND effective_from <= ? 
                                   AND (effective_to IS NULL OR effective_to >= ?) 
                                   ORDER BY effective_from DESC LIMIT 1");
        $shiftStmt->execute([$userId, $currentDate, $currentDate]);
        $shiftData = $shiftStmt->fetch(PDO::FETCH_ASSOC);

        // Parse Weekly Offs (e.g., "Saturday, Sunday" or "Monday")
        $weeklyOffsArr = [];
        if ($shiftData && !empty($shiftData['weekly_offs'])) {
            // Convert "Monday, Tuesday" -> ['Monday', 'Tuesday']
            $weeklyOffsRaw = explode(',', $shiftData['weekly_offs']);
            $weeklyOffsArr = array_map('trim', $weeklyOffsRaw);
        }

        // 3. Iterate Full Month Days
        for ($i = 1; $i <= $daysInMonth; $i++) {
            // Create timestamp for this specific day of the month
            $loopDateStr = sprintf("%04d-%02d-%02d", $currentYear, $currentMonth, $i);
            $loopTimestamp = strtotime($loopDateStr);
            $loopDayName = date('l', $loopTimestamp); // e.g. "Monday"

            // Check if it's a Holiday
            if (in_array($loopDateStr, $holidays)) {
                continue; // It's a holiday, don't count as working day
            }

            // Check if it's a Weekly Off
            if (in_array($loopDayName, $weeklyOffsArr)) {
                continue; // It's a weekly off, don't count as working day
            }

            // If we passed checks, it's a working day
            $msgWorkingDays++; // Increment total for the month

            // If this day has already passed or is today, increment elapsed counter
            if ($i <= $currentDayNum) {
                $elapsedWorkingDays++;
            }
        }

        // Absent Days Calculation (Based on ELAPSED time, not future)
        // We only mark them absent for days that have already happened
        $absentDays = max(0, $elapsedWorkingDays - $presentDays);

        // 3. Prepare Template Parameters
        // Template: employee_punchin_attendance_update
        // Variables:
        // {{1}} Name
        // {{2}} Time
        // {{3}} Date
        // {{4}} Day
        // {{5}} Present Days
        // {{6}} Absent Days
        // {{7}} Working Days

        $templateName = 'employee_punchin_attendance_update';
        $params = [
            $username,      // {{1}}
            $currentTime,   // {{2}}
            $currentDate,   // {{3}}
            $currentDay,    // {{4}}
            $presentDays,   // {{5}}
            $absentDays,    // {{6}}
            $msgWorkingDays // {{7}} - Showing Total Working Days in Month
        ];

        // 4. Send Template Message
        $waService = new WhatsAppService();
        $waService->sendTemplateMessage($phone, $templateName, 'en_US', $params);

        return true;

    } catch (Exception $e) {
        error_log("WhatsApp Notification Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send Punch Out Notification via WhatsApp
 * 
 * @param int $userId The ID of the user
 * @param PDO $pdo proper PDO database connection
 * @return bool
 */
function sendPunchOutNotification($userId, $pdo)
{
    try {
        // 1. Fetch User Details
        $stmt = $pdo->prepare("SELECT username, phone FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['phone'])) {
            return false;
        }

        $phone = $user['phone'];
        $username = $user['username'];
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i:s');
        $currentDay = date('l');

        // 2. Fetch Attendance Details (Punch In, Punch Out, Work Report)
        $attStmt = $pdo->prepare("SELECT punch_in, punch_out, work_report FROM attendance 
                                 WHERE user_id = ? AND date = ?");
        $attStmt->execute([$userId, $currentDate]);
        $attendance = $attStmt->fetch(PDO::FETCH_ASSOC);

        if (!$attendance || empty($attendance['punch_in']) || empty($attendance['punch_out'])) {
            error_log("Punch Out Notification Error: Missing attendance data for user $userId");
            return false;
        }

        $punchInTime = strtotime($attendance['punch_in']);
        $punchOutTime = strtotime($attendance['punch_out']);
        $workReport = $attendance['work_report'] ?? 'No report submitted';

        // 3. Calculate Total Working Hours
        $secondsWorked = $punchOutTime - $punchInTime;
        $hoursWorked = floor($secondsWorked / 3600);
        $minutesWorked = floor(($secondsWorked % 3600) / 60);
        $totalWorkingTimeStr = sprintf("%02d:%02d", $hoursWorked, $minutesWorked);

        // 4. Calculate Overtime
        // Fetch Shift Duration
        $overtimeStr = "00:00";

        // Get User's Shift
        $shiftStmt = $pdo->prepare("SELECT s.start_time, s.end_time 
                                   FROM user_shifts us 
                                   JOIN shifts s ON us.shift_id = s.id 
                                   WHERE us.user_id = ? 
                                   AND us.effective_from <= ? 
                                   AND (us.effective_to IS NULL OR us.effective_to >= ?) 
                                   ORDER BY us.effective_from DESC LIMIT 1");
        $shiftStmt->execute([$userId, $currentDate, $currentDate]);
        $shift = $shiftStmt->fetch(PDO::FETCH_ASSOC);

        if ($shift) {
            $shiftStart = strtotime($shift['start_time']);
            $shiftEnd = strtotime($shift['end_time']);

            // Handle overnight shifts if end < start, add 24 hours
            if ($shiftEnd < $shiftStart) {
                $shiftEnd += 86400;
            }

            $shiftDurationSeconds = $shiftEnd - $shiftStart;

            // Overtime = Actual Worked - Shift Duration
            if ($secondsWorked > $shiftDurationSeconds) {
                $otSeconds = $secondsWorked - $shiftDurationSeconds;
                $otHours = floor($otSeconds / 3600);
                $otMinutes = floor(($otSeconds % 3600) / 60);
                $overtimeStr = sprintf("%02d:%02d", $otHours, $otMinutes);
            }
        }

        // 5. Prepare Template Parameters
        // Template: employee_punchout_recorded
        // {{1}} Name
        // {{2}} Time
        // {{3}} Date
        // {{4}} Day
        // {{5}} Total Working Hours
        // {{6}} Overtime Hours
        // {{7}} Work Report

        $templateName = 'employee_punchout_recorded';
        $params = [
            $username,              // {{1}}
            $currentTime,           // {{2}}
            $currentDate,           // {{3}}
            $currentDay,            // {{4}}
            $totalWorkingTimeStr,   // {{5}}
            $overtimeStr,           // {{6}}
            $workReport             // {{7}}
        ];

        // 6. Send Message
        $waService = new WhatsAppService();
        $waService->sendTemplateMessage($phone, $templateName, 'en_US', $params);

        return true;

    } catch (Exception $e) {
        error_log("WhatsApp Punch Out Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send Daily Admin Summary Notification
 * Sends punch-in summary to all admins, separated by Studio Team and Field Team
 * 
 * @param PDO $pdo Database connection
 * @param string $date Date for which to send summary (Y-m-d format), defaults to today
 * @return bool
 */
function sendAdminDailySummary($pdo, $date = null)
{
    try {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        // 1. Fetch all active admin phone numbers from admin_notifications table
        $adminStmt = $pdo->prepare("SELECT id, admin_name, phone FROM admin_notifications WHERE is_active = 1");
        $adminStmt->execute();
        $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($admins)) {
            error_log("No admins found with phone numbers for daily summary");
            return false;
        }

        // 2. Get Studio Team Statistics
        $studioStats = getPunchInStatsByTeam($pdo, $date, 'Studio');

        // 3. Get Field Team Statistics
        $fieldStats = getPunchInStatsByTeam($pdo, $date, 'Field');

        // 4. Send notifications to each admin
        $waService = new WhatsAppService();
        $successCount = 0;
        $currentTime = date('h:i A'); // Current time in 12-hour format

        foreach ($admins as $admin) {
            // Send Studio Team Summary
            if ($studioStats['total'] > 0) {
                $studioTemplate = 'admin_studioteam_punchin_late_summary';
                $studioParams = [
                    $currentTime,                   // {{1}} Time
                    $studioStats['ontime_list'],    // {{2}} On-time punch-ins
                    $studioStats['late_list']       // {{3}} Late punch-ins
                ];

                $result = $waService->sendTemplateMessage(
                    $admin['phone'],
                    $studioTemplate,
                    'en_US',
                    $studioParams
                );

                if ($result['success']) {
                    $successCount++;
                }
            }

            // Send Field Team Summary
            if ($fieldStats['total'] > 0) {
                $fieldTemplate = 'admin_fieldteam_punchin_late_summary';
                $fieldParams = [
                    $currentTime,                  // {{1}} Time
                    $fieldStats['ontime_list'],    // {{2}} On-time punch-ins
                    $fieldStats['late_list']       // {{3}} Late punch-ins
                ];

                $result = $waService->sendTemplateMessage(
                    $admin['phone'],
                    $fieldTemplate,
                    'en_US',
                    $fieldParams
                );

                if ($result['success']) {
                    $successCount++;
                }
            }
        }

        error_log("Admin daily summary sent successfully. Total messages: $successCount");
        return true;

    } catch (Exception $e) {
        error_log("Admin Daily Summary Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send Scheduled Admin Summary for Specific Team
 * Sends punch-in summary to all admins for a specific team only
 * 
 * @param PDO $pdo Database connection
 * @param string $date Date for which to send summary (Y-m-d format)
 * @param string $teamType 'Studio' or 'Field'
 * @return bool
 */
function sendScheduledAdminSummary($pdo, $date, $teamType)
{
    try {
        // 1. Fetch all active admin phone numbers
        $adminStmt = $pdo->prepare("SELECT id, admin_name, phone FROM admin_notifications WHERE is_active = 1");
        $adminStmt->execute();
        $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($admins)) {
            error_log("No admins found with phone numbers for scheduled summary");
            return false;
        }

        // 2. Get statistics for the specified team
        $stats = getPunchInStatsByTeam($pdo, $date, $teamType);

        if ($stats['total'] === 0) {
            error_log("No employees found for {$teamType} team on {$date}");
            return true; // Not an error, just no data
        }

        // 3. Send notifications to each admin
        $waService = new WhatsAppService();
        $successCount = 0;
        $currentTime = date('h:i A'); // Current time in 12-hour format

        // Determine template name based on team type
        $templateName = ($teamType === 'Studio')
            ? 'admin_studioteam_punchin_late_summary'
            : 'admin_fieldteam_punchin_late_summary';

        foreach ($admins as $admin) {
            $params = [
                $currentTime,           // {{1}} Time
                $stats['ontime_list'],  // {{2}} On-time punch-ins
                $stats['late_list']     // {{3}} Late punch-ins
            ];

            $result = $waService->sendTemplateMessage(
                $admin['phone'],
                $templateName,
                'en_US',
                $params
            );

            if ($result['success']) {
                $successCount++;
            }
        }

        error_log("Scheduled {$teamType} team summary sent successfully. Total messages: $successCount");
        return true;

    } catch (Exception $e) {
        error_log("Scheduled Admin Summary Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Get Punch-In Statistics by Team Type
 * 
 * @param PDO $pdo Database connection
 * @param string $date Date to check (Y-m-d format)
 * @param string $teamType 'Studio' or 'Field'
 * @return array Statistics with ontime_list, late_list, and total count
 */
function getPunchInStatsByTeam($pdo, $date, $teamType)
{
    try {
        // Determine role filter based on team type
        // Field Team: Site Supervisor, Site Coordinator, Purchase Manager
        // Studio Team: All other roles

        $roleCondition = '';
        if ($teamType === 'Studio') {
            // Studio team - exclude field roles
            $roleCondition = "AND u.role NOT IN ('Site Supervisor', 'site coordinator', 'purchase manager')";
        } else {
            // Field team - only field roles
            $roleCondition = "AND u.role IN ('Site Supervisor', 'site coordinator', 'purchase manager')";
        }

        // Get all employees who punched in today for this team type
        $query = "SELECT 
                    u.id,
                    u.username,
                    u.role,
                    a.punch_in,
                    s.start_time
                FROM attendance a
                JOIN users u ON a.user_id = u.id
                LEFT JOIN user_shifts us ON u.id = us.user_id 
                    AND us.effective_from <= ? 
                    AND (us.effective_to IS NULL OR us.effective_to >= ?)
                LEFT JOIN shifts s ON us.shift_id = s.id
                WHERE a.date = ?
                AND a.punch_in IS NOT NULL
                $roleCondition
                ORDER BY u.username";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$date, $date, $date]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $ontimeList = [];
        $lateList = [];

        foreach ($employees as $emp) {
            $punchInTime = strtotime($emp['punch_in']);
            $isLate = false;

            if ($emp['start_time']) {
                // Calculate expected punch-in time with 15-minute grace period
                $shiftStart = strtotime($emp['start_time']);
                $gracePeriodMinutes = 15; // Fixed 15-minute grace period
                // Allow up to the end of the 15th minute (e.g., 09:15:59 is still 09:15)
                $allowedTime = $shiftStart + ($gracePeriodMinutes * 60) + 59;

                if ($punchInTime > $allowedTime) {
                    $isLate = true;
                    $minutesLate = floor(($punchInTime - $allowedTime) / 60);
                    $timeStr = date('h:i A', $punchInTime);
                    $lateList[] = $emp['username'] . " (" . $timeStr . " - " . $minutesLate . " min late)";
                } else {
                    $timeStr = date('h:i A', $punchInTime);
                    $ontimeList[] = $emp['username'] . " (" . $timeStr . ")";
                }
            } else {
                // No shift assigned, consider on-time
                $timeStr = date('h:i A', $punchInTime);
                $ontimeList[] = $emp['username'] . " (" . $timeStr . ")";
            }
        }

        // Format lists for WhatsApp message
        // Use bullet points with spacing because WhatsApp API rejects newlines in this template parameter (#132018)
        $separator = "   •   ";
        $ontimeFormatted = empty($ontimeList) ? "None" : ("• " . implode($separator, $ontimeList));
        $lateFormatted = empty($lateList) ? "None" : ("• " . implode($separator, $lateList));

        return [
            'ontime_list' => $ontimeFormatted,
            'late_list' => $lateFormatted,
            'total' => count($employees)
        ];

    } catch (Exception $e) {
        error_log("Get Punch-In Stats Exception: " . $e->getMessage());
        return [
            'ontime_list' => 'Error fetching data',
            'late_list' => 'Error fetching data',
            'total' => 0
        ];
    }
}

/**
 * Send Scheduled Punch-Out Summary for Specific Team
 * Sends punch-out summary to all admins for a specific team
 * 
 * @param PDO $pdo Database connection
 * @param string $date Date for which to send summary (Y-m-d format)
 * @param string $teamType 'Studio' or 'Field'
 * @return bool
 */
function sendScheduledPunchOutSummary($pdo, $date, $teamType)
{
    try {
        // 1. Fetch all active admin phone numbers
        $adminStmt = $pdo->prepare("SELECT id, admin_name, phone FROM admin_notifications WHERE is_active = 1");
        $adminStmt->execute();
        $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($admins)) {
            error_log("No admins found with phone numbers for punch-out summary");
            return false;
        }

        // 2. Get statistics for the specified team
        $stats = getPunchOutStatsByTeam($pdo, $date, $teamType);

        // Debug logging
        error_log("Punch-out stats for {$teamType}: Total={$stats['total']}, List length=" . strlen($stats['list_formatted']));

        // If list is empty, ensure it has a default message
        if (empty($stats['list_formatted'])) {
            $stats['list_formatted'] = "No punch-outs recorded yet for today.";
        }

        // 3. Send notifications to each admin
        $waService = new WhatsAppService();
        $successCount = 0;
        $currentTime = date('h:i A'); // Current time in 12-hour format

        // Determine template name based on team type
        $templateName = ($teamType === 'Studio')
            ? 'admin_studioteam_punchout_summary'
            : 'admin_fieldteam_punchout_summary';

        foreach ($admins as $admin) {
            $params = [
                $currentTime,             // {{1}} Time
                $stats['list_formatted']  // {{2}} List of punch-outs with reports
            ];

            $result = $waService->sendTemplateMessage(
                $admin['phone'],
                $templateName,
                'en_US',
                $params
            );

            if ($result['success']) {
                $successCount++;
            }
        }

        error_log("Scheduled {$teamType} punch-out summary sent successfully. Total messages: $successCount");
        return true;

    } catch (Exception $e) {
        error_log("Scheduled Punch-Out Summary Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Get Punch-Out Statistics by Team Type
 * 
 * @param PDO $pdo Database connection
 * @param string $date Date to check (Y-m-d format)
 * @param string $teamType 'Studio' or 'Field'
 * @return array Statistics with list_formatted and total count
 */
function getPunchOutStatsByTeam($pdo, $date, $teamType)
{
    try {
        // Determine role filter based on team type (Same logic as punch-in)
        $roleCondition = '';
        if ($teamType === 'Studio') {
            // Studio team - exclude field roles
            $roleCondition = "AND u.role NOT IN ('Site Supervisor', 'site coordinator', 'purchase manager')";
        } else {
            // Field team - only field roles
            $roleCondition = "AND u.role IN ('Site Supervisor', 'site coordinator', 'purchase manager')";
        }

        // Get all employees who punched OUT today for this team type
        $query = "SELECT 
                    u.username,
                    a.punch_out,
                    a.work_report
                FROM attendance a
                JOIN users u ON a.user_id = u.id
                WHERE a.date = ?
                AND a.punch_out IS NOT NULL
                $roleCondition
                ORDER BY a.punch_out DESC"; // Most recent punch-outs first

        $stmt = $pdo->prepare($query);
        $stmt->execute([$date]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $listLines = [];

        foreach ($employees as $emp) {
            $punchOutTime = date('h:i A', strtotime($emp['punch_out']));
            $report = $emp['work_report'] ?? 'No report submitted';

            // Clean report of any newlines
            $report = str_replace(["\r", "\n"], " ", $report);

            // Format: Name - Time | Report: ...
            $listLines[] = "{$emp['username']} - {$punchOutTime} | Report: {$report}";
        }

        // Join multiple entries with a visible separator instead of newlines
        // because WhatsApp API is rejecting newlines for this template parameter.
        $listFormatted = implode("\n\n", $listLines);
        // If the API error persists with \n, we might need to change this implode too.
        // But let's try keeping the structure clean first, maybe it was the *report* content.
        // Actually, the error said "Param text cannot have new-line". This applies to the WHOLE param.
        // So we MUST remove all newlines from the final string.

        $listFormatted = implode("  ///  ", $listLines);

        // Return empty string if no punch outs, so template doesn't break? 
        // Or handle "None" - but requirement didn't specify "None". 
        // If empty, loop above skips sending, so it's fine.
        if (empty($listFormatted)) {
            $listFormatted = "No punch-outs recorded yet.";
        }

        return [
            'list_formatted' => $listFormatted,
            'total' => count($employees)
        ];

    } catch (Exception $e) {
        error_log("Get Punch-Out Stats Exception: " . $e->getMessage());
        return [
            'list_formatted' => 'Error fetching data',
            'total' => 0
        ];
    }
}
