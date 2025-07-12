<?php
// Include necessary files
require_once '../config/db_connect.php';
require_once '../includes/auth_check.php';
require_once '../includes/activity_logger.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Initialize response
$response = array(
    'success' => false,
    'message' => 'An error occurred'
);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not authenticated';
    echo json_encode($response);
    exit;
}

// Get current date and time in IST
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

// If client sent their time, use it to verify (helps with timezone issues)
if (isset($_POST['client_time']) && !empty($_POST['client_time'])) {
    $client_time = new DateTime($_POST['client_time']);
    $current_date = $client_time->format('Y-m-d');
    $current_time = $client_time->format('H:i:s');
}

$user_id = $_SESSION['user_id'];

// Get form data
$latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0;
$within_geofence = isset($_POST['within_geofence']) ? intval($_POST['within_geofence']) : 0;
$distance_from_geofence = isset($_POST['distance_from_geofence']) ? floatval($_POST['distance_from_geofence']) : 0;
$address = isset($_POST['address']) ? $_POST['address'] : '';
$ip_address = isset($_POST['ip_address']) ? $_POST['ip_address'] : '';
$device_info = isset($_POST['device_info']) ? $_POST['device_info'] : '';
$geofence_id = isset($_POST['geofence_id']) ? intval($_POST['geofence_id']) : 0;

