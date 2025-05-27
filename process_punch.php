<?php
/**
 * Process Punch In/Out Data
 * Handles receiving and storing attendance data in the database
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Get current date and time
$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$current_datetime = date('Y-m-d H:i:s');
$timestamp = date('YmdHis');

// Get client IP address
$ip_address = $_SERVER['REMOTE_ADDR'];
if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
}

// Get device info
$device_info = $_SERVER['HTTP_USER_AGENT'];

// Initialize response array
$response = [
    'status' => 'error',
    'message' => 'Invalid request'
];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get punch type (in or out)
    $punch_type = isset($_POST['punch_type']) ? $_POST['punch_type'] : '';
    
    // Validate punch type
    if ($punch_type !== 'in' && $punch_type !== 'out') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid punch type']);
        exit;
    }
    
    // Process photo if provided (base64 encoded image)
    $photo_filename = null;
    if (isset($_POST['photo_data']) && !empty($_POST['photo_data'])) {
        $photo_data = $_POST['photo_data'];
        
        // Remove the data:image/jpeg;base64, part if present
        if (strpos($photo_data, 'data:image/jpeg;base64,') === 0) {
            $photo_data = substr($photo_data, 23);
        }
        
        // Validate base64 data
        $decoded_data = base64_decode($photo_data, true);
        if (!$decoded_data) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid image data']);
            exit;
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/attendance/';
        if (!file_exists($upload_dir)) {
            // Create directory recursively
            if (!mkdir($upload_dir, 0755, true)) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to create upload directory']);
                exit;
            }
        }
        
        // Generate unique filename
        $photo_filename = $upload_dir . $user_id . '_' . $punch_type . '_' . $timestamp . '.jpg';
        
        // Save the image to file
        if (!file_put_contents($photo_filename, $decoded_data)) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save image']);
            exit;
        }
    }
    
    // Process location data
    $latitude = isset($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? $_POST['longitude'] : null;
    $accuracy = isset($_POST['accuracy']) ? $_POST['accuracy'] : null;
    $address = isset($_POST['address']) ? $_POST['address'] : null;
    
    // Location data as JSON
    $location_json = json_encode([
        'latitude' => $latitude,
        'longitude' => $longitude,
        'accuracy' => $accuracy,
        'address' => $address
    ]);
    
    // Check if there's already an attendance record for today
    $check_query = "SELECT * FROM attendance WHERE user_id = ? AND date = ?";
    $check_stmt = $conn->prepare($check_query);
    
    if (!$check_stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $check_stmt->bind_param("is", $user_id, $current_date);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $existing_record = $result->fetch_assoc();
    $check_stmt->close();
    
    // Get user's shift information from user_shifts table
    $shift_query = "SELECT us.*, s.start_time, s.end_time 
                   FROM user_shifts us
                   JOIN shifts s ON us.shift_id = s.id
                   WHERE us.user_id = ? 
                   AND us.effective_from <= ?
                   AND (us.effective_to IS NULL OR us.effective_to >= ?)
                   ORDER BY us.effective_from DESC 
                   LIMIT 1";
    
    $shift_stmt = $conn->prepare($shift_query);
    
    if (!$shift_stmt) {
        error_log("Failed to prepare shift query: " . $conn->error);
        // Continue with default shift if query fails
        $shift_id = null;
        $weekly_offs = 'Saturday,Sunday';
        $standard_hours = 8; // Default
    } else {
        $shift_stmt->bind_param("iss", $user_id, $current_date, $current_date);
        $shift_stmt->execute();
        $shift_result = $shift_stmt->get_result();
        
        if ($shift_result->num_rows > 0) {
            $shift_data = $shift_result->fetch_assoc();
            $shift_id = $shift_data['shift_id'];
            $weekly_offs = $shift_data['weekly_offs'];
            
            // Calculate standard hours from shift start and end times
            $shift_start = strtotime($shift_data['start_time']);
            $shift_end = strtotime($shift_data['end_time']);
            // Handle overnight shifts
            if ($shift_end <= $shift_start) {
                $shift_end += 86400; // Add 24 hours
            }
            $standard_hours = ($shift_end - $shift_start) / 3600;
        } else {
            // No shift assigned, use defaults
            $shift_id = null;
            $weekly_offs = 'Saturday,Sunday';
            $standard_hours = 8; // Default
        }
        
        $shift_stmt->close();
    }
    
    // Process punch in
    if ($punch_type === 'in') {
        if ($existing_record) {
            // Already punched in today, check if already punched out
            if ($existing_record['punch_out']) {
                echo json_encode(['status' => 'error', 'message' => 'Already completed attendance for today']);
                exit;
            } else {
                // Update existing record (shouldn't normally happen, but handle just in case)
                echo json_encode(['status' => 'error', 'message' => 'Already punched in today']);
                exit;
            }
        } else {
            // Create new attendance record
            $insert_query = "INSERT INTO attendance (
                user_id, 
                date, 
                punch_in, 
                location, 
                ip_address, 
                device_info, 
                latitude, 
                longitude, 
                accuracy, 
                address, 
                status, 
                created_at, 
                punch_in_photo, 
                punch_in_latitude, 
                punch_in_longitude, 
                punch_in_accuracy,
                shifts_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = $conn->prepare($insert_query);
            
            if (!$insert_stmt) {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
                exit;
            }
            
            $status = 'present';
            
            $insert_stmt->bind_param(
                "isssssssssssssssi",
                $user_id,
                $current_date,
                $current_time,
                $location_json,
                $ip_address,
                $device_info,
                $latitude,
                $longitude,
                $accuracy,
                $address,
                $status,
                $current_datetime,
                $photo_filename,
                $latitude,
                $longitude,
                $accuracy,
                $shift_id
            );
            
            if ($insert_stmt->execute()) {
                $response = [
                    'status' => 'success',
                    'message' => 'Punch in successful',
                    'time' => date('h:i A', strtotime($current_time)),
                    'date' => date('d M Y', strtotime($current_date))
                ];
                
                // Update session state to reflect the punch-in
                $_SESSION['punched_in'] = true;
                $_SESSION['attendance_completed'] = false;
            } else {
                $response = [
                    'status' => 'error',
                    'message' => 'Database error: ' . $insert_stmt->error
                ];
            }
            
            $insert_stmt->close();
        }
    }
    // Process punch out
    else if ($punch_type === 'out') {
        if ($existing_record) {
            // Check if already punched out
            if ($existing_record['punch_out']) {
                echo json_encode(['status' => 'error', 'message' => 'Already punched out today']);
                exit;
            }
            
            // Get work report if provided
            $work_report = isset($_POST['work_report']) ? $_POST['work_report'] : 'Punched out';
            
            // Calculate working hours
            $punch_in_time = strtotime($existing_record['date'] . ' ' . $existing_record['punch_in']);
            $punch_out_time = strtotime($current_date . ' ' . $current_time);
            $seconds_worked = $punch_out_time - $punch_in_time;
            
            // Format working hours as HH:MM:SS for better precision
            $hours = floor($seconds_worked / 3600);
            $minutes = floor(($seconds_worked % 3600) / 60);
            $seconds = $seconds_worked % 60;
            $working_hours_formatted = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
            
            // Also calculate decimal hours for compatibility
            $working_hours_decimal = round($seconds_worked / 3600, 2);
            
            // Use shift information if available, otherwise use default
            if (!empty($existing_record['shifts_id'])) {
                // If there's already a shift ID in the record, use that one
                $existing_shift_id = $existing_record['shifts_id'];
                
                // Query to get shift details
                $shift_details_query = "SELECT s.start_time, s.end_time 
                                      FROM shifts s
                                      WHERE s.id = ?";
                $shift_details_stmt = $conn->prepare($shift_details_query);
                
                if ($shift_details_stmt) {
                    $shift_details_stmt->bind_param("i", $existing_shift_id);
                    $shift_details_stmt->execute();
                    $shift_details_result = $shift_details_stmt->get_result();
                    
                    if ($shift_details_result->num_rows > 0) {
                        $shift_details = $shift_details_result->fetch_assoc();
                        
                        // Calculate standard hours from shift
                        $shift_start = strtotime($shift_details['start_time']);
                        $shift_end = strtotime($shift_details['end_time']);
                        // Handle overnight shifts
                        if ($shift_end <= $shift_start) {
                            $shift_end += 86400; // Add 24 hours
                        }
                        $standard_hours = ($shift_end - $shift_start) / 3600;
                    }
                    
                    $shift_details_stmt->close();
                }
            }
            
            // Calculate overtime based on standard hours
            $overtime_seconds = max(0, $seconds_worked - ($standard_hours * 3600));
            $overtime_hours = floor($overtime_seconds / 3600);
            $overtime_minutes = floor(($overtime_seconds % 3600) / 60);
            $overtime_seconds_remainder = $overtime_seconds % 60;
            $overtime_hours_formatted = sprintf('%02d:%02d:%02d', $overtime_hours, $overtime_minutes, $overtime_seconds_remainder);
            
            // Also calculate decimal overtime for compatibility
            $overtime_hours_decimal = round($overtime_seconds / 3600, 2);
            
            // Update the attendance record
            $update_query = "UPDATE attendance SET 
                punch_out = ?, 
                punch_out_photo = ?, 
                punch_out_latitude = ?, 
                punch_out_longitude = ?, 
                punch_out_accuracy = ?, 
                punch_out_address = ?, 
                working_hours = ?, 
                overtime_hours = ?,
                work_report = ?, 
                modified_at = ?
                WHERE user_id = ? AND date = ?";
            
            $update_stmt = $conn->prepare($update_query);
            
            if (!$update_stmt) {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
                exit;
            }
            
            // Debug - log the values
            error_log("Punch Out Debug - Values: time=$current_time, photo=$photo_filename, lat=$latitude, lng=$longitude, acc=$accuracy, addr=$address, wh=$working_hours_formatted, wh_decimal=$working_hours_decimal, oh=$overtime_hours_formatted, oh_decimal=$overtime_hours_decimal, wr=" . substr($work_report, 0, 50) . "..., dt=$current_datetime, uid=$user_id, date=$current_date, shift_id=" . ($shift_id ?? 'NULL'));
            
            // Fix the parameter binding to match the SQL query
            // s=string, d=double, i=integer
            $update_stmt->bind_param(
                "ssddssddssis",
                $current_time,
                $photo_filename,
                $latitude,
                $longitude,
                $accuracy,
                $address,
                $working_hours_formatted,
                $overtime_hours_formatted,
                $work_report,
                $current_datetime,
                $user_id,
                $current_date
            );
            
            if ($update_stmt->execute()) {
                $response = [
                    'status' => 'success',
                    'message' => 'Punch out successful',
                    'time' => date('h:i A', strtotime($current_time)),
                    'date' => date('d M Y', strtotime($current_date)),
                    'working_hours' => $working_hours_formatted,
                    'working_hours_decimal' => $working_hours_decimal,
                    'overtime_hours' => $overtime_hours_formatted,
                    'overtime_hours_decimal' => $overtime_hours_decimal
                ];
                
                // Update session state to reflect the punch-out
                $_SESSION['punched_in'] = false;
                $_SESSION['attendance_completed'] = true;
            } else {
                $response = [
                    'status' => 'error',
                    'message' => 'Database error: ' . $update_stmt->error
                ];
            }
            
            $update_stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No punch in record found for today']);
            exit;
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 