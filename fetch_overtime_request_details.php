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
    
    // Check if the attendance date is from November 2025 onwards
    $date_check_query = "SELECT date FROM attendance WHERE id = ?";
    $date_stmt = $pdo->prepare($date_check_query);
    $date_stmt->execute([$attendance_id]);
    $date_row = $date_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$date_row) {
        throw new Exception('Attendance record not found');
    }
    
    $attendance_date = new DateTime($date_row['date']);
    $nov2025 = new DateTime('2025-11-01');
    
    // Only fetch overtime request details for records from November 2025 onwards
    if ($attendance_date < $nov2025) {
        throw new Exception('Overtime request details are only available for records from November 2025 onwards');
    }
    
    // Query to fetch overtime request information
    $query = "SELECT 
                oreq.id,
                oreq.user_id,
                oreq.attendance_id,
                oreq.date,
                oreq.shift_end_time,
                oreq.punch_out_time,
                oreq.overtime_hours,
                oreq.work_report,
                oreq.overtime_description,
                oreq.manager_id,
                oreq.status,
                oreq.submitted_at,
                oreq.actioned_at,
                oreq.manager_comments,
                oreq.updated_at,
                u.username as employee_name,
                u.unique_id as employee_id
              FROM overtime_requests oreq
              JOIN users u ON oreq.user_id = u.id
              WHERE oreq.attendance_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$attendance_id]);
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        throw new Exception('Overtime request not found for this attendance record');
    }
    
    // Format the data for the response
    $overtime_request_details = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'attendance_id' => $row['attendance_id'],
        'date' => $row['date'],
        'shift_end_time' => formatTime($row['shift_end_time']),
        'punch_out_time' => formatTime($row['punch_out_time']),
        'overtime_hours' => number_format(floatval($row['overtime_hours']), 1),
        'work_report' => !empty($row['work_report']) ? $row['work_report'] : 'No work report submitted',
        'overtime_description' => !empty($row['overtime_description']) ? $row['overtime_description'] : 'No description provided',
        'manager_id' => $row['manager_id'],
        'status' => ucfirst($row['status']),
        'submitted_at' => $row['submitted_at'] ? date('M j, Y g:i A', strtotime($row['submitted_at'])) : 'Not submitted',
        'actioned_at' => $row['actioned_at'] ? date('M j, Y g:i A', strtotime($row['actioned_at'])) : 'Not actioned',
        'manager_comments' => !empty($row['manager_comments']) ? $row['manager_comments'] : 'No comments',
        'updated_at' => $row['updated_at'] ? date('M j, Y g:i A', strtotime($row['updated_at'])) : 'Never updated',
        'employee_name' => $row['employee_name'],
        'employee_id' => $row['employee_id']
    ];
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $overtime_request_details
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching overtime request details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
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
?>