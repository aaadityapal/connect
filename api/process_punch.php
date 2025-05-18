<?php
/**
 * Process Punch In/Out Data
 * Handles receiving and storing attendance data in the database
 */

// Enable detailed error logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log errors to file
ini_set('log_errors', 1);
ini_set('error_log', '../php-error.log');

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Include database connection
require_once '../config/db_connect.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Get current date and time
$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$current_datetime = date('Y-m-d H:i:s');
$timestamp = date('YmdHis');

// Get the user's assigned shift
$shift_time = '09:00:00'; // Default value
$shift_id = null;

// Query to get user's current shift
$shift_query = "SELECT us.shift_id, s.start_time 
                FROM user_shifts us
                JOIN shifts s ON us.shift_id = s.id
                WHERE us.user_id = ? 
                AND us.effective_from <= ?
                AND (us.effective_to IS NULL OR us.effective_to >= ?)
                ORDER BY us.effective_from DESC 
                LIMIT 1";

$shift_stmt = $conn->prepare($shift_query);
if ($shift_stmt) {
    $shift_stmt->bind_param("iss", $user_id, $current_date, $current_date);
    $shift_stmt->execute();
    $shift_result = $shift_stmt->get_result();
    
    if ($shift_result->num_rows > 0) {
        $shift_data = $shift_result->fetch_assoc();
        $shift_time = $shift_data['start_time'];
        $shift_id = $shift_data['shift_id'];
    }
    
    $shift_stmt->close();
}

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
    
    // Check if action parameter is provided instead (for backward compatibility)
    if (empty($punch_type) && isset($_POST['action'])) {
        if ($_POST['action'] === 'punch_in') {
            $punch_type = 'in';
        } else if ($_POST['action'] === 'punch_out') {
            $punch_type = 'out';
        }
    }
    
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
        $upload_dir = '../uploads/attendance/';
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
        
        // Store relative path in database
        $photo_filename = 'uploads/attendance/' . $user_id . '_' . $punch_type . '_' . $timestamp . '.jpg';
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
                shift_time,
                shifts_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = $conn->prepare($insert_query);
            
            if (!$insert_stmt) {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
                exit;
            }
            
            $status = 'present';
            
            $insert_stmt->bind_param(
                "issssssddssssdddsi",
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
                $shift_time,
                $shift_id
            );
            
            if ($insert_stmt->execute()) {
                $response = [
                    'status' => 'success',
                    'message' => 'Punch in successful',
                    'time' => date('h:i A', strtotime($current_time)),
                    'date' => date('d M Y', strtotime($current_date))
                ];
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
            
            // Get work report data from request (if available)
            $work_report = isset($_POST['work_report']) ? $_POST['work_report'] : '';
            
            // Calculate working hours
            $punch_in_time = strtotime($existing_record['date'] . ' ' . $existing_record['punch_in']);
            $punch_out_time = strtotime($current_date . ' ' . $current_time);
            $seconds_worked = $punch_out_time - $punch_in_time; // Total seconds worked
            
            // Convert to decimal hours for calculations (still needed for overtime calculation)
            $working_hours_decimal = $seconds_worked / 3600;
            
            // Format working hours as HH:MM:SS for MySQL TIME column
            $hours = floor($seconds_worked / 3600);
            $minutes = floor(($seconds_worked % 3600) / 60);
            $seconds = $seconds_worked % 60;
            $working_hours_formatted = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
            
            // Log the formatted time to check it's valid
            error_log("Formatted working hours: $working_hours_formatted from $seconds_worked seconds");
            
            // Get shift details from existing record or from query
            $standard_hours = 8; // Default value
            
            // If shift_time wasn't stored correctly in the existing record, use the one we just fetched
            if ($existing_record['shift_time'] == '09:00:00' && $shift_time != '09:00:00') {
                // Update the shift_time in the existing record as well
                $update_shift_query = "UPDATE attendance SET shift_time = ?, shifts_id = ? WHERE id = ?";
                $update_shift_stmt = $conn->prepare($update_shift_query);
                if ($update_shift_stmt) {
                    $update_shift_stmt->bind_param("sii", $shift_time, $shift_id, $existing_record['id']);
                    $update_shift_stmt->execute();
                    $update_shift_stmt->close();
                }
            }
            
            // Calculate raw overtime hours
            $raw_overtime_hours = max(0, $working_hours_decimal - $standard_hours);
            
            // Round overtime hours to nearest 30 minutes (0.5 hours)
            $overtime_hours_minutes = $raw_overtime_hours * 60; // Convert to minutes
            $rounded_overtime_minutes = round($overtime_hours_minutes / 30) * 30; // Round to nearest 30 minutes
            $overtime_hours_decimal = $rounded_overtime_minutes / 60; // Convert back to hours
            
            // Format overtime hours as HH:MM:SS for MySQL TIME column
            $ot_hours = floor($overtime_hours_decimal);
            $ot_minutes = floor(($overtime_hours_decimal - $ot_hours) * 60);
            $ot_seconds = 0; // We don't need seconds precision for overtime
            $overtime_hours_formatted = sprintf('%02d:%02d:%02d', $ot_hours, $ot_minutes, $ot_seconds);
            
            // Log overtime calculation for debugging
            error_log("Overtime calculation: raw=$raw_overtime_hours hours, minutes=$overtime_hours_minutes, rounded=$rounded_overtime_minutes minutes, final=$overtime_hours_decimal hours, formatted=$overtime_hours_formatted");
            error_log("Working hours: decimal=$working_hours_decimal, formatted=$working_hours_formatted, seconds worked=$seconds_worked");
            
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
            
            // Debug - log values to error log
            error_log("Punch-out data: time=$current_time, photo=$photo_filename, lat=$latitude, lng=$longitude, acc=$accuracy, addr=$address, wh=$working_hours_formatted, oh=$overtime_hours_formatted, wr=" . substr($work_report, 0, 50) . "..., dt=$current_datetime, uid=$user_id, date=$current_date");
            
            // Fixed parameter binding - match types with the exact database column types
            // punch_out_latitude: decimal(10,8), punch_out_longitude: decimal(11,8), punch_out_accuracy: float
            try {
                $update_stmt->bind_param(
                    "ssddssssssis",  // s=string, d=decimal/float, i=integer
                    $current_time,  // punch_out (time) - s
                    $photo_filename,  // punch_out_photo (varchar) - s
                    $latitude,      // punch_out_latitude (decimal) - d
                    $longitude,     // punch_out_longitude (decimal) - d
                    $accuracy,      // punch_out_accuracy (float) - d
                    $address,       // punch_out_address (text) - s
                    $working_hours_formatted, // working_hours (time) - s
                    $overtime_hours_formatted, // overtime_hours (time) - s
                    $work_report,   // work_report (text) - s
                    $current_datetime, // modified_at (timestamp) - s
                    $user_id,       // user_id (int) - i
                    $current_date   // date (date) - s
                );
            } catch (Exception $e) {
                // Log and return the binding error
                error_log("Bind parameter error: " . $e->getMessage());
                echo json_encode(['status' => 'error', 'message' => 'Parameter binding error: ' . $e->getMessage()]);
                exit;
            }
            
            // Log the query and parameters for debugging
            error_log("Update query parameters: time=$current_time, photo=$photo_filename, lat=$latitude, lng=$longitude, acc=$accuracy, addr=$address, wh=$working_hours_formatted, oh=$overtime_hours_formatted, wr=$work_report, dt=$current_datetime, uid=$user_id, date=$current_date");
            
            if ($update_stmt->execute()) {
                $response = [
                    'status' => 'success',
                    'message' => 'Punch out successful',
                    'time' => date('h:i A', strtotime($current_time)),
                    'date' => date('d M Y', strtotime($current_date)),
                    'working_hours' => $working_hours_formatted,
                    'overtime_hours' => $overtime_hours_formatted,
                    'working_hours_decimal' => round($working_hours_decimal, 2),
                    'overtime_hours_decimal' => $overtime_hours_decimal
                ];
            } else {
                $response = [
                    'status' => 'error',
                    'message' => 'Database error: ' . $update_stmt->error . ' - Check error log for details'
                ];
                // Log the error for debugging
                error_log("Punch out database error: " . $update_stmt->error);
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