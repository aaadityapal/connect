<?php
session_start();
require_once 'config/db_connect.php';
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json');

// Function to get user's IP address
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Function to get device info
function getDeviceInfo() {
    return $_SERVER['HTTP_USER_AGENT'];
}

// Function to calculate working hours and overtime
function calculateWorkingHours($punch_in, $punch_out, $shift_end_time) {
    $in_time = strtotime($punch_in);
    $out_time = strtotime($punch_out);
    
    // Get today's date
    $today = date('Y-m-d');
    
    // Convert shift end time to full datetime for today
    $shift_end = strtotime($today . ' ' . $shift_end_time);
    
    // Calculate total working duration
    $total_seconds = max(0, $out_time - $in_time);
    
    // Check if punch in was after shift end
    if ($in_time > $shift_end) {
        // All time is overtime when punched in after shift end
        $regular_seconds = 0;
        $overtime_seconds = $total_seconds;
    } else {
        // Calculate regular hours (capped at shift end)
        $regular_seconds = min($total_seconds, max(0, $shift_end - $in_time));
        // Calculate overtime only if punched out after shift end
        $overtime_seconds = ($out_time > $shift_end) ? ($out_time - max($shift_end, $in_time)) : 0;
    }
    
    // Format regular hours
    $regular_hours = floor($regular_seconds / 3600);
    $regular_minutes = floor(($regular_seconds % 3600) / 60);
    $regular_seconds = $regular_seconds % 60;
    
    // Format overtime
    $overtime_hours = floor($overtime_seconds / 3600);
    $overtime_minutes = floor(($overtime_seconds % 3600) / 60);
    $overtime_seconds = $overtime_seconds % 60;
    
    return [
        'total_time' => sprintf("%02d:%02d:%02d", 
            floor($total_seconds / 3600),
            floor(($total_seconds % 3600) / 60),
            $total_seconds % 60
        ),
        'regular_time' => sprintf("%02d:%02d:%02d", 
            $regular_hours, 
            $regular_minutes, 
            $regular_seconds
        ),
        'overtime' => sprintf("%02d:%02d:%02d", 
            $overtime_hours, 
            $overtime_minutes, 
            $overtime_seconds
        ),
        'has_overtime' => $overtime_seconds > 0
    ];
}

