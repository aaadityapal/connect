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
    $punchOutTime = date('H:i:s');
    $photoData = $data['photo'] ?? null;
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $accuracy = $data['accuracy'] ?? null;
    $punchOutAddress = $data['punch_out_address'] ?? null;
    $punchOutWithinGeofence = $data['punch_out_within_geofence'] ?? null;
    $punchOutDistanceFromGeofence = $data['punch_out_distance_from_geofence'] ?? null;
    $punchOutGeofenceId = $data['punch_out_geofence_id'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $deviceInfo = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $attendanceId = $data['attendance_id'] ?? null;
    $workReport = $data['workReport'] ?? null;
    $geofenceOutsideReason = $data['geofence_outside_reason'] ?? null;

    // Validate work report
    if (!$workReport || strlen(trim($workReport)) === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Work report is required']);
        exit;
    }

    // Count words in work report
    $wordCount = str_word_count(trim($workReport));
    if ($wordCount < 20) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Work report must contain at least 20 words']);
        exit;
    }

    // Validate geofence reason if provided
    if ($geofenceOutsideReason) {
        $geofenceWordCount = count(array_filter(explode(' ', trim($geofenceOutsideReason))));
        if ($geofenceWordCount < 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Geofence reason must contain at least 10 words']);
            exit;
        }
    }

    // Handle photo storage for punch out
    $photoPaths = '';
    if ($photoData) {
        try {
            // Remove data URL prefix
            $photoData = preg_replace('/^data:image\/\w+;base64,/', '', $photoData);

            // Create directory
            $connectRoot = dirname(__DIR__);
            $photoDir = $connectRoot . '/uploads/attendance/';

            if (!file_exists($photoDir)) {
                @mkdir($photoDir, 0755, true);
            }

            // Generate filename in format: {USER_ID}_{DATE}_{TIME}_out_{MILLISECONDS}.jpeg
            $dateFormat = date('Ymd');
            $timeFormat = date('His');
            $milliseconds = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $fileName = $userId . '_' . $dateFormat . '_' . $timeFormat . '_out_' . $milliseconds . '.jpeg';
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
            // Continue without photo - don't fail the entire punch-out
        }
    }

    // Check if user has an open attendance record for today
    $checkQuery = "
        SELECT id FROM attendance 
        WHERE user_id = :user_id 
        AND DATE(date) = :date 
        AND punch_in IS NOT NULL 
        AND punch_out IS NULL
        LIMIT 1
    ";

    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([':user_id' => $userId, ':date' => $date]);

    if ($checkStmt->rowCount() === 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'No open punch-in record found for today']);
        exit;
    }

    $record = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $recordId = $record['id'];

    // Update attendance record with punch-out details
    $updateQuery = "
        UPDATE attendance 
        SET 
            punch_out = :punch_out,
            punch_out_photo = :punch_out_photo,
            punch_out_latitude = :punch_out_latitude,
            punch_out_longitude = :punch_out_longitude,
            punch_out_accuracy = :punch_out_accuracy,
            punch_out_address = :punch_out_address,
            within_geofence = :within_geofence,
            distance_from_geofence = :distance_from_geofence,
            geofence_id = :geofence_id,
            work_report = :work_report,
            punch_out_outside_reason = :punch_out_outside_reason,
            modified_at = CONVERT_TZ(NOW(), '+00:00', '+05:30')
        WHERE id = :id AND user_id = :user_id
    ";

    $updateStmt = $pdo->prepare($updateQuery);
    $success = $updateStmt->execute([
        ':punch_out' => $punchOutTime,
        ':punch_out_photo' => $photoPaths,
        ':punch_out_latitude' => $latitude,
        ':punch_out_longitude' => $longitude,
        ':punch_out_accuracy' => $accuracy,
        ':punch_out_address' => $punchOutAddress,
        ':within_geofence' => $punchOutWithinGeofence,
        ':distance_from_geofence' => $punchOutDistanceFromGeofence,
        ':geofence_id' => $punchOutGeofenceId,
        ':work_report' => $workReport,
        ':punch_out_outside_reason' => $geofenceOutsideReason,
        ':id' => $recordId,
        ':user_id' => $userId
    ]);

    if ($success) {
        // Send WhatsApp notification to user after successful punch out
        try {
            require_once __DIR__ . '/../whatsapp/send_punch_notification.php';
            $whatsapp_sent = sendPunchOutNotification($userId, $pdo);
            if ($whatsapp_sent) {
                error_log("WhatsApp punch out notification sent successfully for maid user ID: $userId");
            } else {
                error_log("WhatsApp punch out notification failed for maid user ID: $userId");
            }
        } catch (Exception $whatsappError) {
            // Log the error but don't fail the punch out
            error_log("WhatsApp punch out notification error for maid: " . $whatsappError->getMessage());
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Punch-out recorded successfully',
            'attendance_id' => $recordId,
            'punch_out_time' => $punchOutTime,
            'date' => $date
        ]);
    } else {
        throw new Exception('Failed to update attendance record');
    }

} catch (Exception $e) {
    error_log('Punch-out error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to record punch-out',
        'details' => $e->getMessage()
    ]);
}
?>