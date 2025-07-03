<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Database connection
require_once 'config/db_connect.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID is required']);
    exit();
}

$id = $_GET['id'];

// Get the overtime details - use prepared statement for security
$query = "SELECT a.id, a.date, a.punch_in, a.punch_out, a.working_hours, 
                 a.overtime_hours, a.overtime_status, a.status, a.remarks, 
                 a.work_report, a.shift_time, a.location, a.modified_by, a.modified_at,
                 u.username, u.designation 
          FROM attendance a 
          JOIN users u ON a.user_id = u.id 
          WHERE a.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || mysqli_num_rows($result) == 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Record not found']);
    exit();
}

$overtime_data = mysqli_fetch_assoc($result);

// Debug the raw work report value
error_log("Raw work_report from database: " . ($overtime_data['work_report'] ?? 'NULL'));

// Format the data for response
$response = [
    'id' => $overtime_data['id'],
    'username' => htmlspecialchars($overtime_data['username']),
    'designation' => htmlspecialchars($overtime_data['designation']),
    'date' => date('d M, Y', strtotime($overtime_data['date'])),
    'punch_in' => $overtime_data['punch_in'] ? date('h:i A', strtotime($overtime_data['punch_in'])) : 'N/A',
    'punch_out' => $overtime_data['punch_out'] ? date('h:i A', strtotime($overtime_data['punch_out'])) : 'N/A',
    'working_hours' => formatHoursValue($overtime_data['working_hours']),
    'overtime_hours' => formatHoursValue($overtime_data['overtime_hours']),
    'calculated_overtime' => formatHoursValue($overtime_data['overtime_hours']),
    'overtime_status' => $overtime_data['overtime_status'] ?: 'pending',
    'status' => $overtime_data['status'],
    'remarks' => htmlspecialchars($overtime_data['remarks'] ?: ''),
    'work_report' => isset($overtime_data['work_report']) && !empty($overtime_data['work_report']) ? 
                     htmlspecialchars($overtime_data['work_report']) : 'No work report available',
    'shift_time' => $overtime_data['shift_time'] ?: 'Standard',
    'shift_end_time' => '18:00:00', // Default shift end time
    'location' => $overtime_data['location'] ?: 'Not recorded',
    'overtime_approved_by' => $overtime_data['modified_by'] ? getUserName($conn, $overtime_data['modified_by']) : '',
    'overtime_actioned_at' => $overtime_data['modified_at'] ? date('d M, Y h:i A', strtotime($overtime_data['modified_at'])) : ''
];

// Function to get username
function getUserName($conn, $user_id) {
    $query = "SELECT username FROM users WHERE id = '$user_id'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        return htmlspecialchars($user['username']);
    }
    
    return 'Unknown user';
}

// Function to format hours value that might be in time format
function formatHoursValue($value) {
    if (is_numeric($value)) {
        return number_format((float)$value, 2);
    } else if (strpos($value, ':') !== false) {
        // Convert time format (HH:MM:SS) to decimal hours
        $parts = explode(':', $value);
        $hours = (int)$parts[0];
        $minutes = isset($parts[1]) ? (int)$parts[1] / 60 : 0;
        $seconds = isset($parts[2]) ? (int)$parts[2] / 3600 : 0;
        return number_format($hours + $minutes + $seconds, 2);
    }
    return $value;
}

// Return the data as JSON
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>