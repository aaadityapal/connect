<?php
/**
 * API endpoint to handle Come In functionality
 * Records user location, photo, and geofence status in the attendance table
 */

// Start session and include database connection
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/activity_logger.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Get the user ID from session
$user_id = $_SESSION['user_id'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get current date and time in IST
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

// Get POST data
$action = isset($_POST['action']) ? $_POST['action'] : '';
$latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0;
$accuracy = isset($_POST['accuracy']) ? floatval($_POST['accuracy']) : 0;
$address = isset($_POST['address']) ? $_POST['address'] : 'Unknown location';
$is_within_geofence = isset($_POST['is_within_geofence']) ? ($_POST['is_within_geofence'] == '1') : false;
$closest_location = isset($_POST['closest_location']) ? $_POST['closest_location'] : '';
$outside_location_reason = isset($_POST['outside_location_reason']) ? $_POST['outside_location_reason'] : '';

// Calculate distance from geofence (if outside)
$distance_from_geofence = 0;
if (!$is_within_geofence && isset($_POST['distance_from_geofence'])) {
    $distance_from_geofence = floatval($_POST['distance_from_geofence']);
}

// Get device info
$device_info = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
$ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

// Handle photo upload if present
$photo_path = null;
if (isset($_POST['photo']) && !empty($_POST['photo'])) {
    // The photo is sent as a base64 data URL
    $photo_data = $_POST['photo'];
    
    // Extract the base64 encoded binary data
    $image_parts = explode(";base64,", $photo_data);
    $image_base64 = isset($image_parts[1]) ? $image_parts[1] : $photo_data;
    
    // Decode the data
    $image_data = base64_decode($image_base64);
    
    // Generate a unique filename with timestamp
    $microtime = microtime(true);
    $milliseconds = sprintf("%07d", ($microtime - floor($microtime)) * 10000000);
    $timestamp = date('Ymd_His', $microtime) . substr($milliseconds, 0, 4);
    
    $filename = $user_id . '_' . $timestamp . '.jpeg';
    $upload_dir = '../uploads/attendance/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $photo_path = $upload_dir . $filename;
    
    // Save the image
    if (file_put_contents($photo_path, $image_data)) {
        // Make the path relative for database storage
        $photo_path = 'uploads/attendance/' . $filename;
    } else {
        $photo_path = null;
    }
}

// Prepare response
$response = [
    'status' => 'error',
    'message' => 'Failed to process Come In'
];

try {
    // Check if user already has a completed attendance record for today
    $check_completed_query = "SELECT id FROM attendance WHERE user_id = ? AND date = ? AND punch_in IS NOT NULL AND punch_out IS NOT NULL";
    $stmt = $conn->prepare($check_completed_query);
    $stmt->bind_param('is', $user_id, $current_date);
    $stmt->execute();
    $completed_result = $stmt->get_result();
    
    if ($completed_result->num_rows > 0) {
        $response['message'] = 'You have already completed your attendance for today. You cannot punch in or out again today.';
        echo json_encode($response);
        exit;
    }

    // Now check if user has an open punch-in record
    $check_query = "SELECT id FROM attendance WHERE user_id = ? AND date = ? AND punch_in IS NOT NULL AND punch_out IS NULL";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('is', $user_id, $current_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get geofence ID if available
    $geofence_id = 0;
    if (!empty($closest_location)) {
        $geofence_query = "SELECT id FROM geofence_locations WHERE name = ? LIMIT 1";
        $stmt_geo = $conn->prepare($geofence_query);
        $stmt_geo->bind_param('s', $closest_location);
        $stmt_geo->execute();
        $geo_result = $stmt_geo->get_result();
        if ($geo_result->num_rows > 0) {
            $geo_row = $geo_result->fetch_assoc();
            $geofence_id = $geo_row['id'];
        }
        $stmt_geo->close();
    }
    
    if ($result->num_rows > 0) {
        // User already has an open attendance record, this is likely a duplicate request
        $response['message'] = 'You have already punched in today. Please use the Punch Out feature when your shift ends.';
        $response['status'] = 'error';
    } else {
        // New punch in for today
        $insert_query = "INSERT INTO attendance (
            user_id, 
            date, 
            punch_in, 
            punch_in_photo, 
            punch_in_latitude, 
            punch_in_longitude, 
            punch_in_accuracy,
            address,
            within_geofence,
            distance_from_geofence,
            ip_address,
            device_info,
            created_at,
            punch_in_outside_reason,
            geofence_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
        
        $within_geofence_int = $is_within_geofence ? 1 : 0;
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('isssddisdssssi', 
            $user_id, 
            $current_date, 
            $current_time, 
            $photo_path, 
            $latitude, 
            $longitude, 
            $accuracy,
            $address,
            $within_geofence_int,
            $distance_from_geofence,
            $ip_address,
            $device_info,
            $outside_location_reason,
            $geofence_id
        );
        
        if ($stmt->execute()) {
            // Log activity
            $activity_type = $is_within_geofence ? 'Punched in' : 'Punched in outside geofence';
            $activity_details = $is_within_geofence 
                ? "User punched in at {$closest_location}" 
                : "User punched in outside geofence. Reason: {$outside_location_reason}";
            
            logActivity($user_id, $activity_type, $activity_details);
            
            $response['status'] = 'success';
            $response['message'] = 'Come In recorded successfully';
        } else {
            $response['message'] = 'Failed to record attendance: ' . $stmt->error;
        }
    }
    
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?> 