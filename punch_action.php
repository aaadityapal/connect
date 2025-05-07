<?php
// Use output buffering to ensure clean output
ob_start();

// Start session
session_start();

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Prepare the response
$response = ['status' => 'error', 'message' => 'Unknown error occurred'];

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }

    // Get database connection
    require_once('includes/db_connect.php');
    // $conn is the mysqli connection variable from db_connect.php

    // Check if data was received
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get action type (in/out)
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action !== 'in' && $action !== 'out') {
        throw new Exception('Invalid action');
    }
    
    // Get user ID from session
    $user_id = $_SESSION['user_id'];
    
    // Get current date and time in IST
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    $timestamp = date('Y-m-d H:i:s');
    
    // *** EARLY CHECK: Check if user has already punched in/out today before doing any other operations ***
    // This prevents unnecessary database operations and photo processing
    
    // Update session state from database first to ensure accuracy
    $status_check_sql = "SELECT id, punch_in, punch_out FROM attendance WHERE user_id = ? AND date = ? LIMIT 1";
    $status_check_stmt = $conn->prepare($status_check_sql);
    $status_check_stmt->bind_param('is', $user_id, $current_date);
    $status_check_stmt->execute();
    $status_check_result = $status_check_stmt->get_result();
    
    if ($status_check_result && $status_check_result->num_rows > 0) {
        $status_row = $status_check_result->fetch_assoc();
        
        // If punch_out is NULL, user is punched in
        if ($status_row['punch_out'] === NULL) {
            $_SESSION['punched_in'] = true;
            $_SESSION['attendance_completed'] = false;
            $_SESSION['punch_in_time'] = $current_date . ' ' . $status_row['punch_in'];
            
            // If trying to punch in again, stop here and return error
            if ($action === 'in') {
                throw new Exception('You are already punched in for today. Please refresh the page if this is an error.');
            }
        } else {
            // User has already completed attendance for today
            $_SESSION['punched_in'] = false;
            $_SESSION['attendance_completed'] = true;
            $_SESSION['punch_out_time'] = $current_date . ' ' . $status_row['punch_out'];
            
            // If trying to punch in OR punch out again, stop here and return error
            if ($action === 'in') {
                throw new Exception('You have already completed your attendance for today. Only one attendance record per day is allowed.');
            } else if ($action === 'out') {
                throw new Exception('You have already punched out for today. Only one attendance record per day is allowed.');
            }
        }
    } else {
        // No attendance record found
        $_SESSION['punched_in'] = false;
        $_SESSION['attendance_completed'] = false;
        
        // If trying to punch out without punching in, stop here
        if ($action === 'out') {
            throw new Exception('You need to punch in first before punching out.');
        }
    }
    
    $_SESSION['punched_status_checked'] = $current_date;
    $status_check_stmt->close();
    
    // Now we know the operation is valid, continue with the rest of the processing
    
    // Get IP address
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Get device info
    $device_info = isset($_POST['device_info']) ? $_POST['device_info'] : '';
    
    // Get location data
    $latitude = isset($_POST['latitude']) ? $_POST['latitude'] : 0;
    $longitude = isset($_POST['longitude']) ? $_POST['longitude'] : 0;
    $accuracy = isset($_POST['accuracy']) ? $_POST['accuracy'] : 0;
    
    // Get address information
    $address = isset($_POST['address']) ? $_POST['address'] : 'Not available';
    
    // Get user's shift information
    $shift_time = null;
    $shift_id = null;
    
    // Get the current day of the week (1 = Monday, 7 = Sunday)
    $day_of_week = date('N');
    
    // Convert to match your shift days (assuming shifts table uses 1=Sunday, 2=Monday, etc.)
    $shift_day_mapping = [
        1 => 2, // Monday
        2 => 3, // Tuesday
        3 => 4, // Wednesday
        4 => 5, // Thursday
        5 => 6, // Friday
        6 => 7, // Saturday
        7 => 1  // Sunday
    ];
    
    $shift_day = $shift_day_mapping[$day_of_week];
    
    // Find the user's active shift assignment for today
    $shift_query = "
        SELECT us.shift_id, s.start_time AS shift_time
        FROM user_shifts us
        JOIN shifts s ON us.shift_id = s.id
        WHERE us.user_id = ? 
        AND (
            (us.effective_from <= ? AND (us.effective_to >= ? OR us.effective_to IS NULL))
            OR
            (us.effective_from <= ? AND us.effective_to IS NULL)
        )
        AND (? IN (1,2,3,4,5,6,7) OR s.id IS NULL)
        ORDER BY us.created_at DESC
        LIMIT 1
    ";
    
    $shift_stmt = $conn->prepare($shift_query);
    if (!$shift_stmt) {
        error_log("Shift query prepare failed: " . $conn->error);
    } else {
        $shift_stmt->bind_param('isssi', $user_id, $current_date, $current_date, $current_date, $shift_day);
        $shift_stmt->execute();
        $shift_result = $shift_stmt->get_result();
        
        if ($shift_result && $shift_result->num_rows > 0) {
            $shift_data = $shift_result->fetch_assoc();
            $shift_id = $shift_data['shift_id'];
            $shift_time = $shift_data['shift_time'];
        }
        
        $shift_stmt->close();
    }
    
    // If no shift found, try to find any default shift assigned to the user
    if (!$shift_time) {
        $default_shift_query = "
            SELECT us.shift_id, s.start_time AS shift_time
            FROM user_shifts us
            JOIN shifts s ON us.shift_id = s.id
            WHERE us.user_id = ? 
            AND (
                (us.effective_from <= ? AND (us.effective_to >= ? OR us.effective_to IS NULL))
                OR
                (us.effective_from <= ? AND us.effective_to IS NULL)
            )
            ORDER BY us.created_at DESC
            LIMIT 1
        ";
        
        $default_shift_stmt = $conn->prepare($default_shift_query);
        if ($default_shift_stmt) {
            $default_shift_stmt->bind_param('isss', $user_id, $current_date, $current_date, $current_date);
            $default_shift_stmt->execute();
            $default_shift_result = $default_shift_stmt->get_result();
            
            if ($default_shift_result && $default_shift_result->num_rows > 0) {
                $default_shift_data = $default_shift_result->fetch_assoc();
                $shift_id = $default_shift_data['shift_id'];
                $shift_time = $default_shift_data['shift_time'];
            }
            
            $default_shift_stmt->close();
        }
    }
    
    // Now process the photo data - only if we're definitely going to use it
    $photo_data = null;
    if (isset($_POST['photo']) && !empty($_POST['photo'])) {
        try {
            // Remove the URL header from the base64 string
            $photo_data = $_POST['photo'];
            
            // Basic validation of the base64 data
            if (strpos($photo_data, 'data:image/jpeg;base64,') === 0) {
                $photo_data = str_replace('data:image/jpeg;base64,', '', $photo_data);
            } elseif (strpos($photo_data, 'data:image/png;base64,') === 0) {
                $photo_data = str_replace('data:image/png;base64,', '', $photo_data);
            }
            
            // Validate that the photo data is valid base64
            $decoded = base64_decode($photo_data, true);
            if ($decoded === false) {
                throw new Exception("Invalid base64 encoding in photo data");
            }
            
            // Generate a filename based on user ID and timestamp
            $photo_filename = 'user_' . $user_id . '_' . date('Ymd_His') . '_' . $action . '.jpg';
            
            // Define the upload directory - make sure this directory exists and is writable
            $upload_dir = 'uploads/attendance/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }
            
            // Check if directory is writable
            if (!is_writable($upload_dir)) {
                throw new Exception("Upload directory is not writable");
            }
            
            // Save the image file
            $result = file_put_contents($upload_dir . $photo_filename, $decoded);
            if ($result === false) {
                throw new Exception("Failed to write photo file to disk");
            }
            
            $photo_data = $photo_filename;
            
        } catch (Exception $e) {
            // Log the error
            error_log("Photo handling error: " . $e->getMessage());
            
            // Set photo data to null and continue with the operation
            $photo_data = null;
            $response['photo_error'] = $e->getMessage();
        }
    }
    
    // Process based on action type
    if ($action === 'in') {
        // Insert punch in record - we've already verified this is allowed
        $sql = "INSERT INTO attendance (user_id, date, punch_in, ip_address, device_info, location, 
                latitude, longitude, accuracy, punch_in_photo, address, status, shifts_id, shift_time) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'present', ?, ?)";
        
        $location = "Lat: $latitude, Long: $longitude";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param('isssssddsssss', 
            $user_id, 
            $current_date, 
            $current_time, 
            $ip_address, 
            $device_info, 
            $location, 
            $latitude, 
            $longitude, 
            $accuracy, 
            $photo_data,
            $address,
            $shift_id,
            $shift_time
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        // Update session
        $_SESSION['punched_in'] = true;
        $_SESSION['attendance_completed'] = false;
        $_SESSION['punch_in_time'] = $timestamp;
        
        $response = [
            'status' => 'success',
            'message' => 'Punched in successfully',
            'time' => $timestamp,
            'photo' => $photo_data,
            'address' => $address,
            'shift_time' => $shift_time
        ];
        
        $stmt->close();
        
    } else {
        // Punch out logic - we've already verified this is allowed
        // Find the active punch-in record for today (which we know exists)
        $check_sql = "SELECT id, punch_in, shifts_id, shift_time FROM attendance WHERE user_id = ? AND date = ? AND punch_out IS NULL LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('is', $user_id, $current_date);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();
        
        $attendance_id = $row['id'];
        $punch_in_time = $row['punch_in'];
        
        // If no shift_time/shift_id was found earlier, use the one from the punch-in record
        if (!$shift_time && isset($row['shift_time'])) {
            $shift_time = $row['shift_time'];
        }
        if (!$shift_id && isset($row['shifts_id'])) {
            $shift_id = $row['shifts_id'];
        }
        
        // Calculate working hours
        $punch_in_timestamp = strtotime($current_date . ' ' . $punch_in_time);
        $punch_out_timestamp = strtotime($current_date . ' ' . $current_time);
        $seconds_worked = $punch_out_timestamp - $punch_in_timestamp;
        $hours_worked = gmdate('H:i:s', $seconds_worked);
        
        // Update the record with punch-out data
        $sql = "UPDATE attendance SET 
                punch_out = ?, 
                punch_out_photo = ?, 
                working_hours = ?, 
                punch_out_address = ?, 
                work_report = 'Punched out' 
                WHERE id = ?";
                
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param('ssssi', 
            $current_time, 
            $photo_data, 
            $hours_worked, 
            $address,
            $attendance_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        // Update session
        $_SESSION['punched_in'] = false;
        $_SESSION['attendance_completed'] = true;
        $_SESSION['punch_out_time'] = $timestamp;
        
        // Format hours worked for display
        $time_parts = explode(':', $hours_worked);
        $formatted_hours = sprintf(
            '%d hours, %d minutes, %d seconds',
            $time_parts[0],
            $time_parts[1],
            $time_parts[2]
        );
        
        $response = [
            'status' => 'success',
            'message' => 'Punched out successfully',
            'time' => $timestamp,
            'photo' => $photo_data,
            'hours_worked' => $formatted_hours,
            'address' => $address,
            'shift_time' => $shift_time
        ];
        
        $stmt->close();
    }
} catch (Exception $e) {
    // Error message will contain the appropriate information from the early validation
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    error_log("Punch action error: " . $e->getMessage());
}

// Make sure to clear everything in the output buffer
ob_end_clean();

// Ensure no accidental output happens after the buffer is cleaned
if (headers_sent($file, $line)) {
    error_log("Headers already sent in $file:$line - ensuring JSON response is clean");
}

// Return clean JSON response with proper headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Ensure properly encoded JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit; 