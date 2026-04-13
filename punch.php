<?php
session_start();
require_once 'config/db_connect.php';
require_once __DIR__ . '/whatsapp/send_punch_notification.php';
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json');

// Function to get user's IP address
function getUserIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Function to get device info
function getDeviceInfo()
{
    return $_SERVER['HTTP_USER_AGENT'];
}

// Function to get address from coordinates using reverse geocoding
function getAddressFromCoordinates($latitude, $longitude)
{
    if (!$latitude || !$longitude) {
        return "Unknown location";
    }

    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$latitude&lon=$longitude&zoom=18&addressdetails=1";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'HR Attendance System');

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['display_name'])) {
        return $data['display_name'];
    }

    return "Unknown location";
}

// Function to calculate working hours and overtime
function calculateWorkingHours($punch_in, $punch_out, $shift_end_time)
{
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
        'total_time' => sprintf(
            "%02d:%02d:%02d",
            floor($total_seconds / 3600),
            floor(($total_seconds % 3600) / 60),
            $total_seconds % 60
        ),
        'regular_time' => sprintf(
            "%02d:%02d:%02d",
            $regular_hours,
            $regular_minutes,
            $regular_seconds
        ),
        'overtime' => sprintf(
            "%02d:%02d:%02d",
            $overtime_hours,
            $overtime_minutes,
            $overtime_seconds
        ),
        'has_overtime' => $overtime_seconds > 0
    ];
}

