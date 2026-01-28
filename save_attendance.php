<?php
// Start session to access session variables
session_start();

// Include database configuration
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Get the user ID from session
$user_id = $_SESSION['user_id'];

// Get the JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);

// Validate required data
if (!$data) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Get current date and time
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');

    // Check if user has already completed their attendance cycle for today
    $stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ? AND punch_in IS NOT NULL AND punch_out IS NOT NULL");
    $stmt->execute([$user_id, $current_date]);
    $completed_cycle = $stmt->fetch();

    if ($completed_cycle && $data['action'] === 'punch_in') {
        // User has already completed their attendance cycle for today
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'You have already completed your attendance for today. You cannot punch in again until tomorrow.']);
        exit();
    }

    // Check if there's already an attendance record for today
    $stmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, $current_date]);
    $attendance = $stmt->fetch();

    $photo_data = null;
    $photo_filename = null;

    // Handle photo data if provided
    if (isset($data['photo']) && !empty($data['photo'])) {
        try {
            // Remove the URL header from the base64 string
            $photo_data = $data['photo'];

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
            $photo_filename = 'user_' . $user_id . '_' . date('Ymd_His') . '_' . $data['action'] . '.jpg';

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

        } catch (Exception $e) {
            // Log the error
            error_log("Photo handling error: " . $e->getMessage());

            // Set photo data to null and continue with the operation
            $photo_filename = null;
        }
    }

    // Prepare data for database insertion
    $punch_in_outside_reason = null;
    $punch_out_outside_reason = null;
    $work_report = null;
    $within_geofence = null;
    $geofence_id = null;
    $distance_from_geofence = null;

    if (isset($data['geofence_reason']) && !empty($data['geofence_reason'])) {
        if ($data['action'] === 'punch_in') {
            $punch_in_outside_reason = $data['geofence_reason'];
        } elseif ($data['action'] === 'punch_out') {
            $punch_out_outside_reason = $data['geofence_reason'];
        }
    }

    // Get work report for punch out
    if (isset($data['work_report']) && !empty($data['work_report'])) {
        $work_report = $data['work_report'];
    }

    // Get geofence information
    if (isset($data['within_geofence'])) {
        $within_geofence = $data['within_geofence'] ? 1 : 0;
    }

    if (isset($data['geofence_id']) && !empty($data['geofence_id'])) {
        $geofence_id = $data['geofence_id'];
    }

    if (isset($data['distance_from_geofence']) && !empty($data['distance_from_geofence'])) {
        $distance_from_geofence = $data['distance_from_geofence'];
    }

    // Get IP address
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

    // Get device info from user agent
    $device_info = $_SERVER['HTTP_USER_AGENT'] ?? null;

    // Check if this is a punch in or punch out action
    if ($data['action'] === 'punch_in') {
        if ($attendance) {
            // Update existing record for punch in
            $stmt = $pdo->prepare("UPDATE attendance SET 
                punch_in = ?,
                punch_in_photo = ?,
                punch_in_latitude = ?,
                punch_in_longitude = ?,
                punch_in_accuracy = ?,
                punch_in_outside_reason = ?,
                ip_address = ?,
                device_info = ?,
                address = ?,
                within_geofence = ?,
                geofence_id = ?,
                distance_from_geofence = ?,
                latitude = ?,
                longitude = ?,
                accuracy = ?
                WHERE id = ?");

            $stmt->execute([
                $current_time,
                $photo_filename,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['accuracy'] ?? null,
                $punch_in_outside_reason,
                $ip_address,
                $device_info,
                $data['address'] ?? null,
                $within_geofence,
                $geofence_id,
                $distance_from_geofence,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['accuracy'] ?? null,
                $attendance['id']
            ]);
        } else {
            // Create new record for punch in
            $stmt = $pdo->prepare("INSERT INTO attendance (
                user_id, date, punch_in, punch_in_photo, punch_in_latitude, 
                punch_in_longitude, punch_in_accuracy, punch_in_outside_reason,
                ip_address, device_info, address, within_geofence, geofence_id, 
                distance_from_geofence, latitude, longitude, accuracy
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $user_id,
                $current_date,
                $current_time,
                $photo_filename,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['accuracy'] ?? null,
                $punch_in_outside_reason,
                $ip_address,
                $device_info,
                $data['address'] ?? null,
                $within_geofence,
                $geofence_id,
                $distance_from_geofence,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['accuracy'] ?? null
            ]);
        }
    } elseif ($data['action'] === 'punch_out') {
        if ($attendance) {
            // Update existing record for punch out
            $stmt = $pdo->prepare("UPDATE attendance SET 
                punch_out = ?,
                punch_out_photo = ?,
                punch_out_latitude = ?,
                punch_out_longitude = ?,
                punch_out_accuracy = ?,
                punch_out_outside_reason = ?,
                work_report = ?,
                ip_address = ?,
                device_info = ?,
                within_geofence = ?,
                geofence_id = ?,
                distance_from_geofence = ?,
                modified_at = NOW()
                WHERE id = ?");

            $stmt->execute([
                $current_time,
                $photo_filename,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['accuracy'] ?? null,
                $punch_out_outside_reason,
                $work_report,
                $ip_address,
                $device_info,
                $within_geofence,
                $geofence_id,
                $distance_from_geofence,
                $attendance['id']
            ]);
        } else {
            // Create new record for punch out (in case of missing punch in)
            $stmt = $pdo->prepare("INSERT INTO attendance (
                user_id, date, punch_out, punch_out_photo, punch_out_latitude, 
                punch_out_longitude, punch_out_accuracy, punch_out_outside_reason,
                work_report, ip_address, device_info, within_geofence, geofence_id, 
                distance_from_geofence
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $user_id,
                $current_date,
                $current_time,
                $photo_filename,
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['accuracy'] ?? null,
                $punch_out_outside_reason,
                $work_report,
                $ip_address,
                $device_info,
                $within_geofence,
                $geofence_id,
                $distance_from_geofence
            ]);
        }
    }

    // Commit transaction
    $pdo->commit();

    // Trigger WhatsApp Notification for Punch In
    if (isset($data['action']) && $data['action'] === 'punch_in') {
        try {
            require_once 'whatsapp/send_punch_notification.php';
            // execute in background or just run safely
            sendPunchNotification($user_id, $pdo);
        } catch (Exception $waError) {
            error_log("Failed to trigger WhatsApp notification: " . $waError->getMessage());
        }
    }

    // Trigger WhatsApp Notification for Punch Out
    if (isset($data['action']) && $data['action'] === 'punch_out') {
        try {
            require_once 'whatsapp/send_punch_notification.php';
            // execute in background or just run safely
            sendPunchOutNotification($user_id, $pdo);
        } catch (Exception $waError) {
            error_log("Failed to trigger WhatsApp punch out notification: " . $waError->getMessage());
        }
    }

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Attendance recorded successfully',
        'photo_filename' => $photo_filename
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollback();

    // Log error
    error_log("Attendance save error: " . $e->getMessage());

    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save attendance data: ' . $e->getMessage()
    ]);
}
?>