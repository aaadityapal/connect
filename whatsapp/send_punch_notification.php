<?php
require_once __DIR__ . '/WhatsAppService.php';

/**
 * Send Punch In Notification via WhatsApp
 *
 * Template: attendance_punchin_summary
 * {{1}} Name
 * {{2}} Punch-In Time
 * {{3}} Late Status
 * {{4}} Location (from attendance.address)
 * {{5}} Day
 * {{6}} Working Days (total in month)
 * {{7}} Present Days (this month)
 * {{8}} Absent Days (elapsed - present)
 * {{9}} Late Entries (this month)
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
        $currentMonth = date('m');
        $currentYear = date('Y');
        $currentDayNum = (int) date('d');

        // 2. Fetch today's punch-in time and location (address) from attendance table
        $attStmt = $pdo->prepare(
            "SELECT punch_in, address FROM attendance
             WHERE user_id = ? AND date = ?
             LIMIT 1"
        );
        $attStmt->execute([$userId, $currentDate]);
        $todayAtt = $attStmt->fetch(PDO::FETCH_ASSOC);
        $punchInTime = $todayAtt['punch_in'] ?? $currentTime;
        $location = (!empty($todayAtt['address'])) ? $todayAtt['address'] : 'Not Available';

        // 3. Fetch user's current shift (start_time, end_time, weekly_offs)
        //    by joining user_shifts and shifts tables
        $shiftStmt = $pdo->prepare(
            "SELECT s.start_time, s.end_time, us.weekly_offs
             FROM user_shifts us
             JOIN shifts s ON us.shift_id = s.id
             WHERE us.user_id = ?
               AND us.effective_from <= ?
               AND (us.effective_to IS NULL OR us.effective_to >= ?)
             ORDER BY us.effective_from DESC
             LIMIT 1"
        );
        $shiftStmt->execute([$userId, $currentDate, $currentDate]);
        $shiftData = $shiftStmt->fetch(PDO::FETCH_ASSOC);

        // 4. Parse Weekly Offs for this user's shift
        $weeklyOffsArr = [];
        if ($shiftData && !empty($shiftData['weekly_offs'])) {
            $weeklyOffsArr = array_map('trim', explode(',', $shiftData['weekly_offs']));
        }

        // 5. Fetch office holidays for this month
        $holidays = [];
        $holidayStmt = $pdo->prepare(
            "SELECT holiday_date FROM office_holidays
             WHERE MONTH(holiday_date) = ? AND YEAR(holiday_date) = ?"
        );
        $holidayStmt->execute([$currentMonth, $currentYear]);
        $holidays = $holidayStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // 6. Calculate Working Days (total in month) and Elapsed Working Days
        $msgWorkingDays = 0;
        $elapsedWorkingDays = 0;
        $daysInMonth = (int) date('t');

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $loopDateStr = sprintf("%04d-%02d-%02d", $currentYear, $currentMonth, $i);
            $loopDayName = date('l', strtotime($loopDateStr));

            if (in_array($loopDateStr, $holidays))
                continue; // Holiday
            if (in_array($loopDayName, $weeklyOffsArr))
                continue; // Weekly off

            $msgWorkingDays++;
            if ($i <= $currentDayNum) {
                $elapsedWorkingDays++;
            }
        }

        // 7. Calculate Present Days, Absent Days, Late Status and Late Entries
        $presentDays = 0;
        $lateCount = 0;
        $lateStatus = 'On Time ✅';

        if ($shiftData && !empty($shiftData['start_time'])) {
            // Get all attendances for the current month
            $attMonthStmt = $pdo->prepare(
                "SELECT date, punch_in FROM attendance 
                 WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ? AND punch_in IS NOT NULL"
            );
            $attMonthStmt->execute([$userId, $currentMonth, $currentYear]);
            $monthAttendances = $attMonthStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch all approved leaves overlapping the current month
            $leaveStartStr = "$currentYear-$currentMonth-01";
            $leaveEndStr = date('Y-m-t', strtotime($leaveStartStr));
            $leaveMonthStmt = $pdo->prepare(
                "SELECT lr.start_date, lr.end_date, lr.time_from, lr.time_to, lr.day_type, lt.name as lt_name 
                 FROM leave_request lr 
                 JOIN leave_types lt ON lr.leave_type = lt.id 
                 WHERE lr.user_id = ? 
                   AND lr.start_date <= ? AND lr.end_date >= ?
                   AND LOWER(lr.status) = 'approved'"
            );
            $leaveMonthStmt->execute([$userId, $leaveEndStr, $leaveStartStr]);
            $monthLeaves = $leaveMonthStmt->fetchAll(PDO::FETCH_ASSOC);

            $shiftStartStr = $shiftData['start_time'];
            $shiftEndStr = $shiftData['end_time'] ?? null;
            $gracePeriodSeconds = 15 * 60; // exactly 15 minutes

            // Ensure today's punch-in is evaluated if not returned in $monthAttendances
            $todayInRecords = false;
            foreach ($monthAttendances as $att) {
                if ($att['date'] === $currentDate) {
                    $todayInRecords = true;
                    break;
                }
            }
            if (!$todayInRecords && $punchInTime) {
                $monthAttendances[] = ['date' => $currentDate, 'punch_in' => $punchInTime];
            }

            // Calculate present days
            $presentDays = count($monthAttendances);

            foreach ($monthAttendances as $att) {
                $aDate = $att['date'];
                $aPunchIn = $att['punch_in'];

                $shiftStartTs = strtotime($aDate . ' ' . $shiftStartStr);
                $expectedStartTs = $shiftStartTs;

                // Check for overlapping approved leave for this date
                $dailyLeave = null;
                foreach ($monthLeaves as $leave) {
                    if ($aDate >= $leave['start_date'] && $aDate <= $leave['end_date']) {
                        $dailyLeave = $leave;
                        break;
                    }
                }

                if ($dailyLeave) {
                    $ltName = strtolower($dailyLeave['lt_name']);
                    // Is it Half Day or Short Leave?
                    if (strpos($ltName, 'half day') !== false || strpos($ltName, 'short') !== false) {
                        $isMorningLeave = false;
                        if (!empty($dailyLeave['time_from'])) {
                            // Leave starts in the morning (within 2 hours of shift start)
                            $leaveStartTs = strtotime($aDate . ' ' . $dailyLeave['time_from']);
                            if ($leaveStartTs <= ($shiftStartTs + 2 * 3600)) {
                                $isMorningLeave = true;
                            }
                        } else if ($dailyLeave['day_type'] === 'first_half') {
                            $isMorningLeave = true;
                        }

                        if ($isMorningLeave) {
                            if (!empty($dailyLeave['time_to'])) {
                                $expectedStartTs = strtotime($aDate . ' ' . $dailyLeave['time_to']);
                            } else if (!empty($shiftEndStr)) {
                                // Default to half of the shift duration
                                $shiftEndTs = strtotime($aDate . ' ' . $shiftEndStr);
                                if ($shiftEndTs < $shiftStartTs)
                                    $shiftEndTs += 86400; // Overnight shift
                                $expectedStartTs = $shiftStartTs + (($shiftEndTs - $shiftStartTs) / 2);
                            }
                        }
                    }
                }

                $punchInTs = strtotime($aDate . ' ' . $aPunchIn);

                if ($punchInTs > ($expectedStartTs + $gracePeriodSeconds)) {
                    $lateCount++;

                    if ($aDate === $currentDate) {
                        $minutesLate = (int) ceil(($punchInTs - $expectedStartTs) / 60);
                        $lateStatus = "Late ⚠️ ({$minutesLate} min late)";
                    }
                }
            }
        } else {
            // Fallback for present days if no shift data
            $presentStmt = $pdo->prepare(
                "SELECT COUNT(*) as present_count FROM attendance
                 WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ? AND punch_in IS NOT NULL"
            );
            $presentStmt->execute([$userId, $currentMonth, $currentYear]);
            $presentRow = $presentStmt->fetch(PDO::FETCH_ASSOC);
            $presentDays = $presentRow['present_count'] ?? 0;
        }

        // 8. Absent Days (elapsed working days - present days, minimum 0)
        $absentDays = max(0, $elapsedWorkingDays - $presentDays);

        // 11. Prepare Template Parameters
        // Template: attendance_punchin_summary
        // {{1}} Name
        // {{2}} Punch-In Time
        // {{3}} Late Status
        // {{4}} Location
        // {{5}} Day
        // {{6}} Working Days (total in month)
        // {{7}} Present Days
        // {{8}} Absent Days
        // {{9}} Late Entries
        $templateName = 'attendance_punchin_summary';
        $params = [
            $username,                                  // {{1}} Name
            date('h:i A', strtotime($punchInTime)),     // {{2}} Punch-In Time (12-hr)
            $lateStatus,                                // {{3}} Late Status
            $location,                                  // {{4}} Location
            $currentDay . ', ' . date('d M Y'),         // {{5}} Day + Date (e.g. Monday, 23 Feb 2026)
            (string) $msgWorkingDays,                   // {{6}} Working Days
            (string) $presentDays,                      // {{7}} Present Days
            (string) $absentDays,                       // {{8}} Absent Days
            (string) $lateCount,                        // {{9}} Late Entries
        ];

        // 12. Send Template Message
        $waService = new WhatsAppService();
        $waService->sendTemplateMessage($phone, $templateName, 'en_US', $params);

        error_log("WhatsApp punch-in notification sent for user ID: $userId | Status: $lateStatus");
        return true;

    } catch (Exception $e) {
        error_log("WhatsApp Notification Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send Punch Out Notification via WhatsApp
 * 
 * Template: employee_punch_out_detais
 * {{1}} Name
 * {{2}} Punch-Out Time
 * {{3}} Total Working Hours
 * {{4}} Location (punch_out_address)
 * {{5}} Day
 * {{6}} Punch-In Time
 * {{7}} Overtime
 * {{8}} Work Report
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

        // 2. Fetch Attendance Details (Punch In, Punch Out, Work Report, Address)
        $attStmt = $pdo->prepare(
            "SELECT punch_in, punch_out, work_report, punch_out_address FROM attendance 
             WHERE user_id = ? AND date = ?"
        );
        $attStmt->execute([$userId, $currentDate]);
        $attendance = $attStmt->fetch(PDO::FETCH_ASSOC);

        if (!$attendance || empty($attendance['punch_in']) || empty($attendance['punch_out'])) {
            error_log("Punch Out Notification Error: Missing attendance data for user $userId");
            return false;
        }

        $punchInTime = strtotime($attendance['punch_in']);
        $punchOutTime = strtotime($attendance['punch_out']);
        $workReport = $attendance['work_report'] ?? 'No report submitted';
        $location = (!empty($attendance['punch_out_address'])) ? $attendance['punch_out_address'] : 'Not Available';

        // 3. Calculate Total Working Hours
        $secondsWorked = $punchOutTime - $punchInTime;
        $hoursWorked = floor($secondsWorked / 3600);
        $minutesWorked = floor(($secondsWorked % 3600) / 60);
        // Format e.g., "09 hrs 30 mins" or "09:30" - template implies a clear format
        $totalWorkingTimeStr = sprintf("%02d:%02d hrs", $hoursWorked, $minutesWorked);

        // 4. Calculate Overtime
        $overtimeStr = "00:00 hrs";

        // Get User's Shift
        $shiftStmt = $pdo->prepare(
            "SELECT s.start_time, s.end_time 
             FROM user_shifts us 
             JOIN shifts s ON us.shift_id = s.id 
             WHERE us.user_id = ? 
               AND us.effective_from <= ? 
               AND (us.effective_to IS NULL OR us.effective_to >= ?) 
             ORDER BY us.effective_from DESC LIMIT 1"
        );
        $shiftStmt->execute([$userId, $currentDate, $currentDate]);
        $shift = $shiftStmt->fetch(PDO::FETCH_ASSOC);

        if ($shift) {
            $shiftStart = strtotime($currentDate . ' ' . $shift['start_time']);
            $shiftEnd = strtotime($currentDate . ' ' . $shift['end_time']);

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
                $overtimeStr = sprintf("%02d:%02d hrs", $otHours, $otMinutes);
            }
        }

        // 5. Prepare Template Parameters
        // Template: employee_punch_out_detais
        // {{1}} Name
        // {{2}} Punch-Out Time
        // {{3}} Total Working Hours
        // {{4}} Location
        // {{5}} Day
        // {{6}} Punch-In Time
        // {{7}} Overtime
        // {{8}} Work Report

        $templateName = 'employee_punch_out_detais';
        $params = [
            $username,                                  // {{1}} Name
            date('h:i A', $punchOutTime),               // {{2}} Punch-Out Time (12-hr)
            $totalWorkingTimeStr,                       // {{3}} Total Working Hours
            $location,                                  // {{4}} Location
            $currentDay . ', ' . date('d M Y'),         // {{5}} Day + Date
            date('h:i A', $punchInTime),                // {{6}} Punch-In Time (12-hr)
            $overtimeStr,                               // {{7}} Overtime
            $workReport                                 // {{8}} Work Report
        ];

        // 6. Send Message
        $waService = new WhatsAppService();
        $waService->sendTemplateMessage($phone, $templateName, 'en_US', $params);

        error_log("WhatsApp punch-out notification sent for user ID: $userId");
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
 * Sends punch-out summary to all admins for a specific team with PDF attachment
 * 
 * @param PDO $pdo Database connection
 * @param string $date Date for which to send summary (Y-m-d format)
 * @param string $teamType 'Studio' or 'Field'
 * @return bool
 */