// Add outside location reason if provided
$punch_in_outside_reason = null;
$punch_out_outside_reason = null;
if (isset($_POST['outside_location_reason']) && !empty($_POST['outside_location_reason'])) {
    // Determine if this is punch in or punch out
    $check_query = "SELECT id FROM attendance WHERE user_id = ? AND date = ? AND punch_in IS NOT NULL AND punch_out IS NULL";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("is", $user_id, $current_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // This is a punch out
        $punch_out_outside_reason = $_POST['outside_location_reason'];
    } else {
        // This is a punch in
        $punch_in_outside_reason = $_POST['outside_location_reason'];
    }
}

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
    $check_query = "SELECT id, punch_in FROM attendance WHERE user_id = ? AND date = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('is', $user_id, $current_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Process image upload
    $image_data = null;
    $image_path_for_db = null;
    if (isset($_POST['photo']) && !empty($_POST['photo'])) {
        // Remove the data URL prefix
        $image_data = $_POST['photo'];
        if (strpos($image_data, 'data:image/jpeg;base64,') === 0) {
            $image_data = str_replace('data:image/jpeg;base64,', '', $image_data);
        }
        
        // Decode the base64 image
        $image_data = base64_decode($image_data);
        
        // Generate filename in the format: user_id_YYYYMMDD_HHMMSSSSSS.jpeg
        // Get current timestamp with milliseconds
        $microtime = microtime(true);
        $milliseconds = sprintf("%07d", ($microtime - floor($microtime)) * 10000000);
        $timestamp = date('Ymd_His', $microtime) . substr($milliseconds, 0, 4);
        
        $image_filename = $user_id . '_' . $timestamp . '.jpeg';
        $physical_path = '../uploads/attendance/' . $image_filename;
        $image_path_for_db = 'uploads/attendance/' . $image_filename;
        
        // Ensure directory exists
        if (!file_exists('../uploads/attendance/')) {
            mkdir('../uploads/attendance/', 0755, true);
        }
        
        // Save the image
        file_put_contents($physical_path, $image_data);
    }
    
    // Get work report if provided (for punch out)
    $work_report = isset($_POST['work_report']) ? $_POST['work_report'] : null;
    
    if ($result->num_rows > 0) {
        // User already punched in today, update punch out
        $row = $result->fetch_assoc();
        
        // Check if already punched out
        if (!empty($row['punch_out'])) {
            $response['message'] = 'You have already completed your attendance for today';
            echo json_encode($response);
            exit;
        }
        
        // Get punch in time
        $punch_in_time = new DateTime($row['punch_in']);
        $punch_out_time = new DateTime($current_time);
        
        // Calculate working hours
        $working_hours = $punch_out_time->diff($punch_in_time);
        $working_hours_formatted = sprintf(
            '%02d:%02d:%02d',
            $working_hours->h + ($working_hours->days * 24),
            $working_hours->i,
            $working_hours->s
        );
        
        // Get user's shift information to calculate overtime
        $overtime_hours = '00:00:00';
        $shift_query = "
            SELECT s.id, s.shift_name, s.start_time, s.end_time 
            FROM shifts s
            JOIN user_shifts us ON s.id = us.shift_id
            WHERE us.user_id = ?
            AND ? BETWEEN us.effective_from AND IFNULL(us.effective_to, '9999-12-31')
            LIMIT 1
        ";
        
        $stmt_shift = $conn->prepare($shift_query);
        if ($stmt_shift) {
            $stmt_shift->bind_param("is", $user_id, $current_date);
            $stmt_shift->execute();
            $shift_result = $stmt_shift->get_result();
            
            if ($shift_result->num_rows > 0) {
                $shift_info = $shift_result->fetch_assoc();
                
                // Calculate expected work hours based on shift
                $shift_start = new DateTime($shift_info['start_time']);
                $shift_end = new DateTime($shift_info['end_time']);
                
                // Handle midnight case (00:00:00)
                if ($shift_end->format('H:i:s') === '00:00:00') {
                    $shift_end = new DateTime('23:59:59');
                    $shift_end->modify('+1 second'); // Make it 00:00:00 of next day
                }
                
                // Calculate expected shift duration
                if ($shift_start > $shift_end) {
                    // Overnight shift
                    $shift_end->modify('+1 day');
                }
                
                $shift_duration = $shift_start->diff($shift_end);
                $shift_duration_seconds = $shift_duration->h * 3600 + $shift_duration->i * 60 + $shift_duration->s;
                
                // Calculate actual work duration in seconds
                $work_duration_seconds = $working_hours->h * 3600 + $working_hours->i * 60 + $working_hours->s;
                $work_duration_seconds += $working_hours->days * 24 * 3600;
                
                // Calculate overtime in seconds
                $overtime_seconds = max(0, $work_duration_seconds - $shift_duration_seconds);
                
                // Only record overtime if it's at least 1 hour and 30 minutes (5400 seconds)
                if ($overtime_seconds >= 5400) {
                    $overtime_hours = sprintf(
                        '%02d:%02d:%02d',
                        floor($overtime_seconds / 3600),
                        floor(($overtime_seconds % 3600) / 60),
                        $overtime_seconds % 60
                    );
                }
            }
            
            $stmt_shift->close();
        }
        
        // Update punch out
        $update_query = "UPDATE attendance SET 
            punch_out = ?,
            punch_out_photo = ?,
            punch_out_latitude = ?,
            punch_out_longitude = ?,
            punch_out_address = ?,
            modified_at = NOW(),
            working_hours = ?,
            punch_out_outside_reason = ?,
            work_report = ?,
            overtime_hours = ?
            WHERE id = ?";
            
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ssddsssssi', 
            $current_time, 
            $image_path_for_db, 
            $latitude, 
            $longitude, 
            $address,
            $working_hours_formatted,
            $punch_out_outside_reason,
            $work_report,
            $overtime_hours,
            $row['id']
        );
        
        if ($stmt->execute()) {
            // Log activity
            logActivity($user_id, 'Punched out', 'Attendance');
            
            $response['success'] = true;
            $response['message'] = 'Punch out recorded successfully';
        } else {
            $response['message'] = 'Failed to record punch out: ' . $stmt->error;
        }
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
        
        $accuracy = 0; // Default value if not provided
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('isssddisdssssi', 
            $user_id, 
            $current_date, 
            $current_time, 
            $image_path_for_db, 
            $latitude, 
            $longitude, 
            $accuracy,
            $address,
            $within_geofence,
            $distance_from_geofence,
            $ip_address,
            $device_info,
            $punch_in_outside_reason,
            $geofence_id
        );
        
        if ($stmt->execute()) {
            // Log activity
            logActivity($user_id, 'Punched in', 'Attendance');
            
            $response['success'] = true;
            $response['message'] = 'Punch in recorded successfully';
        } else {
            $response['message'] = 'Failed to record punch in: ' . $stmt->error;
        }
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Return response
echo json_encode($response);
?> 