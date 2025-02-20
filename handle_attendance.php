<?php
session_start();
require_once 'config/db_connect.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the incoming request
error_log('Attendance request received: ' . file_get_contents('php://input'));

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    die(json_encode(['success' => false, 'message' => 'Invalid request data']));
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d'); // IST date
$now = date('Y-m-d H:i:s'); // IST timestamp
$ip_address = getUserIP();
$device_info = getDeviceInfo();
$location = $data['location'] ?? 'Unknown';

// Log the processed data with IST time
error_log("Processing attendance [IST]: User ID: $user_id, Type: {$data['type']}, Date: $today, Time: $now");

if ($data['type'] === 'in') {
    // Check for existing punch-in
    $check_existing = $conn->prepare("SELECT punch_in FROM attendance WHERE user_id = ? AND date = ? AND punch_in IS NOT NULL");
    $check_existing->bind_param("is", $user_id, $today);
    $check_existing->execute();
    $result = $check_existing->get_result();
    
    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        echo json_encode([
            'success' => false,
            'error' => 'already_punched_in',
            'last_punch_in' => date('h:i A', strtotime($existing['punch_in'])),
            'message' => 'You have already punched in for today'
        ]);
        exit;
    }
}

if ($data['type'] === 'in') {
    try {
        // Check if already punched in
        $stmt = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            die(json_encode(['success' => false, 'message' => 'Already punched in today']));
        }

        // Create new attendance record
        $stmt = $conn->prepare("INSERT INTO attendance (user_id, date, punch_in, location, ip_address, device_info) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $user_id, $today, $now, $location, $ip_address, $device_info);
        
        if ($stmt->execute()) {
            error_log("Punch in successful for user $user_id");
            echo json_encode(['success' => true, 'message' => 'Punched in successfully']);
        } else {
            error_log("Punch in failed for user $user_id: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error recording punch in', 'debug' => $stmt->error]);
        }
    } catch (Exception $e) {
        error_log("Exception during punch in: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'System error during punch in', 'debug' => $e->getMessage()]);
    }
} else if ($data['type'] === 'out') {
    try {
        // Check for existing punch-in
        $stmt = $conn->prepare("SELECT id, punch_in, shift_time FROM attendance WHERE user_id = ? AND date = ? AND punch_out IS NULL");
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            die(json_encode(['success' => false, 'message' => 'No active punch-in found for today']));
        }

        $row = $result->fetch_assoc();
        
        // Calculate working hours and overtime
        $punch_in_time = new DateTime($row['punch_in']);
        $punch_out_time = new DateTime($now);

        // If this is an automatic midnight punch-out, set max working hours to 6 PM
        if ($punch_out_time->format('H:i') === '00:00') {
            $max_work_time = new DateTime($today . ' 18:00:00'); // 6:00 PM
            if ($punch_out_time > $max_work_time) {
                $punch_out_time = $max_work_time;
            }
        }
        
        $interval = $punch_in_time->diff($punch_out_time);
        
        // Format total working hours
        $working_hours = sprintf(
            '%02d:%02d:%02d',
            $interval->h + ($interval->days * 24),
            $interval->i,
            $interval->s
        );
        
        // Convert shift time to seconds (default 9 hours = 32400 seconds)
        $shift_seconds = strtotime($row['shift_time']) - strtotime('TODAY');
        
        // Calculate total worked seconds
        $worked_seconds = $interval->days * 86400 + $interval->h * 3600 + $interval->i * 60 + $interval->s;
        
        // Calculate overtime
        $overtime_seconds = max(0, $worked_seconds - $shift_seconds);
        
        // Format overtime
        if ($overtime_seconds > 0) {
            $overtime_days = floor($overtime_seconds / 86400);
            $overtime_seconds_remaining = $overtime_seconds % 86400;
            $overtime_hours = floor($overtime_seconds_remaining / 3600);
            $overtime_seconds_remaining %= 3600;
            $overtime_minutes = floor($overtime_seconds_remaining / 60);
            $overtime_seconds = $overtime_seconds_remaining % 60;
            
            $overtime = sprintf(
                '%02d:%02d:%02d',
                $overtime_hours + ($overtime_days * 24),
                $overtime_minutes,
                $overtime_seconds
            );
        } else {
            $overtime = '00:00:00';
        }
        
        // Update the record
        $stmt = $conn->prepare("UPDATE attendance 
                               SET punch_out = ?,
                                   working_hours = ?,
                                   overtime = ?
                               WHERE id = ?");
        
        $stmt->bind_param("sssi", $now, $working_hours, $overtime, $row['id']);
        
        if ($stmt->execute()) {
            // Format overtime message
            $overtime_message = '';
            if ($overtime_seconds > 0) {
                $overtime_parts = [];
                if ($overtime_days > 0) {
                    $overtime_parts[] = $overtime_days . ' day(s)';
                }
                if ($overtime_hours > 0) {
                    $overtime_parts[] = $overtime_hours . ' hour(s)';
                }
                if ($overtime_minutes > 0) {
                    $overtime_parts[] = $overtime_minutes . ' minute(s)';
                }
                $overtime_message = "\nOvertime: " . implode(', ', $overtime_parts);
            }

            echo json_encode([
                'success' => true, 
                'message' => 'Punched out successfully',
                'workingHours' => $working_hours,
                'overtime' => $overtime,
                'overtimeMessage' => $overtime_message
            ]);
        } else {
            error_log("Punch out failed for user $user_id: " . $stmt->error);
            echo json_encode([
                'success' => false, 
                'message' => 'Error recording punch out', 
                'debug' => $stmt->error
            ]);
        }
    } catch (Exception $e) {
        error_log("Exception during punch out: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'System error during punch out', 
            'debug' => $e->getMessage()
        ]);
    }
}

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function getDeviceInfo() {
    return $_SERVER['HTTP_USER_AGENT'];
}

function autoProcessMidnightPunchOut($conn) {
    $today = date('Y-m-d');
    $midnight = date('Y-m-d H:i:s');
    
    // Find all users who haven't punched out
    $stmt = $conn->prepare("SELECT user_id, id, punch_in FROM attendance 
                           WHERE date = ? AND punch_out IS NULL");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $punch_in_time = new DateTime($row['punch_in']);
        $max_work_time = new DateTime($today . ' 18:00:00'); // 6:00 PM
        
        // Calculate working hours until 6 PM
        $interval = $punch_in_time->diff($max_work_time);
        
        // Format working hours
        $working_hours = sprintf(
            '%02d:%02d:%02d',
            $interval->h + ($interval->days * 24),
            $interval->i,
            $interval->s
        );
        
        // Calculate overtime (if any)
        $shift_seconds = 32400; // 9 hours in seconds
        $worked_seconds = $interval->days * 86400 + $interval->h * 3600 + $interval->i * 60 + $interval->s;
        $overtime_seconds = max(0, $worked_seconds - $shift_seconds);
        
        // Format overtime
        $overtime = '00:00:00';
        if ($overtime_seconds > 0) {
            $overtime = sprintf(
                '%02d:%02d:%02d',
                floor($overtime_seconds / 3600),
                floor(($overtime_seconds % 3600) / 60),
                $overtime_seconds % 60
            );
        }
        
        // Update the record
        $update_stmt = $conn->prepare("UPDATE attendance 
                                     SET punch_out = ?,
                                         working_hours = ?,
                                         overtime = ?,
                                         auto_punch_out = 1
                                     WHERE id = ?");
        $update_stmt->bind_param("sssi", $midnight, $working_hours, $overtime, $row['id']);
        $update_stmt->execute();
    }
}

// Check if it's midnight and process automatic punch-outs
if (date('H:i') === '00:00') {
    autoProcessMidnightPunchOut($conn);
}
?> 