// Ensure table exists for storing project usage inside work reports
function ensureProjectWorkReportMentionsTable($conn)
{
    $sql = "CREATE TABLE IF NOT EXISTS project_work_report_mentions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attendance_id INT NOT NULL,
        user_id INT NOT NULL,
        project_id INT NOT NULL,
        project_title VARCHAR(255) NOT NULL,
        report_date DATE NOT NULL,
        work_report TEXT NOT NULL,
        mention_text VARCHAR(300) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_attendance_project (attendance_id, project_id),
        KEY idx_project_date (project_id, report_date),
        KEY idx_user_date (user_id, report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return $conn->query($sql);
}

// Save one record per mentioned project hashtag in the work report
function saveProjectWorkReportMentions($conn, $attendance_id, $user_id, $report_date, $work_report)
{
    if (empty($work_report) || strpos($work_report, '#') === false) {
        return;
    }

    if (!ensureProjectWorkReportMentionsTable($conn)) {
        error_log("Could not ensure project_work_report_mentions table: " . $conn->error);
        return;
    }

    $project_sql = "SELECT id, title FROM projects WHERE deleted_at IS NULL";
    $project_rs = $conn->query($project_sql);
    if (!$project_rs) {
        error_log("Could not fetch projects for hashtag tracking: " . $conn->error);
        return;
    }

    $insert_sql = "INSERT INTO project_work_report_mentions
        (attendance_id, user_id, project_id, project_title, report_date, work_report, mention_text)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            project_title = VALUES(project_title),
            work_report = VALUES(work_report),
            mention_text = VALUES(mention_text),
            updated_at = CURRENT_TIMESTAMP";
    $insert_stmt = $conn->prepare($insert_sql);
    if (!$insert_stmt) {
        error_log("Prepare failed for project hashtag tracking: " . $conn->error);
        return;
    }

    while ($p = $project_rs->fetch_assoc()) {
        $project_id = (int)$p['id'];
        $project_title = trim((string)$p['title']);
        if ($project_title === '') continue;

        // Matches exact hashtag phrase like: #Project Name
        $pattern = '/(?:^|\s)#' . preg_quote($project_title, '/') . '(?=$|[\s\.,;:!\?\)\]\}])/iu';
        if (!preg_match($pattern, $work_report)) {
            continue;
        }

        $mention_text = '#' . $project_title;

        $insert_stmt->bind_param(
            "iiissss",
            $attendance_id,
            $user_id,
            $project_id,
            $project_title,
            $report_date,
            $work_report,
            $mention_text
        );
        $insert_stmt->execute();
    }

    $insert_stmt->close();
}

// Function to get user's current shift and weekly offs
function getUserShiftDetails($conn, $user_id)
{
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
        // Fetch user shift details to know when their shift ends
        $user_shift = getUserShiftDetails($conn, $user_id);
        $shift_end_time_str = $user_shift['end_time'];

        // Check if user has punched in/out today
        $query = "SELECT punch_in, punch_out FROM attendance 
                 WHERE user_id = ? AND date = ? ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $user_id, $current_date);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['punch_out'] === null) {
                // Currently punched in
                echo json_encode([
                    'is_punched_in' => true,
                    'already_punched_out' => false,
                    'punch_time' => $row['punch_in'],
                    'shift_end_time' => $shift_end_time_str
                ]);
            } else {
                // Already punched out for today
                echo json_encode([
                    'is_punched_in' => false,
                    'already_punched_out' => true,
                    'punch_time' => null,
                    'shift_end_time' => $shift_end_time_str
                ]);
            }
        } else {
            // Not punched in yet today
            echo json_encode([
                'is_punched_in' => false,
                'already_punched_out' => false,
                'punch_time' => null,
                'shift_end_time' => $shift_end_time_str
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
            $address = null;  // Initialize address variable
            $image_file_path = null; // Initialize image_file_path variable

            if (isset($data['latitude']) && isset($data['longitude'])) {
                $latitude = $data['latitude'];
                $longitude = $data['longitude'];

                try {
                    // Form a basic location string from coordinates
                    $location = "Lat: $latitude, Long: $longitude";

                    // Get address from coordinates using reverse geocoding
                    $address = getAddressFromCoordinates($latitude, $longitude);

                } catch (Exception $e) {
                    // If geocoding fails, just use the coordinates
                    error_log("Geocoding failed: " . $e->getMessage());
                }

                // Get accuracy if available
                if (isset($data['accuracy'])) {
                    $accuracy = $data['accuracy'];
                }
            }

            // Use address from request if provided, otherwise use the one from geocoding
            if (isset($data['address']) && !empty($data['address'])) {
                $address = $data['address'];
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

            // Extract outside-geofence reason (if user was outside allowed area)
            $punch_in_outside_reason = isset($data['out_of_geofence_reason']) && !empty(trim($data['out_of_geofence_reason']))
                ? trim($data['out_of_geofence_reason'])
                : null;

            // Extract geofence tracking fields sent by frontend
            $geofence_id          = isset($data['geofence_id'])           ? (int)$data['geofence_id']           : null;
            $within_geofence      = isset($data['within_geofence'])       ? (int)$data['within_geofence']       : 0;
            $distance_from_geofence = isset($data['distance_from_geofence']) ? (float)$data['distance_from_geofence'] : null;

            // Insert new attendance record
            $query = "INSERT INTO attendance (
                user_id, date, punch_in, location, ip_address, device_info, status, created_at,
                shift_time, weekly_offs, auto_punch_out, is_weekly_off,
                punch_in_photo, latitude, longitude, accuracy, address,
                punch_in_outside_reason, geofence_id, within_geofence, distance_from_geofence
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )";

            $stmt = $conn->prepare($query);
            // shift_time is a TIME column — store shift start time only
            $shift_time      = $shift_details['start_time'];
            $weekly_offs     = $shift_details['weekly_offs'];
            $shift_end_time  = $shift_details['end_time'];   // kept for response message only
            // auto_punch_out is a TINYINT boolean flag (0 = not auto punched, 1 = auto punched by system)
            // Set to 0 on manual punch-in
            $auto_punch_out  = 0;

            // Log the punch in time to debug the issue
            error_log("Punch In Debug - Current Time: " . $current_time);
            error_log("Punch In Debug - Shift: " . $shift_details['shift_name'] . " (" . $shift_time . " - " . $shift_end_time . ")");
            error_log("Punch In Debug - Outside Reason: " . ($punch_in_outside_reason ?? 'None'));

            $stmt->bind_param(
                "isssssssssiisdddssidi",
                $user_id,                   // i  user_id
                $current_date,              // s  date
                $current_time,              // s  punch_in
                $location,                  // s  location
                $ip_address,                // s  ip_address
                $device_info,               // s  device_info
                $status,                    // s  status
                $created_at,                // s  created_at
                $shift_time,                // s  shift_time
                $weekly_offs,               // s  weekly_offs
                $auto_punch_out,            // i  auto_punch_out  (TINYINT)
                $is_weekly_off,             // i  is_weekly_off   (TINYINT)
                $image_file_path,           // s  punch_in_photo
                $latitude,                  // d  latitude
                $longitude,                 // d  longitude
                $accuracy,                  // d  accuracy
                $address,                   // s  address
                $punch_in_outside_reason,   // s  punch_in_outside_reason
                $geofence_id,               // i  geofence_id
                $distance_from_geofence,    // d  distance_from_geofence
                $within_geofence            // i  within_geofence (TINYINT)
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

                // Send WhatsApp notification after successful punch in
                try {
                    // Log to global activity feed
                    $attendance_id = $conn->insert_id;
                    $log_desc = "User punched in at " . date('h:i A', strtotime($current_time));
                    $log_meta_data = ['address' => $address, 'geofence_id' => $geofence_id];
                    
                    if (!empty($punch_in_outside_reason)) {
                        $log_desc .= " (Outside Geofence. Reason: " . $punch_in_outside_reason . ")";
                        $log_meta_data['outside_geofence_reason'] = $punch_in_outside_reason;
                    }
                    
                    $log_meta = json_encode($log_meta_data);
                    $log_stmt = $conn->prepare("INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata) VALUES (?, 'punch_in', 'attendance', ?, ?, ?)");
                    $log_stmt->bind_param("iiss", $user_id, $attendance_id, $log_desc, $log_meta);
                    $log_stmt->execute();
                } catch(Exception $logError) {
                    error_log("Activity log error (Punch In): " . $logError->getMessage());
                }

                try {
                    global $pdo; // Use the PDO connection from db_connect.php
                    if ($pdo) {
                        $notificationSent = sendPunchNotification($user_id, $pdo);
                        if ($notificationSent) {
                            error_log("WhatsApp punch in notification sent successfully for user ID: $user_id");
                        } else {
                            error_log("WhatsApp punch in notification failed for user ID: $user_id");
                        }
                    } else {
                        error_log("PDO connection not available for WhatsApp notification");
                    }
                } catch (Exception $whatsappError) {
                    // Log the error but don't fail the punch in
                    error_log("WhatsApp notification error: " . $whatsappError->getMessage());
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
            $punch_out_address = null;  // Initialize punch_out_address variable

            // Get location data if provided
            if (isset($data['latitude']) && isset($data['longitude'])) {
                $latitude = $data['latitude'];
                $longitude = $data['longitude'];

                // Get address from coordinates
                $punch_out_address = getAddressFromCoordinates($latitude, $longitude);

                if (isset($data['accuracy'])) {
                    $accuracy = $data['accuracy'];
                }
            }

            // Use address from request if provided, otherwise use the one from geocoding
            if (isset($data['address']) && !empty($data['address'])) {
                $punch_out_address = $data['address'];
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

            // Extract outside-geofence reason for punch-out
            $punch_out_outside_reason = isset($data['out_of_geofence_reason']) && !empty(trim($data['out_of_geofence_reason']))
                ? trim($data['out_of_geofence_reason'])
                : null;

            // Extract overtime reason submitted from OT modal (if any)
            $overtime_report = isset($data['overtime_report']) && !empty(trim($data['overtime_report']))
                ? trim($data['overtime_report'])
                : null;

            // Update attendance record with punch out details
            $query = "UPDATE attendance SET
                punch_out = ?,
                working_hours = ?,
                overtime_hours = ?,
                work_report = ?,
                modified_at = ?,
                modified_by = ?,
                punch_out_photo = ?,
                punch_out_latitude = ?,
                punch_out_longitude = ?,
                punch_out_accuracy = ?,
                punch_out_address = ?,
                punch_out_outside_reason = ?,
                geofence_id = ?,
                within_geofence = ?,
                distance_from_geofence = ?
                WHERE user_id = ? AND date = ? AND punch_out IS NULL";

            $stmt = $conn->prepare($query);
            $modified_at = $current_datetime;
            $work_report = trim($data['work_report']);

            // Get location data if provided
            $punch_out_latitude  = isset($data['latitude'])  ? $data['latitude']  : null;
            $punch_out_longitude = isset($data['longitude']) ? $data['longitude'] : null;
            $punch_out_accuracy  = isset($data['accuracy'])  ? $data['accuracy']  : null;

            // Geofence fields from frontend
            $geofence_id_out          = isset($data['geofence_id'])           ? (int)$data['geofence_id']           : null;
            $within_geofence_out      = isset($data['within_geofence'])       ? (int)$data['within_geofence']       : 0;
            $distance_from_geofence_out = isset($data['distance_from_geofence']) ? (float)$data['distance_from_geofence'] : null;

            // Add debugging information
            error_log("Punch Out Debug - Query: " . $query);
            error_log("Punch Out Debug - Current Time: " . $current_time);
            error_log("Punch Out Debug - Outside Reason: " . ($punch_out_outside_reason ?? 'None'));
            error_log("Punch Out Debug - Overtime Report: " . ($overtime_report ?? 'None'));
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
            // s=string, i=integer, d=double
            // Params: punch_out(s), working_hours(s), overtime(s), work_report(s), modified_at(s),
            //         modified_by(i), punch_out_photo(s), lat(d), lon(d), accuracy(d),
            //         punch_out_address(s), punch_out_outside_reason(s),
            //         geofence_id(i), within_geofence(i), distance_from_geofence(d), user_id(i), date(s)
            try {
                $stmt->bind_param(
                    "sssssisdddssiidis",
                    $current_time,              // s  punch_out
                    $total_time,                // s  working_hours
                    $overtime,                  // s  overtime_hours
                    $work_report,               // s  work_report
                    $modified_at,               // s  modified_at
                    $modified_by,               // i  modified_by
                    $photo_path,                // s  punch_out_photo
                    $punch_out_latitude,        // d  punch_out_latitude
                    $punch_out_longitude,       // d  punch_out_longitude
                    $punch_out_accuracy,        // d  punch_out_accuracy
                    $punch_out_address,         // s  punch_out_address
                    $punch_out_outside_reason,  // s  punch_out_outside_reason
                    $geofence_id_out,           // i  geofence_id
                    $within_geofence_out,       // i  within_geofence (TINYINT)
                    $distance_from_geofence_out,// d  distance_from_geofence
                    $user_id,                   // i  WHERE user_id
                    $current_date               // s  WHERE date
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

                // Log to global activity feed
                try {
                    $attendance_id = $attendance['id'];
                    $log_desc = "User punched out at " . date('h:i A', strtotime($current_time));
                    $log_meta_data = [
                        'address' => $punch_out_address, 
                        'geofence_id' => $geofence_id_out, 
                        'working_hours' => $time_details['total_time']
                    ];
                    
                    if (!empty($punch_out_outside_reason)) {
                        $log_desc .= " (Outside Geofence. Reason: " . $punch_out_outside_reason . ")";
                        $log_meta_data['outside_geofence_reason'] = $punch_out_outside_reason;
                    }

                    if (!empty($overtime_report)) {
                        $log_meta_data['overtime_report'] = $overtime_report;
                        $log_meta_data['overtime_hours'] = $time_details['overtime'];
                    }
                    
                    $log_meta = json_encode($log_meta_data);
                    $log_stmt = $conn->prepare("INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata) VALUES (?, 'punch_out', 'attendance', ?, ?, ?)");
                    $log_stmt->bind_param("iiss", $user_id, $attendance_id, $log_desc, $log_meta);
                    $log_stmt->execute();
                } catch(Exception $logError) {
                    error_log("Activity log error (Punch Out): " . $logError->getMessage());
                }

                // Track which project hashtags were used in this work report
                try {
                    $attendance_id = (int)$attendance['id'];
                    saveProjectWorkReportMentions($conn, $attendance_id, $user_id, $current_date, $work_report);
                } catch (Exception $mentionError) {
                    error_log("Project hashtag tracking error: " . $mentionError->getMessage());
                }

                // Send WhatsApp notification after successful punch out
                try {
                    global $pdo; // Use the PDO connection from db_connect.php
                    if ($pdo) {
                        $notificationSent = sendPunchOutNotification($user_id, $pdo);
                        if ($notificationSent) {
                            error_log("WhatsApp punch out notification sent successfully for user ID: $user_id");
                        } else {
                            error_log("WhatsApp punch out notification failed for user ID: $user_id");
                        }
                    } else {
                        error_log("PDO connection not available for WhatsApp punch out notification");
                    }
                } catch (Exception $whatsappError) {
                    // Log the error but don't fail the punch out
                    error_log("WhatsApp punch out notification error: " . $whatsappError->getMessage());
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

                if (
                    $punch_in_loc && $punch_in_loc['latitude'] && $punch_in_loc['longitude'] &&
                    $punch_out_latitude && $punch_out_longitude
                ) {

                    // Calculate distance between punch-in and punch-out locations using Haversine formula
                    $earth_radius = 6371; // Radius of the earth in km
                    $lat_diff = deg2rad($punch_out_latitude - $punch_in_loc['latitude']);
                    $lon_diff = deg2rad($punch_out_longitude - $punch_in_loc['longitude']);

                    $a = sin($lat_diff / 2) * sin($lat_diff / 2) +
                        cos(deg2rad($punch_in_loc['latitude'])) * cos(deg2rad($punch_out_latitude)) *
                        sin($lon_diff / 2) * sin($lon_diff / 2);

                    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
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
                    'success'           => true,
                    'message'           => $message,
                    'punch_time'        => $current_time,
                    'working_hours'     => $time_message,
                    'has_overtime'      => $time_details['has_overtime'],
                    'work_report'       => $work_report,
                    'location_changed'  => $location_changed,
                    'location_distance' => $distance_km,
                    'location_message'  => $location_message,
                    'attendance_id'     => (int)$attendance['id']  // ← needed for OT submission
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