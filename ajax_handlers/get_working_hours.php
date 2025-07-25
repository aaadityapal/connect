<?php
/**
 * Ajax handler to calculate working hours between punch-in and punch-out
 * Used by the recent_time_widget.php to display working hours on punch-out
 */

// Include database connection
require_once '../config/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Start session with looser restrictions to ensure session is read properly
session_start();

// Check if user is logged in - either from session or from POST parameter
$user_id = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
    // Allow explicit user_id from POST as fallback for testing
    $user_id = intval($_POST['user_id']);
}

if ($user_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit;
}

// User ID is already set from above
// $user_id is already defined
$date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');

// Validate user ID
if ($user_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user ID'
    ]);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid date format'
    ]);
    exit;
}

// Get punch-in and punch-out times from database
$query = "SELECT punch_in, punch_out FROM attendance 
          WHERE user_id = ? AND date = ? 
          ORDER BY id DESC LIMIT 1";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param("is", $user_id, $date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No attendance record found'
    ]);
    exit;
}

$row = $result->fetch_assoc();
$punch_in = $row['punch_in'];
$punch_out = $row['punch_out'];

// If punch-out is not set (which shouldn't happen in this context, but just in case)
if (empty($punch_out)) {
    echo json_encode([
        'success' => false,
        'message' => 'Punch-out time not found'
    ]);
    exit;
}

// Get user's shift information
$shift_query = "
    SELECT s.id, s.shift_name, s.start_time, s.end_time 
    FROM shifts s
    JOIN user_shifts us ON s.id = us.shift_id
    WHERE us.user_id = ?
    AND ? BETWEEN us.effective_from AND IFNULL(us.effective_to, '9999-12-31')
    LIMIT 1
";

$shift_stmt = $conn->prepare($shift_query);
if (!$shift_stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error when fetching shift: ' . $conn->error
    ]);
    exit;
}

$shift_stmt->bind_param("is", $user_id, $date);
$shift_stmt->execute();
$shift_result = $shift_stmt->get_result();

$shift_end_time = null;
$shift_name = "Standard";

if ($shift_result->num_rows > 0) {
    $shift_row = $shift_result->fetch_assoc();
    $shift_end_time = $shift_row['end_time'];
    $shift_name = $shift_row['shift_name'];
} else {
    // Default shift end time if no shift is assigned (6:00 PM)
    $shift_end_time = '18:00:00';
}

// Calculate working hours
try {
    $punch_in_time = new DateTime($punch_in);
    $punch_out_time = new DateTime($punch_out);
    
    // Calculate the difference
    $interval = $punch_in_time->diff($punch_out_time);
    
    // Format the working hours
    $hours = $interval->h + ($interval->days * 24);
    $minutes = $interval->i;
    $seconds = $interval->s;
    
    // Format as HH:MM:SS
    $working_hours = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    
    // Calculate overtime
    $has_overtime = false;
    $overtime_hours = "00:00:00";
    
    // Get shift end time as DateTime
    $shift_end_datetime = new DateTime($date . ' ' . $shift_end_time);
    
    // If punch out is after shift end time
    if ($punch_out_time > $shift_end_datetime) {
        $overtime_interval = $shift_end_datetime->diff($punch_out_time);
        $ot_hours = $overtime_interval->h + ($overtime_interval->days * 24);
        $ot_minutes = $overtime_interval->i;
        $ot_seconds = $overtime_interval->s;
        
        // Calculate total overtime seconds for easier comparison
        $total_overtime_seconds = $ot_hours * 3600 + $ot_minutes * 60 + $ot_seconds;
        
        // Check if overtime is at least 1 hour and 30 minutes (5400 seconds)
        if ($total_overtime_seconds >= 5400) {
            $has_overtime = true;
            $overtime_hours = sprintf('%02d:%02d:%02d', $ot_hours, $ot_minutes, $ot_seconds);
        }
    }
    
    // If hours are more than 12, add a note
    $note = '';
    if ($hours >= 12) {
        $note = 'Long shift detected';
    }
    
    echo json_encode([
        'success' => true,
        'working_hours' => $working_hours,
        'has_overtime' => $has_overtime,
        'overtime_hours' => $overtime_hours,
        'shift_end_time' => $shift_end_time,
        'shift_name' => $shift_name,
        'note' => $note
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error calculating working hours: ' . $e->getMessage()
    ]);
} 