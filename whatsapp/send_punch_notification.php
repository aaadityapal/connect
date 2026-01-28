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
