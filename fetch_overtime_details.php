<?php
header('Content-Type: application/json');
session_start();

// Include database connection
require_once 'config/db_connect.php';

try {
    // Get the attendance ID from the request
    $attendance_id = isset($_GET['attendance_id']) ? (int)$_GET['attendance_id'] : 0;
    
    if ($attendance_id <= 0) {
        throw new Exception('Invalid attendance ID');
    }
    
    // Query to fetch detailed overtime information
    $query = "SELECT 
                a.id as attendance_id,
                u.username,
                u.role,
                u.unique_id as employee_id,
                a.date,
                a.punch_in,
                a.punch_out,
                a.work_report,
                a.overtime_status,
                a.overtime_hours,
                a.overtime_reason,
                s.shift_name,
                s.start_time as shift_start_time,
                s.end_time as shift_end_time,
                TIMESTAMPDIFF(SECOND, 
                    STR_TO_DATE(CONCAT(a.date, ' ', s.end_time), '%Y-%m-%d %H:%i:%s'),
                    STR_TO_DATE(CONCAT(
                        CASE 
                            WHEN TIME(a.punch_out) < TIME(s.end_time) THEN DATE_ADD(a.date, INTERVAL 1 DAY)
                            ELSE a.date
                        END, 
                        ' ', a.punch_out
                    ), '%Y-%m-%d %H:%i:%s')
                ) as overtime_seconds,
                oreq.id as overtime_request_id,
                oreq.overtime_description,
                oreq.overtime_hours as submitted_ot_hours,
                oreq.status as request_status,
                oreq.submitted_at,
                oreq.actioned_at,
                oreq.manager_comments
              FROM attendance a
              JOIN users u ON a.user_id = u.id
              LEFT JOIN user_shifts us ON u.id = us.user_id AND a.date BETWEEN us.effective_from AND COALESCE(us.effective_to, '9999-12-31')
              LEFT JOIN shifts s ON us.shift_id = s.id
              LEFT JOIN overtime_requests oreq ON a.id = oreq.attendance_id
              WHERE a.id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$attendance_id]);
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        throw new Exception('Overtime record not found');
    }
    
    // Calculate overtime hours using proper rounding logic
    $overtime_seconds = intval($row['overtime_seconds']);
    $overtime_minutes = $overtime_seconds / 60;
    
    // Apply proper rounding: minimum 1.5 hours, round down to nearest 30-minute increment
    $overtime_hours = roundOvertimeHours($overtime_minutes);
    
    // Format the data for the response
    $overtime_details = [
        'attendance_id' => $row['attendance_id'],
        'username' => $row['username'],
        'employee_id' => $row['employee_id'],
        'role' => $row['role'],
        'date' => $row['date'],
        'punch_in_time' => formatTime($row['punch_in']),
        'punch_out_time' => formatTime($row['punch_out']),
        'shift_name' => $row['shift_name'] ?? 'No shift assigned',
        'shift_start_time' => formatTime($row['shift_start_time']),
        'shift_end_time' => formatTime($row['shift_end_time']),
        'calculated_ot_hours' => number_format($overtime_hours, 1),
        'submitted_ot_hours' => !empty($row['submitted_ot_hours']) ? number_format(floatval($row['submitted_ot_hours']), 1) : 'N/A',
        'work_report' => !empty($row['work_report']) && trim($row['work_report']) !== '' ? $row['work_report'] : 'No work report submitted',
        'overtime_description' => !empty($row['overtime_description']) ? $row['overtime_description'] : 'No overtime description provided',
        'overtime_reason' => !empty($row['overtime_reason']) ? $row['overtime_reason'] : 'No reason provided',
        'status' => determineDisplayStatus($row),
        'request_status' => ucfirst($row['request_status'] ?? 'N/A'),
        'submitted_at' => $row['submitted_at'] ? date('M j, Y g:i A', strtotime($row['submitted_at'])) : 'Not submitted',
        'actioned_at' => $row['actioned_at'] ? date('M j, Y g:i A', strtotime($row['actioned_at'])) : 'Not actioned',
        'manager_comments' => !empty($row['manager_comments']) ? $row['manager_comments'] : 'No comments',
        'overtime_request_id' => $row['overtime_request_id']
    ];
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $overtime_details
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching overtime details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch overtime details. Please try again.'
    ]);
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
 * Format time for display (HH:MM format)
 */
function formatTime($time) {
    if (!$time) return 'N/A';
    
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
 * Format hours from TIME format to decimal
 */
function formatHours($time) {
    if (!$time) return 'N/A';
    
    list($hours, $minutes, $seconds) = explode(':', $time);
    $totalHours = $hours + ($minutes / 60) + ($seconds / 3600);
    
    return number_format($totalHours, 1);
}

/**
 * Determine display status based on business rules
 */
function determineDisplayStatus($row) {
    $date = new DateTime($row['date']);
    $oct2025 = new DateTime('2025-10-01');
    
    // For records from October 2025 and later
    if ($date >= $oct2025) {
        // Always display 'Submitted' if a corresponding request exists
        if (!empty($row['overtime_request_id'])) {
            return 'Submitted';
        }
    }
    
    // For earlier dates or records without requests, use the stored status
    return ucfirst($row['overtime_status'] ?? 'pending');
}
?>