// Function to get user's current shift and weekly offs
function getUserShiftDetails($conn, $user_id) {
    $current_date = date('Y-m-d');
    
    $query = "SELECT us.*, s.shift_name, s.start_time, s.end_time 
              FROM user_shifts us
              JOIN shifts s ON us.shift_id = s.id
              WHERE us.user_id = ? 
              AND us.effective_from <= ?
              AND (us.effective_to >= ? OR us.effective_to IS NULL)
              ORDER BY us.effective_from DESC 
              LIMIT 1";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $current_date, $current_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $shift_data = $result->fetch_assoc();
        return [
            'shift_name' => $shift_data['shift_name'],
            'start_time' => $shift_data['start_time'],
            'end_time' => $shift_data['end_time'],
            'weekly_offs' => $shift_data['weekly_offs']
        ];
    }
    
    // Return default shift if no specific shift is assigned
    return [
        'shift_name' => 'Default Shift',
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'weekly_offs' => 'Saturday,Sunday'
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check_status') {
    $user_id = $_SESSION['user_id'];
    $current_date = date('Y-m-d');
    
    try {
        // Check if user has punched in today
        $query = "SELECT punch_in FROM attendance 
                 WHERE user_id = ? AND date = ? AND punch_out IS NULL";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $user_id, $current_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode([
                'is_punched_in' => true,
                'punch_time' => $row['punch_in']
            ]);
        } else {
            echo json_encode([
                'is_punched_in' => false,
                'punch_time' => null
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'is_punched_in' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $_SESSION['user_id'];
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    $current_datetime = date('Y-m-d H:i:s');
    $ip_address = getUserIP();
    $device_info = getDeviceInfo();
    $status = 'present';
    $created_at = $current_datetime;
    
    // Get user's shift details
    $shift_details = getUserShiftDetails($conn, $user_id);
    
    try {
        if ($data['action'] === 'punch_in') {
            // Check if it's a weekly off
            $current_day = date('l'); // Gets the current day name
            $weekly_offs = explode(',', $shift_details['weekly_offs']);
            
            // Set is_weekly_off flag
            $is_weekly_off = in_array($current_day, $weekly_offs) ? 1 : 0;
            
            // Check if already punched in today
            $check_query = "SELECT id FROM attendance WHERE user_id = ? AND date = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("is", $user_id, $current_date);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Already punched in for today']);
                exit;
            }

            // Get location string from coordinates if provided
            $location = "";
            $latitude = null;
            $longitude = null;
            $accuracy = null;
            $image_file_path = null;  // Initialize to ensure it's always defined
            
            if (isset($data['latitude']) && isset($data['longitude'])) {
                $latitude = $data['latitude'];
                $longitude = $data['longitude'];
                
                try {
                    // Form a basic location string from coordinates
                    $location = "Lat: $latitude, Long: $longitude";
                    
                    // Optional: Use a geocoding service to get a readable address
                    // This requires an API key and external service, so using coordinates as fallback
                    // $geocode_url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=$latitude,$longitude&key=YOUR_API_KEY";
                    // $geocode_data = json_decode(file_get_contents($geocode_url), true);
                    // if ($geocode_data['status'] == 'OK') {
                    //     $location = $geocode_data['results'][0]['formatted_address'];
                    // }
                } catch (Exception $e) {
                    // If geocoding fails, just use the coordinates
                    error_log("Geocoding failed: " . $e->getMessage());
                }
                
                // Get accuracy if available
                if (isset($data['accuracy'])) {
                    $accuracy = $data['accuracy'];
                }
            }
            
            // Handle selfie image if available
            if (isset($data['punch_in_photo']) && !empty($data['punch_in_photo'])) {
                // The image comes as base64 data URL, need to extract just the base64 string
                $image_base64 = '';
                
                if (strpos($data['punch_in_photo'], 'data:image') !== false) {
                    // Extract the base64 part and image type from the data URL
                    $image_parts = explode(',', $data['punch_in_photo']);
                    $image_base64 = $image_parts[1];
                    
                    // Get image type (jpg, png) from the data URL
                    preg_match('/data:image\/(.*);base64/', $data['punch_in_photo'], $matches);
                    $image_type = $matches[1] ?? 'jpeg'; // Default to jpeg if can't determine
                    
                    // Create unique filename - user_id_date_timestamp.jpg
                    $filename = $user_id . '_' . date('Ymd') . '_' . time() . '.' . $image_type;
                    $upload_dir = 'uploads/attendance/';
                    
                    // Make sure the directory exists
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Save the decoded image to the uploads directory
                    $image_data = base64_decode($image_base64);
                    $file_path = $upload_dir . $filename;
                    
                    if (file_put_contents($file_path, $image_data)) {
                        // Use the relative file path to store in database
                        $image_file_path = $file_path;
                    } else {
                        // Log error but continue
                        error_log("Failed to save attendance photo to: " . $file_path);
                    }
                } else {
                    $image_base64 = $data['punch_in_photo'];
                    
                    // Similar process for non-data URLs
                    $filename = $user_id . '_' . date('Ymd') . '_' . time() . '.jpg';
                    $upload_dir = 'uploads/attendance/';
                    
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $image_data = base64_decode($image_base64);
                    $file_path = $upload_dir . $filename;
                    
                    if (file_put_contents($file_path, $image_data)) {
                        $image_file_path = $file_path;
                    } else {
                        error_log("Failed to save attendance photo to: " . $file_path);
                    }
                }
            }
            
            // Get device info from request if available, otherwise use server function
            if (isset($data['device_info']) && !empty($data['device_info'])) {
                $device_info = $data['device_info'];
            }

            // Insert new attendance record
            $query = "INSERT INTO attendance (
                user_id, 
                date, 
                punch_in, 
                location, 
                ip_address, 
                device_info, 
                status, 
                created_at, 
                shift_time, 
                weekly_offs, 
                auto_punch_out,
                is_weekly_off,
                punch_in_photo,
                latitude,
                longitude,
                accuracy
            ) VALUES (
                ?, 
                ?, 
                ?, 
                ?, 
                ?, 
                ?, 
                ?, 
                ?, 
                ?, 
                ?, 
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )";
            
            $stmt = $conn->prepare($query);
            $shift_time = $shift_details['start_time'] . '-' . $shift_details['end_time'];
            $weekly_offs = $shift_details['weekly_offs'];
            $auto_punch_out = $shift_details['end_time'];
            
            // Log the punch in time to debug the issue
            error_log("Punch In Debug - Current Time: " . $current_time);
            
            $stmt->bind_param("issssssssssisddd", 
                $user_id, 
                $current_date, 
                $current_time,
                $location, 
                $ip_address, 
                $device_info, 
                $status, 
                $created_at,
                $shift_time,
                $weekly_offs,
                $auto_punch_out,
                $is_weekly_off,
                $image_file_path,
                $latitude,
                $longitude,
                $accuracy
            );
            
            try {
                if (!$stmt->execute()) {
                    $error_message = "MySQL Error: " . $stmt->error . " (Code: " . $stmt->errno . ")";
                    error_log($error_message);
                    echo json_encode([
                        'success' => false,
                        'message' => $error_message,
                        'query' => $query 
                    ]);
                    exit;
                }
                
                // Format response message
                $message = 'Punched in successfully at ' . date('h:i:s A', strtotime($current_time));
                if ($is_weekly_off) {
                    $message .= ' (Working on Weekly Off)';
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => $message,
                    'punch_time' => $current_time,
                    'shift_time' => "Shift: " . $shift_details['shift_name'] . " (" . 
                                  date('h:i A', strtotime($shift_details['start_time'])) . 
                                  " - " . date('h:i A', strtotime($shift_details['end_time'])) . ")"
                ]);
            } catch (Exception $e) {
                error_log("Punch In Error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } elseif ($data['action'] === 'punch_out') {
            // Validate work report
            if (!isset($data['work_report']) || empty(trim($data['work_report']))) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Work report is required for punch out'
                ]);
                exit;
            }

            // Initialize variables
            $punch_out_photo = null;
            $latitude = null;
            $longitude = null;
            $accuracy = null;

            // Get location data if provided
            if (isset($data['latitude']) && isset($data['longitude'])) {
                $latitude = $data['latitude'];
                $longitude = $data['longitude'];
                if (isset($data['accuracy'])) {
                    $accuracy = $data['accuracy'];
                }
            }

            // Handle punch out photo if available
            if (isset($data['punch_out_photo']) && !empty($data['punch_out_photo'])) {
                // Log the incoming punch_out_photo data
                error_log("Received punch_out_photo data. Length: " . strlen($data['punch_out_photo']));
                
                // The image comes as base64 data URL, need to extract just the base64 string
                $image_base64 = '';
                
                if (strpos($data['punch_out_photo'], 'data:image') !== false) {
                    // Extract the base64 part and image type from the data URL
                    $image_parts = explode(',', $data['punch_out_photo']);
                    $image_base64 = $image_parts[1];
                    
                    // Get image type (jpg, png) from the data URL
                    preg_match('/data:image\/(.*);base64/', $data['punch_out_photo'], $matches);
                    $image_type = $matches[1] ?? 'jpeg'; // Default to jpeg if can't determine
                    
                    // Create unique filename - user_id_date_timestamp_out.jpg
                    $filename = $user_id . '_' . date('Ymd') . '_' . time() . '_out.' . $image_type;
                    $upload_dir = 'uploads/attendance/';
                    
                    // Make sure the directory exists
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                        error_log("Created directory: " . $upload_dir);
                    }
                    
                    // Save the decoded image to the uploads directory
                    $image_data = base64_decode($image_base64);
                    $file_path = $upload_dir . $filename;
                    
                    if (file_put_contents($file_path, $image_data)) {
                        // Use the relative file path to store in database
                        $punch_out_photo = $file_path;
                        error_log("Successfully saved punch out photo to: " . $file_path);
                    } else {
                        // Log error but continue
                        error_log("Failed to save punch out photo to: " . $file_path);
                    }
                } else {
                    $image_base64 = $data['punch_out_photo'];
                    
                    // Similar process for non-data URLs
                    $filename = $user_id . '_' . date('Ymd') . '_' . time() . '_out.jpg';
                    $upload_dir = 'uploads/attendance/';
                    
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                        error_log("Created directory: " . $upload_dir);
                    }
                    
                    $image_data = base64_decode($image_base64);
                    $file_path = $upload_dir . $filename;
                    
                    if (file_put_contents($file_path, $image_data)) {
                        $punch_out_photo = $file_path;
                        error_log("Successfully saved punch out photo to: " . $file_path);
                    } else {
                        error_log("Failed to save punch out photo to: " . $file_path);
                    }
                }
                
                error_log("Final punch_out_photo value: " . ($punch_out_photo ?: "NULL"));
            } else {
                error_log("No punch_out_photo data received in request");
            }

            // First check if a punch-in record exists and hasn't been punched out
            $check_record = "SELECT id, punch_out, auto_punch_out FROM attendance WHERE user_id = ? AND date = ?";
            $check_stmt = $conn->prepare($check_record);
            $check_stmt->bind_param("is", $user_id, $current_date);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $attendance = $check_result->fetch_assoc();
            
            if (!$attendance) {
                $error_message = "No punch-in record found to update. Make sure you punched in first.";
                error_log($error_message);
                echo json_encode([
                    'success' => false,
                    'message' => $error_message
                ]);
                exit;
            }
            
            // Check if already punched out
            if ($attendance['punch_out'] !== null) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You have already punched out for today.',
                ]);
                exit;
            }
            
            // Check if already auto punched out
            if ($attendance['auto_punch_out'] == 1) {
                echo json_encode([
                    'success' => false,
                    'message' => 'System has already recorded your punch out at shift end time due to missing punch out.',
                    'auto_punch_out' => true,
                    'punch_out_time' => date('h:i A', strtotime($attendance['punch_out']))
                ]);
                exit;
            }

            // Get punch in time and shift details
            $get_details = "SELECT a.punch_in, s.end_time 
                            FROM attendance a 
                            JOIN user_shifts us ON a.user_id = us.user_id 
                            JOIN shifts s ON us.shift_id = s.id 
                            WHERE a.user_id = ? AND a.date = ? AND a.punch_out IS NULL";
            $stmt_details = $conn->prepare($get_details);
            $stmt_details->bind_param("is", $user_id, $current_date);
            $stmt_details->execute();
            $result = $stmt_details->get_result();
            $row = $result->fetch_assoc();
            
            if (!$row) {
                throw new Exception("No punch-in record found for today");
            }
            
            $punch_in_time = $row['punch_in'];
            $shift_end_time = $row['end_time'];
            
            // Calculate working hours and overtime
            $time_details = calculateWorkingHours($punch_in_time, $current_time, $shift_end_time);
            
            // Update attendance record with punch out time, working hours, overtime and work report
            $query = "UPDATE attendance SET punch_out = ?, working_hours = ?, overtime_hours = ?, work_report = ?, modified_at = ?, modified_by = ?, punch_out_photo = ?, punch_out_latitude = ?, punch_out_longitude = ?, punch_out_accuracy = ? WHERE user_id = ? AND date = ? AND punch_out IS NULL";
            
            $stmt = $conn->prepare($query);
            $modified_at = $current_datetime;
            $work_report = trim($data['work_report']);
            
            // Get location data if provided
            $punch_out_latitude = isset($data['latitude']) ? $data['latitude'] : null;
            $punch_out_longitude = isset($data['longitude']) ? $data['longitude'] : null;
            $punch_out_accuracy = isset($data['accuracy']) ? $data['accuracy'] : null;
            
            // Add debugging information
            error_log("Punch Out Debug - Query: " . $query);
            error_log("Punch Out Debug - Current Time: " . $current_time);
            error_log("Punch Out Debug - Work Report: " . substr($work_report, 0, 50) . "...");
            error_log("Punch Out Debug - Photo Path: " . ($punch_out_photo ? $punch_out_photo : "None"));
            error_log("Punch Out Debug - Location: Lat: " . ($punch_out_latitude ?? 'NULL') . ", Long: " . ($punch_out_longitude ?? 'NULL') . ", Accuracy: " . ($punch_out_accuracy ?? 'NULL'));
            error_log("Punch Out Debug - Values: current_time=" . $current_time . 
                      ", total_time=" . $time_details['total_time'] . 
                      ", overtime=" . $time_details['overtime'] . 
                      ", modified_at=" . $modified_at . 
                      ", user_id=" . $user_id . 
                      ", current_date=" . $current_date);
            
            // Fix any potential issues with data types and make sure all parameters are properly set
            $current_time = $current_time;
            $total_time = $time_details['total_time'];
            $overtime = $time_details['overtime'];
            $work_report = $work_report;
            $modified_by = $user_id;
            $photo_path = $punch_out_photo ?: null;
            
            // Make sure we have correct parameter types for bind_param
            // s=string, i=integer, d=double, b=blob
            try {
                $stmt->bind_param("sssssisdddis", 
                    $current_time,
                    $total_time,
                    $overtime,
                    $work_report,
                    $modified_at,
                    $modified_by,
                    $photo_path,
                    $punch_out_latitude,
                    $punch_out_longitude,
                    $punch_out_accuracy,
                    $user_id,
                    $current_date
                );
                
                error_log("Punch Out Debug - After bind_param setup");
            } catch (Exception $bind_error) {
                error_log("Bind Param Error: " . $bind_error->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error preparing database query: ' . $bind_error->getMessage()
                ]);
                exit;
            }
            
            try {
                $result = $stmt->execute();
                error_log("Execute result: " . ($result ? "Success" : "Failure"));
                
                if (!$result) {
                    $error_message = "MySQL Error: " . $stmt->error . " (Code: " . $stmt->errno . ")";
                    error_log($error_message);
                    echo json_encode([
                        'success' => false,
                        'message' => $error_message,
                        'query' => $query 
                    ]);
                    exit;
                }
                
                // Check if punch-out location differs significantly from punch-in location
                $location_changed = false;
                $distance_km = 0;
                $location_message = '';
                
                // Get punch-in location from the database
                $get_punchin_loc = "SELECT latitude, longitude FROM attendance WHERE user_id = ? AND date = ?";
                $loc_stmt = $conn->prepare($get_punchin_loc);
                $loc_stmt->bind_param("is", $user_id, $current_date);
                $loc_stmt->execute();
                $loc_result = $loc_stmt->get_result();
                $punch_in_loc = $loc_result->fetch_assoc();
                
                if ($punch_in_loc && $punch_in_loc['latitude'] && $punch_in_loc['longitude'] && 
                    $punch_out_latitude && $punch_out_longitude) {
                    
                    // Calculate distance between punch-in and punch-out locations using Haversine formula
                    $earth_radius = 6371; // Radius of the earth in km
                    $lat_diff = deg2rad($punch_out_latitude - $punch_in_loc['latitude']);
                    $lon_diff = deg2rad($punch_out_longitude - $punch_in_loc['longitude']);
                    
                    $a = sin($lat_diff/2) * sin($lat_diff/2) +
                         cos(deg2rad($punch_in_loc['latitude'])) * cos(deg2rad($punch_out_latitude)) * 
                         sin($lon_diff/2) * sin($lon_diff/2);
                    
                    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                    $distance_km = $earth_radius * $c; // Distance in km
                    
                    // Consider significant location change if distance is more than 100 meters
                    if ($distance_km > 0.1) {
                        $location_changed = true;
                        $location_message = sprintf(
                            "Location change detected. Distance: %.1f km from punch-in location.", 
                            $distance_km
                        );
                        error_log("Location difference detected: " . $location_message);
                    }
                }
                
                // Format response message
                $message = 'Punched out successfully at ' . date('h:i:s A', strtotime($current_time));
                $time_message = sprintf(
                    "Regular hours: %s\n%s",
                    $time_details['regular_time'],
                    $time_details['has_overtime'] ? "Overtime: " . $time_details['overtime'] : ""
                );
                
                echo json_encode([
                    'success' => true, 
                    'message' => $message,
                    'punch_time' => $current_time,
                    'working_hours' => $time_message,
                    'has_overtime' => $time_details['has_overtime'],
                    'work_report' => $work_report,
                    'location_changed' => $location_changed,
                    'location_distance' => $distance_km,
                    'location_message' => $location_message
                ]);
            } catch (Exception $e) {
                error_log("Punch Out Error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        }
        
    } catch (Exception $e) {
        error_log("Punch Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?> 