function sendScheduledPunchOutSummary($pdo, $date, $teamType)
{
    try {
        // Load PDF generation function
        require_once __DIR__ . '/generate_punchout_summary_pdf.php';

        // 1. Fetch all active admin phone numbers
        $adminStmt = $pdo->prepare("SELECT id, admin_name, phone FROM admin_notifications WHERE is_active = 1");
        $adminStmt->execute();
        $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($admins)) {
            error_log("No admins found with phone numbers for punch-out summary");
            return false;
        }

        // 2. Get detailed punch-out data for the specified team
        $punchOutData = getPunchOutDataByTeam($pdo, $date, $teamType);

        // 3. Generate PDF (even if no data, create empty report)
        $pdfResult = generatePunchOutSummaryPDF($punchOutData, $date, $teamType);

        if (!$pdfResult['success']) {
            error_log("Failed to generate PDF for {$teamType} team: " . ($pdfResult['error'] ?? 'Unknown error'));
            return false;
        }

        // 4. Prepare summary text for template
        $currentTime = date('h:i A'); // Current time like "06:20 PM"
        $totalCount = count($punchOutData);

        if ($totalCount > 0) {
            $summaryText = "Total employees punched out: {$totalCount}. Please see attached PDF for detailed work reports.";
        } else {
            $summaryText = "No punch-outs recorded yet for today.";
        }

        // 5. Send notifications to each admin with PDF attachment
        $waService = new WhatsAppService();
        $successCount = 0;

        // Determine template name based on team type
        $templateName = ($teamType === 'Studio')
            ? 'admin_punchout_summary_studio'
            : 'admin_punchout_summary_field';

        foreach ($admins as $admin) {
            $params = [
                $currentTime,    // {{1}} Time (e.g., "06:20 PM")
                $summaryText     // {{2}} Summary text
            ];

            $result = $waService->sendTemplateMessageWithDocument(
                $admin['phone'],
                $templateName,
                'en_US',
                $params,
                $pdfResult['url'],
                $pdfResult['file_name']
            );

            if ($result['success']) {
                $successCount++;
            } else {
                error_log("Failed to send punch-out summary to {$admin['phone']}: " . ($result['response'] ?? 'Unknown error'));
            }
        }

        error_log("Scheduled {$teamType} punch-out summary sent successfully. Total messages: $successCount, PDF: {$pdfResult['file_name']}");
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

        // Join multiple entries with a clearly visible separator
        // We use " // " as a separator which is more readable than "///" or single lines
        $listFormatted = implode("\n\n--------------------\n\n", $listLines);

        // WhatsApp API often rejects pure newlines in header parameters, but body parameters are usually fine.
        // If the previous issue was due to the Template Variable restrictions, we must stick to single line.
        // Let's try a very distinct text separator first if Newlines were the cause of failure.
        // Given your screenshot shows "///" working but looking bad, let's try emojis and spacing.
        $listFormatted = implode("\n\n👉 ", $listLines);
        $listFormatted = "👉 " . $listFormatted; // Add one to the start

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

/**
 * Get Detailed Punch-Out Data by Team Type (for PDF generation)
 * 
 * @param PDO $pdo Database connection
 * @param string $date Date to check (Y-m-d format)
 * @param string $teamType 'Studio' or 'Field'
 * @return array Array of punch-out records with username, punch_out, work_report
 */
function getPunchOutDataByTeam($pdo, $date, $teamType)
{
    try {
        // Determine role filter based on team type
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

        return $employees;

    } catch (Exception $e) {
        error_log("Get Punch-Out Data Exception: " . $e->getMessage());
        return [];
    }
}
