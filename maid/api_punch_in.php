<?php
session_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON data
    $input = file_get_contents('php://input');

    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid JSON data');
    }

    // Extract data - Use Indian Standard Time (IST)
    date_default_timezone_set('Asia/Kolkata');
    $date = date('Y-m-d');
    $punchInTime = date('H:i:s');
    $photoData = $data['photo'] ?? null;
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $accuracy = $data['accuracy'] ?? null;
    $address = $data['address'] ?? null;
    $withinGeofence = $data['within_geofence'] ?? null;
    $distanceFromGeofence = $data['distance_from_geofence'] ?? null;
    $geofenceId = $data['geofence_id'] ?? null;
    $geofenceOutsideReason = $data['geofence_outside_reason'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? null;

    // Validate geofence reason if provided
    if ($geofenceOutsideReason) {
        $wordCount = count(array_filter(explode(' ', trim($geofenceOutsideReason))));
        if ($wordCount < 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Geofence reason must contain at least 10 words']);
            exit;
        }
    }

    // Handle photo storage
    $photoPaths = '';
    if ($photoData) {
        try {
            // Remove data URL prefix
            $photoData = preg_replace('/^data:image\/\w+;base64,/', '', $photoData);

            // Create directory - use /uploads/attendance/ as per documentation
            $connectRoot = dirname(__DIR__);
            $photoDir = $connectRoot . '/uploads/attendance/';

            if (!file_exists($photoDir)) {
                @mkdir($photoDir, 0755, true);
            }

            // Generate filename in format: {USER_ID}_{DATE}_{TIME}_{MILLISECONDS}.jpeg
            $dateFormat = date('Ymd');        // YYYYMMDD
            $timeFormat = date('His');        // HHMMSS (24-hour IST)
            $milliseconds = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $fileName = $userId . '_' . $dateFormat . '_' . $timeFormat . '_' . $milliseconds . '.jpeg';
            $filePath = $photoDir . $fileName;

            $decodedPhoto = base64_decode($photoData, true);
            if ($decodedPhoto === false) {
                throw new Exception('Invalid photo data encoding');
            }

            $bytes_written = @file_put_contents($filePath, $decodedPhoto);

            if ($bytes_written !== false && $bytes_written > 0) {
                $photoPaths = 'uploads/attendance/' . $fileName;
            }
        } catch (Exception $photoException) {
            // Continue without photo - don't fail the entire punch-in
        }
    }

    // Check if already punched in today
    $checkQuery = "SELECT id FROM attendance WHERE user_id = :user_id AND DATE(date) = :date AND punch_in IS NOT NULL LIMIT 1";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([':user_id' => $userId, ':date' => $date]);

    if ($checkStmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Already punched in today']);
        exit;
    }

    // Get shift info
    $shiftQuery = "
        SELECT s.id as shift_id, s.shift_name, s.start_time, s.end_time, us.weekly_offs
        FROM shifts s
        INNER JOIN user_shifts us ON s.id = us.shift_id
        WHERE us.user_id = :user_id
        AND us.effective_from <= CURDATE()
        AND (us.effective_to IS NULL OR us.effective_to >= CURDATE())
        LIMIT 1
    ";

    $shiftStmt = $pdo->prepare($shiftQuery);
    $shiftStmt->execute([':user_id' => $userId]);
    $shift = $shiftStmt->fetch(PDO::FETCH_ASSOC);

    $shiftId = $shift['shift_id'] ?? null;
    $shiftTime = ($shift ? $shift['start_time'] : null); // Store only start_time, not range
    $weeklyOffs = $shift['weekly_offs'] ?? null;

    // Check if today is weekly off
    $dayOfWeek = date('l');
    $isWeeklyOff = ($weeklyOffs && stripos($weeklyOffs, $dayOfWeek) !== false) ? 1 : 0;

    // Insert attendance record
    $insertQuery = "
        INSERT INTO attendance (
            user_id, date, punch_in, punch_in_photo, punch_in_latitude, 
            punch_in_longitude, punch_in_accuracy, address, within_geofence, 
            distance_from_geofence, geofence_id, ip_address, device_info, 
            shifts_id, shift_time, weekly_offs, is_weekly_off, 
            approval_status, status, punch_in_outside_reason, created_at, modified_at
        ) VALUES (
            :user_id, :date, :punch_in, :punch_in_photo, :punch_in_latitude,
            :punch_in_longitude, :punch_in_accuracy, :address, :within_geofence,
            :distance_from_geofence, :geofence_id, :ip_address, :device_info,
            :shifts_id, :shift_time, :weekly_offs, :is_weekly_off,
            :approval_status, :status, :punch_in_outside_reason, CONVERT_TZ(NOW(), '+00:00', '+05:30'), CONVERT_TZ(NOW(), '+00:00', '+05:30')
        )
    ";

    $insertStmt = $pdo->prepare($insertQuery);
    $success = $insertStmt->execute([
        ':user_id' => $userId,
        ':date' => $date,
        ':punch_in' => $punchInTime,
        ':punch_in_photo' => $photoPaths,
        ':punch_in_latitude' => $latitude,
        ':punch_in_longitude' => $longitude,
        ':punch_in_accuracy' => $accuracy,
        ':address' => $address,
        ':within_geofence' => $withinGeofence,
        ':distance_from_geofence' => $distanceFromGeofence,
        ':geofence_id' => $geofenceId,
        ':ip_address' => $ipAddress,
        ':device_info' => $deviceInfo,
        ':shifts_id' => $shiftId,
        ':shift_time' => $shiftTime,
        ':weekly_offs' => $weeklyOffs,
        ':is_weekly_off' => $isWeeklyOff,
        ':approval_status' => 'pending',
        ':status' => 'present',
        ':punch_in_outside_reason' => $geofenceOutsideReason
    ]);

    if ($success) {
        $attendanceId = $pdo->lastInsertId();

        // Send WhatsApp notification to user after successful punch in
        try {
            require_once __DIR__ . '/../whatsapp/send_punch_notification.php';
            $whatsapp_sent = sendPunchNotification($userId, $pdo);
            if ($whatsapp_sent) {
                error_log("WhatsApp punch in notification sent successfully for maid user ID: $userId");
            } else {
                error_log("WhatsApp punch in notification failed for maid user ID: $userId");
            }
        } catch (Exception $whatsappError) {
            // Log the error but don't fail the punch in
            error_log("WhatsApp notification error for maid: " . $whatsappError->getMessage());
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Punch-in recorded successfully',
            'attendance_id' => $attendanceId,
            'punch_in_time' => $punchInTime,
            'date' => $date
        ]);
    } else {
        throw new Exception('Failed to insert attendance record');
    }

} catch (Exception $e) {
    error_log('Punch-in error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to record punch-in',
        'details' => $e->getMessage()
    ]);
}
?>