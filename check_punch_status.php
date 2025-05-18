<?php
session_start();
require_once 'config/db_connect.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Check current punch status
$check_punch = $conn->prepare("SELECT punch_in, punch_out, working_hours, overtime_hours FROM attendance WHERE user_id = ? AND date = ?");
$check_punch->bind_param("is", $user_id, $today);
$check_punch->execute();
$result = $check_punch->get_result();
$attendance = $result->fetch_assoc();

// Get the user's assigned shift
$shift_query = "SELECT us.shift_id, s.shift_name, s.start_time, s.end_time, s.weekly_offs 
                FROM user_shifts us
                JOIN shifts s ON us.shift_id = s.id
                WHERE us.user_id = ? 
                AND us.effective_from <= ?
                AND (us.effective_to IS NULL OR us.effective_to >= ?)
                ORDER BY us.effective_from DESC 
                LIMIT 1";

$shift_stmt = $conn->prepare($shift_query);
$shift_info = null;

if ($shift_stmt) {
    $shift_stmt->bind_param("iss", $user_id, $today, $today);
    $shift_stmt->execute();
    $shift_result = $shift_stmt->get_result();
    
    if ($shift_result->num_rows > 0) {
        $shift_data = $shift_result->fetch_assoc();
        $shift_info = [
            'shift_id' => $shift_data['shift_id'],
            'shift_name' => $shift_data['shift_name'],
            'start_time' => $shift_data['start_time'],
            'end_time' => $shift_data['end_time'],
            'weekly_offs' => $shift_data['weekly_offs'],
            'start_time_formatted' => date('h:i A', strtotime($shift_data['start_time'])),
            'end_time_formatted' => date('h:i A', strtotime($shift_data['end_time']))
        ];
    }
    
    $shift_stmt->close();
}

$response = [
    'is_punched_in' => false,
    'last_punch_in' => null,
    'shift_info' => $shift_info,
    'is_completed' => ($attendance && $attendance['punch_out']) ? true : false
];

if ($attendance) {
    if ($attendance['punch_in']) {
        $response['is_punched_in'] = !$attendance['punch_out']; // Only consider punched in if not punched out
        $response['last_punch_in'] = date('h:i A', strtotime($attendance['punch_in']));
    }
    
    // Include working hours and overtime hours if the user has already punched out
    if ($attendance['punch_out']) {
        // Calculate decimal working hours from HH:MM:SS format for display
        $working_hours_time = $attendance['working_hours'];
        if (!empty($working_hours_time)) {
            $working_time_parts = explode(':', $working_hours_time);
            if (count($working_time_parts) == 3) {
                $hours = (int)$working_time_parts[0];
                $minutes = (int)$working_time_parts[1];
                $seconds = (int)$working_time_parts[2];
                
                // Convert to decimal hours (e.g. 8:30:00 becomes 8.5)
                $working_hours_decimal = $hours + ($minutes / 60) + ($seconds / 3600);
                
                // Format for display (HH:MM)
                $working_hours_display = sprintf('%d:%02d', $hours, $minutes);
                
                $response['working_hours'] = $working_hours_display;
                $response['working_hours_time'] = $working_hours_time;
                $response['working_hours_decimal'] = round($working_hours_decimal, 2);
            } else {
                $response['working_hours'] = '0:00';
                $response['working_hours_decimal'] = 0;
            }
        } else {
            $response['working_hours'] = '0:00';
            $response['working_hours_decimal'] = 0;
        }
        
        // Calculate decimal overtime hours from HH:MM:SS format for display
        $overtime_hours_time = $attendance['overtime_hours'];
        if (!empty($overtime_hours_time)) {
            $overtime_time_parts = explode(':', $overtime_hours_time);
            if (count($overtime_time_parts) == 3) {
                $ot_hours = (int)$overtime_time_parts[0];
                $ot_minutes = (int)$overtime_time_parts[1];
                $ot_seconds = (int)$overtime_time_parts[2];
                
                // Convert to decimal hours (e.g. 1:30:00 becomes 1.5)
                $overtime_hours_decimal = $ot_hours + ($ot_minutes / 60) + ($ot_seconds / 3600);
                
                // Format for display (HH:MM)
                $overtime_hours_display = sprintf('%d:%02d', $ot_hours, $ot_minutes);
                
                $response['overtime_hours'] = $overtime_hours_display; 
                $response['overtime_hours_time'] = $overtime_hours_time;
                $response['overtime_hours_decimal'] = round($overtime_hours_decimal, 2);
            } else {
                $response['overtime_hours'] = '0:00';
                $response['overtime_hours_decimal'] = 0;
            }
        } else {
            $response['overtime_hours'] = '0:00';
            $response['overtime_hours_decimal'] = 0;
        }
        
        $response['punch_out_time'] = date('h:i A', strtotime($attendance['punch_out']));
    }
}

echo json_encode($response);
?> 