<?php
// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Register shutdown function to catch fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Clear any output that might have been sent
        if (ob_get_length())
            ob_clean();

        // Set proper header
        header('Content-Type: application/json; charset=utf-8');
        header('HTTP/1.1 200 OK'); // Override 500 status

        // Return error as JSON
        echo json_encode([
            'success' => false,
            'message' => 'Server error occurred',
            'error_details' => $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
        exit;
    }
});

// Set maximum execution time to prevent timeouts during image processing
ini_set('max_execution_time', 120); // 2 minutes
ini_set('memory_limit', '256M'); // Increase memory limit

// Start output buffering to catch any stray output
ob_start();

// Include necessary files
require_once '../config/db_connect.php';
require_once '../includes/auth_check.php';
require_once '../includes/activity_logger.php';
require_once '../includes/attendance_notification.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Initialize response
$response = array(
    'success' => false,
    'message' => 'An error occurred'
);

// Wrap everything in a try-catch block to handle exceptions
try {
    // Function to sanitize values for JSON
    function sanitizeForJson($value)
    {
        if (is_string($value)) {
            // Check if mbstring extension is available
            if (function_exists('mb_convert_encoding')) {
                // Remove invalid UTF-8 sequences
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            } else {
                // Fallback for servers without mbstring
                // Simple replacement of potentially problematic characters
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\xFF]/u', '', $value);

                // Try to ensure it's valid UTF-8
                if (!preg_match('//u', $value)) {
                    // If still invalid UTF-8, try a more aggressive approach
                    $value = utf8_encode($value);
                }
            }

            // Remove control characters
            $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        }
        return $value;
    }

    // Recursive function to sanitize array values
    function sanitizeArrayForJson($array)
    {
        // Handle null or non-array values
        if ($array === null || !is_array($array)) {
            return sanitizeForJson($array);
        }

        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = sanitizeArrayForJson($value);
            } else {
                $result[$key] = sanitizeForJson($value);
            }
        }

        return $result;
    }

    // Debug mode - set to true to include debug info in response
    $debug_mode = true;
    if ($debug_mode) {
        $response['debug'] = array(
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'timestamp' => date('Y-m-d H:i:s')
        );

        // Only store a small subset of POST data for debugging
        if (isset($_POST)) {
            $safe_post = $_POST;
            if (isset($safe_post['photo'])) {
                $safe_post['photo'] = '[BASE64_IMAGE_DATA_REMOVED]';
            }
            $response['debug']['post_data'] = $safe_post;
        }
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        $response['message'] = 'User not authenticated';
        throw new Exception('User not authenticated');
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

    // Check for both parameter naming conventions (within_geofence and is_within_geofence)
    $within_geofence = 0;
    if (isset($_POST['within_geofence'])) {
        $within_geofence = intval($_POST['within_geofence']);
    } elseif (isset($_POST['is_within_geofence'])) {
        $within_geofence = $_POST['is_within_geofence'] == '1' ? 1 : 0;
    }

    $distance_from_geofence = isset($_POST['distance_from_geofence']) ? floatval($_POST['distance_from_geofence']) : 0;

    // Limit the size of potentially large string values
    $address = isset($_POST['address']) ? $_POST['address'] : '';
    if (strlen($address) > 500) {
        $address = substr($address, 0, 497) . '...';
    }

    $ip_address = isset($_POST['ip_address']) ? $_POST['ip_address'] : '';
    if (strlen($ip_address) > 45) { // Standard IPv6 length
        $ip_address = substr($ip_address, 0, 45);
    }

    $device_info = isset($_POST['device_info']) ? $_POST['device_info'] : '';
    if (strlen($device_info) > 1000) {
        $device_info = substr($device_info, 0, 997) . '...';
    }

    $geofence_id = isset($_POST['geofence_id']) ? intval($_POST['geofence_id']) : 0;

    // Continue with the rest of the script...
    // For Come In functionality - get geofence ID from closest location name
    if (isset($_POST['action']) && $_POST['action'] == 'come_in' && isset($_POST['closest_location']) && !empty($_POST['closest_location'])) {
        $closest_location = $_POST['closest_location'];

        // Look up geofence ID from location name
        try {
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
        } catch (Exception $e) {
            error_log("Error looking up geofence ID: " . $e->getMessage());
        }
    }

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

    // For explicit action=come_in with outside_location_reason
    if (isset($_POST['action']) && $_POST['action'] == 'come_in' && isset($_POST['outside_location_reason']) && !empty($_POST['outside_location_reason'])) {
        $punch_in_outside_reason = $_POST['outside_location_reason'];
    }

    // Determine approval status based on geofence
    $approval_status = ($within_geofence == 1) ? 'approved' : 'pending';

    // Fetch employee manager - get the first senior manager from their department
    $manager_id = null;
    $user_department_query = "SELECT department FROM users WHERE id = ?";
    $stmt_dept = $conn->prepare($user_department_query);
    $stmt_dept->bind_param('i', $user_id);
    $stmt_dept->execute();
    $dept_result = $stmt_dept->get_result();
    $department = null;

    if ($dept_result->num_rows > 0) {
        $dept_row = $dept_result->fetch_assoc();
        $department = $dept_row['department'];

        // Get the manager for this department
        if (!empty($department)) {
            $manager_query = "SELECT id FROM users WHERE 
                (role = 'Senior Manager (Studio)' OR role = 'Senior Manager (Site)') 
                AND department = ? 
                LIMIT 1";
            $stmt_manager = $conn->prepare($manager_query);
            $stmt_manager->bind_param('s', $department);
            $stmt_manager->execute();
            $manager_result = $stmt_manager->get_result();

            if ($manager_result->num_rows > 0) {
                $manager_row = $manager_result->fetch_assoc();
                $manager_id = $manager_row['id'];
            }
        }
    }

    // If no department-specific manager found, get any senior manager
    if (!$manager_id) {
        $default_manager_query = "SELECT id FROM users WHERE 
            role = 'Senior Manager (Studio)' OR role = 'Senior Manager (Site)' 
            LIMIT 1";
        $default_manager_result = $conn->query($default_manager_query);

        if ($default_manager_result->num_rows > 0) {
            $manager_row = $default_manager_result->fetch_assoc();
            $manager_id = $manager_row['id'];
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
        $check_query = "SELECT id, punch_in, approval_status FROM attendance WHERE user_id = ? AND date = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param('is', $user_id, $current_date);
        $stmt->execute();
        $result = $stmt->get_result();

        // Process image upload
        $image_data = null;
        $image_path_for_db = null;
        if (isset($_POST['photo']) && !empty($_POST['photo'])) {
            try {
                // Remove the data URL prefix
                $image_data = $_POST['photo'];
                if (strpos($image_data, 'data:image/jpeg;base64,') === 0) {
                    $image_data = str_replace('data:image/jpeg;base64,', '', $image_data);
                }

                // Decode the base64 image
                $image_data = base64_decode($image_data);

                if ($image_data === false) {
                    throw new Exception("Failed to decode base64 image data");
                }

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
                    if (!mkdir('../uploads/attendance/', 0755, true)) {
                        throw new Exception("Failed to create directory for attendance photos");
                    }
                }

                // Save the image
                if (file_put_contents($physical_path, $image_data) === false) {
                    throw new Exception("Failed to save image to disk");
                }
            } catch (Exception $e) {
                error_log("Error processing image upload: " . $e->getMessage());
                $response['debug']['image_error'] = $e->getMessage();
                // Continue without the image
                $image_path_for_db = null;
            }
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

            // Check if punch-in was already approved (if it required approval)
            $current_approval_status = $row['approval_status'];

            // If punch-in is pending approval and now punch-out is also outside geofence
            // Keep the status as pending
            if ($current_approval_status == 'pending' || !$within_geofence) {
                $punch_out_approval_status = 'pending';
            } else {
                $punch_out_approval_status = 'approved';
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
                overtime_hours = ?,
                approval_status = ?,
                manager_id = ?
                WHERE id = ?";

            $stmt = $conn->prepare($update_query);
            $stmt->bind_param(
                'ssddssssssii',
                $current_time,
                $image_path_for_db,
                $latitude,
                $longitude,
                $address,
                $working_hours_formatted,
                $punch_out_outside_reason,
                $work_report,
                $overtime_hours,
                $punch_out_approval_status,
                $manager_id,
                $row['id']
            );

            if ($stmt->execute()) {
                // Log activity
                logActivity($user_id, 'Punched out', 'Attendance');

                $response['success'] = true;

                if ($punch_out_approval_status == 'pending') {
                    $response['message'] = 'Punch out recorded and pending manager approval';
                    $response['requires_approval'] = true;

                    // Send notification to manager
                    if ($manager_id) {
                        $notification_sent = notify_manager($manager_id, $user_id, $row['id'], 'punch_out');
                        if ($notification_sent) {
                            $response['notification_sent'] = true;
                            error_log("Notification sent to manager ID: $manager_id for attendance ID: {$row['id']}");
                        } else {
                            $response['notification_sent'] = false;
                            error_log("Failed to send notification to manager ID: $manager_id for attendance ID: {$row['id']}");
                        }
                    } else {
                        $response['notification_sent'] = false;
                        error_log("No manager assigned for attendance approval. User ID: $user_id");
                    }
                } else {
                    $response['message'] = 'Punch out recorded successfully';
                }

                // Send WhatsApp notification to user after successful punch out
                try {
                    require_once '../whatsapp/send_punch_notification.php';
                    global $pdo; // Use PDO connection for WhatsApp notification
                    if (isset($pdo) && $pdo) {
                        $whatsapp_sent = sendPunchOutNotification($user_id, $pdo);
                        if ($whatsapp_sent) {
                            error_log("WhatsApp punch out notification sent successfully for user ID: $user_id");
                            if ($debug_mode) {
                                $response['debug']['whatsapp_punchout_notification'] = 'sent';
                            }
                        } else {
                            error_log("WhatsApp punch out notification failed for user ID: $user_id");
                            if ($debug_mode) {
                                $response['debug']['whatsapp_punchout_notification'] = 'failed';
                            }
                        }
                    } else {
                        error_log("PDO connection not available for WhatsApp punch out notification");
                        if ($debug_mode) {
                            $response['debug']['whatsapp_punchout_notification'] = 'pdo_unavailable';
                        }
                    }
                } catch (Exception $whatsappError) {
                    // Log the error but don't fail the punch out
                    error_log("WhatsApp punch out notification error: " . $whatsappError->getMessage());
                    if ($debug_mode) {
                        $response['debug']['whatsapp_punchout_error'] = $whatsappError->getMessage();
                    }
                }

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
                geofence_id,
                approval_status,
                manager_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)";

            $accuracy = 0; // Default value if not provided



            $stmt = $conn->prepare($insert_query);

            // Ensure punch_in_outside_reason is properly passed as a string
            if (is_null($punch_in_outside_reason)) {
                $punch_in_outside_reason = '';
            }

            $stmt->bind_param(
                'isssddisdssssisi',
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
                $punch_in_outside_reason, // Changed from 's' to 's' to ensure it's treated as string
                $geofence_id,
                $approval_status,
                $manager_id
            );

            if ($stmt->execute()) {
                // Log activity
                logActivity($user_id, 'Punched in', 'Attendance');

                $response['success'] = true;

                if ($approval_status == 'pending') {
                    $response['message'] = 'Punch in recorded and pending manager approval';
                    $response['requires_approval'] = true;

                    // Get the attendance ID
                    $attendance_id = $conn->insert_id;

                    // Send notification to manager
                    if ($manager_id) {
                        $notification_sent = notify_manager($manager_id, $user_id, $attendance_id, 'punch_in');
                        if ($notification_sent) {
                            $response['notification_sent'] = true;
                            error_log("Notification sent to manager ID: $manager_id for attendance ID: $attendance_id");
                        } else {
                            $response['notification_sent'] = false;
                            error_log("Failed to send notification to manager ID: $manager_id for attendance ID: $attendance_id");
                        }
                    } else {
                        $response['notification_sent'] = false;
                        error_log("No manager assigned for attendance approval. User ID: $user_id");
                    }
                } else {
                    $response['message'] = 'Punch in recorded successfully';
                }

                // Send WhatsApp notification to user after successful punch in
                try {
                    require_once '../whatsapp/send_punch_notification.php';
                    global $pdo; // Use PDO connection for WhatsApp notification
                    if (isset($pdo) && $pdo) {
                        $whatsapp_sent = sendPunchNotification($user_id, $pdo);
                        if ($whatsapp_sent) {
                            error_log("WhatsApp punch in notification sent successfully for user ID: $user_id");
                            if ($debug_mode) {
                                $response['debug']['whatsapp_notification'] = 'sent';
                            }
                        } else {
                            error_log("WhatsApp punch in notification failed for user ID: $user_id");
                            if ($debug_mode) {
                                $response['debug']['whatsapp_notification'] = 'failed';
                            }
                        }
                    } else {
                        error_log("PDO connection not available for WhatsApp notification");
                        if ($debug_mode) {
                            $response['debug']['whatsapp_notification'] = 'pdo_unavailable';
                        }
                    }
                } catch (Exception $whatsappError) {
                    // Log the error but don't fail the punch in
                    error_log("WhatsApp notification error: " . $whatsappError->getMessage());
                    if ($debug_mode) {
                        $response['debug']['whatsapp_error'] = $whatsappError->getMessage();
                    }
                }


                // Add debug info about the inserted data
                if ($debug_mode) {
                    $response['debug']['inserted_data'] = array(
                        'user_id' => $user_id,
                        'date' => $current_date,
                        'time' => $current_time,
                        'within_geofence' => $within_geofence,
                        'distance_from_geofence' => $distance_from_geofence,
                        'geofence_id' => $geofence_id,
                        'approval_status' => $approval_status,
                        'manager_id' => $manager_id
                    );
                }
            } else {
                $response['message'] = 'Failed to record punch in: ' . $stmt->error;

                // Add debug info about the error
                if ($debug_mode) {
                    $response['debug']['sql_error'] = $stmt->error;
                    $response['debug']['sql_errno'] = $stmt->errno;
                }
            }
        }

    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }

} catch (Exception $e) {
    error_log("Exception in submit_attendance.php: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
    $response['error_type'] = 'caught_exception';
}

// Sanitize response data to prevent JSON encoding issues
$response = isset($response) ? sanitizeArrayForJson($response) : ['success' => false, 'message' => 'Unknown error occurred'];

// Set proper content type header
header('Content-Type: application/json; charset=utf-8');
header('HTTP/1.1 200 OK'); // Ensure we don't send a 500 status

// Capture and discard any unwanted output
$output = ob_get_clean();
if (!empty($output)) {
    error_log("Unwanted output in submit_attendance.php: " . $output);
    // Add to response for debugging
    if (isset($debug_mode) && $debug_mode) {
        $response['debug']['unwanted_output'] = substr($output, 0, 500) . (strlen($output) > 500 ? '...' : '');
    }
}

// Return response
try {
    // Set JSON encoding options based on available PHP version
    $json_options = 0;

    // Only use these options if PHP version supports them
    if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
        $json_options |= JSON_PARTIAL_OUTPUT_ON_ERROR;
    }

    if (defined('JSON_UNESCAPED_UNICODE')) {
        $json_options |= JSON_UNESCAPED_UNICODE;
    }

    // Convert response to JSON with error handling
    $json_response = json_encode($response, $json_options);

    // Check for JSON encoding errors
    if ($json_response === false) {
        // JSON encoding failed
        $json_error = function_exists('json_last_error_msg') ? json_last_error_msg() : 'Unknown JSON error';
        error_log("JSON encoding error in submit_attendance.php: " . $json_error);

        // Try to simplify the response to increase chances of successful encoding
        $simple_response = [
            'success' => false,
            'message' => 'Server error: Failed to encode response. Error: ' . $json_error,
            'error_type' => 'json_encoding_failure'
        ];

        // Try again with the simplified response
        $json_response = json_encode($simple_response);

        // If still failing, create the absolute minimum response
        if ($json_response === false) {
            $json_response = '{"success":false,"message":"Server error: JSON encoding failed"}';
        }
    }

    // JSON encoded successfully
    echo $json_response;
} catch (Throwable $t) {
    // Last resort error handling (catches both Error and Exception)
    error_log("Throwable in submit_attendance.php JSON encoding: " . $t->getMessage());

    // Create a minimal response that should always work
    echo '{"success":false,"message":"A server error occurred"}';
}

// End the script to prevent any other output
exit;
?>