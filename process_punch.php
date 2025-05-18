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
                punch_in_accuracy
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $insert_stmt = $conn->prepare($insert_query);
            
            if (!$insert_stmt) {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
                exit;
            }
            
            $status = 'present';
            
            $insert_stmt->bind_param(
                "isssssssssssssss",
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
                $accuracy
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
            
            // Calculate working hours
            $punch_in_time = strtotime($existing_record['date'] . ' ' . $existing_record['punch_in']);
            $punch_out_time = strtotime($current_date . ' ' . $current_time);
            $working_hours = round(($punch_out_time - $punch_in_time) / 3600, 2); // Convert seconds to hours
            
            // Get shift details (assumed to be retrieved from another table)
            // For demo purposes, assuming 8-hour shift
            $standard_hours = 8;
            $overtime_hours = max(0, $working_hours - $standard_hours);
            
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
                modified_at = ?
                WHERE user_id = ? AND date = ?";
            
            $update_stmt = $conn->prepare($update_query);
            
            if (!$update_stmt) {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
                exit;
            }
            
            $update_stmt->bind_param(
                "sssssddsssi",
                $current_time,
                $photo_filename,
                $latitude,
                $longitude,
                $accuracy,
                $address,
                $working_hours,
                $overtime_hours,
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
                    'working_hours' => $working_hours,
                    'overtime_hours' => $overtime_hours
                ];
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