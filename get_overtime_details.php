<?php
session_start();
// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Senior Manager (Studio)') {
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

$id = mysqli_real_escape_string($conn, $_GET['id']);

// Get the overtime details
$query = "SELECT a.*, u.username, u.designation 
          FROM attendance a 
          JOIN users u ON a.user_id = u.id 
          WHERE a.id = '$id'";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Record not found']);
    exit();
}

$overtime_data = mysqli_fetch_assoc($result);

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
    'overtime' => $overtime_data['overtime'] ?: 'pending',
    'status' => $overtime_data['status'],
    'remarks' => htmlspecialchars($overtime_data['remarks'] ?: ''),
    'work_report' => htmlspecialchars($overtime_data['work_report'] ?: ''),
    'shift_time' => $overtime_data['shift_time'] ?: 'Standard',
    'location' => $overtime_data['location'] ?: 'Not recorded',
    'modified_by' => $overtime_data['modified_by'] ? getUserName($conn, $overtime_data['modified_by']) : '',
    'modified_at' => $overtime_data['modified_at'] ? date('d M, Y h:i A', strtotime($overtime_data['modified_at'])) : ''
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