<?php
session_start();
require_once 'config/db_connect.php';
require_once 'includes/functions/shift_functions.php';

header('Content-Type: application/json');

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// Get filter parameters
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n') - 1; // 0-11
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

try {
    // Fetch overtime data
    $overtime_data = getOvertimeData($pdo, $user_id, $filter_month, $filter_year);
    
    // Calculate statistics
    $statistics = calculateOvertimeStatistics($overtime_data);
    
    // Also fetch user shift info
    $user_shift = getUserShiftEndTime($pdo, $user_id);
    $shift_end_time = $user_shift ? convertTo12HourFormat($user_shift['end_time']) : 'N/A';
    
    echo json_encode([
        'success' => true,
        'data' => $overtime_data,
        'statistics' => $statistics,
        'shift_end_time' => $shift_end_time
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to fetch data: ' . $e->getMessage()]);
}

/**
 * Calculate overtime statistics
 */
function calculateOvertimeStatistics($overtime_data) {
    $pending_requests = 0;
    $approved_hours = 0;
    $rejected_requests = 0;
    
    foreach ($overtime_data as $record) {
        $status = strtolower($record['status']);
        $hours = floatval($record['ot_hours']);
        
        switch ($status) {
            case 'pending':
                $pending_requests++;
                break;
            case 'approved':
                $approved_hours += $hours;
                break;
            case 'rejected':
                $rejected_requests++;
                break;
        }
    }
    
    // Calculate estimated cost based on approved hours (assuming $15/hour rate)
    $hourly_rate = 15;
    $estimated_cost = $approved_hours * $hourly_rate;
    
    return [
        'pending_requests' => $pending_requests,
        'approved_hours' => round($approved_hours, 1),
        'rejected_requests' => $rejected_requests,
        'estimated_cost' => round($estimated_cost, 2)
    ];
}

/**
 * Get overtime data for a user based on month/year filters
 */
function getOvertimeData($pdo, $user_id, $month, $year) {
    try {
        // Calculate the first and last day of the selected month
        $first_day = sprintf('%04d-%02d-01', $year, $month + 1);
        $last_day = date('Y-m-t', strtotime($first_day));
        
        // First, get the user's shift end time for the current date to use in filtering
        $user_shift = getUserShiftEndTime($pdo, $user_id);
        $shift_end_time = $user_shift ? $user_shift['end_time'] : '18:00:00';
        
        // Query to fetch overtime data with the new rule:
        // Only show records where punch_out time is at least 1.5 hours after shift end time
        // We need to handle two cases:
        // 1. Same day punch out (punch_out > shift_end_time + 1.5 hours)
        // 2. Next day punch out (punch_out < shift_end_time, meaning they worked past midnight)
        $query = "SELECT 
                    a.id as attendance_id,
                    a.date,
                    a.punch_out,
                    a.overtime_hours,
                    a.work_report,
                    a.overtime_status
                  FROM attendance a
                  WHERE a.user_id = ? 
                  AND a.date BETWEEN ? AND ?
                  AND a.overtime_status IS NOT NULL
                  AND (
                    -- Case 1: Punch out on same day and at least 1.5 hours after shift end
                    (TIME_TO_SEC(a.punch_out) >= TIME_TO_SEC(?) + 5400)
                    OR
                    -- Case 2: Punch out on next day (before shift end time)
                    (TIME_TO_SEC(a.punch_out) < TIME_TO_SEC(?))
                  )
                  ORDER BY a.date DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id, $first_day, $last_day, $shift_end_time, $shift_end_time]);
        
        $data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Calculate overtime hours dynamically based on shift end time and punch out time
            $calculated_ot_hours = calculateOvertimeHours($shift_end_time, $row['punch_out']);
            
            // Check if there's a corresponding overtime request in the overtime_requests table
            $overtime_description = '';
            $status = ucfirst($row['overtime_status'] ?? 'pending');
            
            // Check if the record is expired (older than 15 days)
            $record_date = new DateTime($row['date']);
            $nov_2025 = new DateTime('2025-11-01');
            $current_date = new DateTime();
            $interval = $record_date->diff($current_date);
            $days_old = $interval->days;
            $is_expired = $days_old > 15;
            
            // If record is expired AND status is pending, set status to Expired
            // For submitted, approved, or rejected records, do not show expired status
            $status_lower = strtolower($status);
            if ($is_expired && $status_lower === 'pending') {
                $status = 'Expired';
                $overtime_description = 'Overtime request period has expired (older than 15 days).';
            } else if ($record_date >= $nov_2025) {
                // For records from November 2025 and later, if an overtime request exists, 
                // always display the status as 'Submitted' regardless of its actual database value
                if ($status_lower !== 'expired') { // Only check if not already expired
                    $check_request_query = "SELECT id, overtime_description FROM overtime_requests WHERE attendance_id = ? LIMIT 1";
                    $check_stmt = $pdo->prepare($check_request_query);
                    $check_stmt->execute([$row['attendance_id']]);
                    $request_result = $check_stmt->fetch();
                    
                    if ($request_result) {
                        $status = 'Submitted';
                        $overtime_description = $request_result['overtime_description'];
                    } else if ($status_lower === 'pending') {
                        // For pending status with no submission, show appropriate message
                        $overtime_description = 'Overtime not yet submitted. Please submit your overtime request.';
                    }
                }
            } else {
                // For records before November 2025, fetch message from overtime_notifications table
                if ($status_lower !== 'expired') { // Only check if not already expired
                    $check_notification_query = "SELECT message FROM overtime_notifications WHERE overtime_id = ? LIMIT 1";
                    $check_notification_stmt = $pdo->prepare($check_notification_query);
                    $check_notification_stmt->execute([$row['attendance_id']]);
                    $notification_result = $check_notification_stmt->fetch();
                    
                    if ($notification_result) {
                        $overtime_description = $notification_result['message'];
                    } else if ($status_lower === 'pending') {
                        // For pending status with no notification, show appropriate message
                        $overtime_description = 'Overtime details not yet provided.';
                    }
                }
            }
            
            $data[] = [
                'attendance_id' => $row['attendance_id'],
                'date' => $row['date'],
                'punch_out_time' => convertTo12HourFormat($row['punch_out']) ?? 'N/A',
                'ot_hours' => $calculated_ot_hours,
                'work_report' => $row['work_report'] ?? '',
                'overtime_description' => $overtime_description,
                'status' => $status
            ];
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("Error fetching overtime data: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculate overtime hours based on shift end time and punch out time
 */
function calculateOvertimeHours($shiftEndTime, $punchOutTime) {
    if (!$shiftEndTime || !$punchOutTime) {
        return '0.0';
    }
    
    // Convert times to seconds
    $shiftEndSeconds = timeToSeconds($shiftEndTime);
    $punchOutSeconds = timeToSeconds($punchOutTime);
    
    // Calculate overtime in seconds
    $overtimeSeconds = 0;
    
    if ($punchOutSeconds > $shiftEndSeconds) {
        // Same day punch out
        $overtimeSeconds = $punchOutSeconds - $shiftEndSeconds;
    } else if ($punchOutSeconds < $shiftEndSeconds) {
        // Next day punch out (worked past midnight)
        $overtimeSeconds = (24 * 3600 - $shiftEndSeconds) + $punchOutSeconds;
    }
    
    // Convert seconds to minutes
    $overtimeMinutes = $overtimeSeconds / 60;
    
    // Apply rounding logic:
    // - If less than 90 minutes (1.5 hours), return 1.5 (minimum threshold)
    // - Otherwise, round down to nearest 30-minute increment
    $roundedHours = roundOvertimeHours($overtimeMinutes);
    
    return number_format($roundedHours, 1, '.', '');
}

/**
 * Round overtime hours according to the specified rules:
 * - Minimum 1.5 hours
 * - Round down to nearest 30-minute increment
 */
function roundOvertimeHours($minutes) {
    // If less than 1.5 hours (90 minutes), return 1.5 (minimum threshold)
    if ($minutes < 90) {
        return 1.5;
    }
    
    // For 1.5 hours and above:
    // Round down to the nearest 30-minute increment
    // First, subtract 1.5 hours (90 minutes) from the total
    $adjustedMinutes = $minutes - 90;
    
    // Then round down to nearest 30-minute increment
    $roundedAdjusted = floor($adjustedMinutes / 30) * 30;
    
    // Add back the 1.5 hours base
    $finalMinutes = 90 + $roundedAdjusted;
    
    // Convert back to hours
    $finalHours = $finalMinutes / 60;
    
    return $finalHours;
}

/**
 * Convert TIME format (HH:MM:SS) to seconds
 */
function timeToSeconds($time) {
    list($hours, $minutes, $seconds) = explode(':', $time);
    return ($hours * 3600) + ($minutes * 60) + $seconds;
}

/**
 * Convert 24-hour format to 12-hour AM/PM format
 */
function convertTo12HourFormat($time) {
    if (!$time || $time === 'N/A') {
        return $time;
    }
    
    // Parse the time
    $timeParts = explode(':', $time);
    if (count($timeParts) < 2) {
        return $time;
    }
    
    $hours = (int)$timeParts[0];
    $minutes = $timeParts[1];
    
    // Determine AM/PM
    $period = ($hours >= 12) ? 'PM' : 'AM';
    
    // Convert hours to 12-hour format
    if ($hours == 0) {
        $hours = 12;
    } else if ($hours > 12) {
        $hours = $hours - 12;
    }
    
    return sprintf('%d:%s %s', $hours, $minutes, $period);
}

/**
 * Convert TIME format (HH:MM:SS) to decimal hours
 */
function formatTimeToHours($time) {
    if (!$time) return '0.0';
    list($hours, $minutes, $seconds) = explode(':', $time);
    return number_format($hours + ($minutes / 60) + ($seconds / 3600), 1, '.', '');
}
?>