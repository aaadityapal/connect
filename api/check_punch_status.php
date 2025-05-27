<?php
session_start();
require_once '../config/db_connect.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Check current punch status
$check_punch = $conn->prepare("SELECT punch_in, punch_out, working_hours, overtime_hours, shifts_id FROM attendance WHERE user_id = ? AND date = ?");
$check_punch->bind_param("is", $user_id, $today);
$check_punch->execute();
$result = $check_punch->get_result();
$attendance = $result->fetch_assoc();

// Get user's current shift information
$query = "SELECT us.*, s.shift_name, s.start_time, s.end_time 
          FROM user_shifts us
          JOIN shifts s ON us.shift_id = s.id
          WHERE us.user_id = ? 
          AND us.effective_from <= ?
          AND (us.effective_to IS NULL OR us.effective_to >= ?)
          ORDER BY us.effective_from DESC 
          LIMIT 1";
          
$shift_stmt = $conn->prepare($query);
$shift_stmt->bind_param("iss", $user_id, $today, $today);
$shift_stmt->execute();
$shift_result = $shift_stmt->get_result();

$response = [
    'is_punched_in' => false,
    'last_punch_in' => null,
    'is_completed' => false, // Flag to check if attendance is completed for the day
    'working_hours' => null,
    'shift_info' => null
];

// Add shift information to response
if ($shift_result->num_rows > 0) {
    $shift_data = $shift_result->fetch_assoc();
    
    // Format times for display
    $start_time_formatted = date('h:i A', strtotime($shift_data['start_time']));
    $end_time_formatted = date('h:i A', strtotime($shift_data['end_time']));
    
    $response['shift_info'] = [
        'shift_id' => $shift_data['shift_id'],
        'shift_name' => $shift_data['shift_name'],
        'start_time' => $shift_data['start_time'],
        'end_time' => $shift_data['end_time'],
        'start_time_formatted' => $start_time_formatted,
        'end_time_formatted' => $end_time_formatted,
        'weekly_offs' => $shift_data['weekly_offs']
    ];
} else {
    // Default shift if none is assigned
    $response['shift_info'] = [
        'shift_id' => null,
        'shift_name' => 'Default Shift',
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'start_time_formatted' => '09:00 AM',
        'end_time_formatted' => '06:00 PM',
        'weekly_offs' => 'Saturday,Sunday'
    ];
}

if ($attendance) {
    if ($attendance['punch_in']) {
        $response['is_punched_in'] = true;
        $response['last_punch_in'] = date('h:i A', strtotime($attendance['punch_in']));
        
        // Check if already punched out
        if ($attendance['punch_out']) {
            $response['is_completed'] = true;
            
            // Get the working hours and overtime hours directly from the database
            if (isset($attendance['working_hours'])) {
                // Use the stored working hours if available
                $response['working_hours'] = $attendance['working_hours'];
                $response['overtime_hours'] = $attendance['overtime_hours'] ?? '00:00:00';
                
                // Also calculate hours in decimal for compatibility
                $parts = explode(':', $attendance['working_hours']);
                if (count($parts) === 3) {
                    $hours = intval($parts[0]);
                    $minutes = intval($parts[1]);
                    $seconds = intval($parts[2]);
                    $decimal_hours = $hours + ($minutes / 60) + ($seconds / 3600);
                    $response['working_hours_decimal'] = round($decimal_hours, 2);
                }
                
                // Calculate decimal overtime
                if (isset($attendance['overtime_hours'])) {
                    $overtime_parts = explode(':', $attendance['overtime_hours']);
                    if (count($overtime_parts) === 3) {
                        $oh = intval($overtime_parts[0]);
                        $om = intval($overtime_parts[1]);
                        $os = intval($overtime_parts[2]);
                        $decimal_overtime = $oh + ($om / 60) + ($os / 3600);
                        $response['overtime_hours_decimal'] = round($decimal_overtime, 2);
                    }
                }
            } else {
                // Calculate if not stored
                $punch_in_time = strtotime($today . ' ' . $attendance['punch_in']);
                $punch_out_time = strtotime($today . ' ' . $attendance['punch_out']);
                $seconds_worked = $punch_out_time - $punch_in_time;
                
                // Format as HH:MM:SS
                $hours = floor($seconds_worked / 3600);
                $minutes = floor(($seconds_worked % 3600) / 60);
                $seconds = $seconds_worked % 60;
                $response['working_hours'] = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
                $response['working_hours_decimal'] = round($seconds_worked / 3600, 2);
            }
        }
    }
}

echo json_encode($response);
?> 