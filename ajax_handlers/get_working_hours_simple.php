<?php
/**
 * Simplified version of get_working_hours.php for testing
 * With proper overtime detection logic
 */

// Set header to return JSON
header('Content-Type: application/json');

// Get parameters
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 1;
$date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');

// Get attendance record for testing
// In a real implementation, this would come from the database
$punch_in = '09:00:00';  // 9:00 AM
$punch_out = '19:30:00'; // 7:30 PM

// Define shift end time (default: 6:00 PM)
$shift_end_time = '18:00:00';
$shift_name = 'Standard 9-6';

// Calculate working hours
$punch_in_time = strtotime($date . ' ' . $punch_in);
$punch_out_time = strtotime($date . ' ' . $punch_out);
$shift_end_datetime = strtotime($date . ' ' . $shift_end_time);

// Calculate total working hours
$working_seconds = $punch_out_time - $punch_in_time;
$hours = floor($working_seconds / 3600);
$minutes = floor(($working_seconds % 3600) / 60);
$seconds = $working_seconds % 60;
$working_hours = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

// Calculate overtime
$has_overtime = false;
$overtime_hours = '00:00:00';

// If punch out is after shift end time
if ($punch_out_time > $shift_end_datetime) {
    $overtime_seconds = $punch_out_time - $shift_end_datetime;
    $ot_hours = floor($overtime_seconds / 3600);
    $ot_minutes = floor(($overtime_seconds % 3600) / 60);
    $ot_seconds = $overtime_seconds % 60;
    
    // Check if overtime is at least 1 hour and 30 minutes (5400 seconds)
    if ($overtime_seconds >= 5400) {
        $has_overtime = true;
        $overtime_hours = sprintf('%02d:%02d:%02d', $ot_hours, $ot_minutes, $ot_seconds);
    }
}

// Return the response
echo json_encode([
    'success' => true,
    'message' => 'Test successful',
    'working_hours' => $working_hours,
    'has_overtime' => $has_overtime,
    'overtime_hours' => $overtime_hours,
    'shift_end_time' => $shift_end_time,
    'shift_name' => $shift_name,
    'punch_in' => $punch_in,
    'punch_out' => $punch_out,
    'overtime_seconds' => isset($overtime_seconds) ? $overtime_seconds : 0,
    'overtime_threshold' => 5400, // 1.5 hours in seconds
    'timestamp' => date('Y-m-d H:i:s')
]);
?> 