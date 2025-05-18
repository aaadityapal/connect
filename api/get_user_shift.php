<?php
session_start();
require_once '../config/db_connect.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$current_date = date('Y-m-d');

// Get the user's current shift information
$query = "SELECT us.*, s.shift_name, s.start_time, s.end_time 
          FROM user_shifts us
          JOIN shifts s ON us.shift_id = s.id
          WHERE us.user_id = ? 
          AND us.effective_from <= ?
          AND (us.effective_to IS NULL OR us.effective_to >= ?)
          ORDER BY us.effective_from DESC 
          LIMIT 1";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $user_id, $current_date, $current_date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $shift_data = $result->fetch_assoc();
    
    // Format times for display
    $start_time_formatted = date('h:i A', strtotime($shift_data['start_time']));
    $end_time_formatted = date('h:i A', strtotime($shift_data['end_time']));
    
    // Parse weekly offs
    $weekly_offs = explode(',', $shift_data['weekly_offs']);
    $weekly_offs_formatted = [];
    foreach ($weekly_offs as $day) {
        if (trim($day) !== '') {
            $weekly_offs_formatted[] = trim($day);
        }
    }
    
    $response = [
        'success' => true,
        'shift_id' => $shift_data['shift_id'],
        'shift_name' => $shift_data['shift_name'],
        'start_time' => $shift_data['start_time'],
        'end_time' => $shift_data['end_time'],
        'start_time_formatted' => $start_time_formatted,
        'end_time_formatted' => $end_time_formatted,
        'weekly_offs' => $weekly_offs_formatted,
        'effective_from' => $shift_data['effective_from'],
        'effective_to' => $shift_data['effective_to']
    ];
} else {
    // If no shift is assigned, provide default values
    $response = [
        'success' => true,
        'shift_id' => null,
        'shift_name' => 'Default Shift',
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'start_time_formatted' => '09:00 AM',
        'end_time_formatted' => '06:00 PM',
        'weekly_offs' => ['Saturday', 'Sunday'],
        'effective_from' => null,
        'effective_to' => null
    ];
}

echo json_encode($response);
?> 