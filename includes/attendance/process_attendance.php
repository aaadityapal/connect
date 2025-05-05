<?php
// Include database connection
require_once '../../config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Function to get address from coordinates using reverse geocoding
function getAddressFromCoordinates($latitude, $longitude) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$latitude&lon=$longitude&zoom=18&addressdetails=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ArchitectsHive Site Supervision App');
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['display_name'])) {
        return $data['display_name'];
    }
    
    return "Unknown location";
}

// Set timezone to India Standard Time
date_default_timezone_set('Asia/Kolkata');

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = $_POST['user_id'] ?? $_SESSION['user_id'];
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $accuracy = $_POST['accuracy'] ?? null;
    $deviceInfo = $_POST['device_info'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $currentDateTime = date('Y-m-d H:i:s'); // Now returns IST time
    $today = date('Y-m-d'); // IST date
    
    // Get location address if coordinates provided
    $address = '';
    if ($latitude && $longitude) {
        $address = getAddressFromCoordinates($latitude, $longitude);
    }
    
    try {
        if ($action === 'punch_in') {
            // Check if already punched in today and not punched out
            $checkStmt = $pdo->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
            $checkStmt->execute([$userId, $today]);
            
            if ($checkStmt->rowCount() > 0) {
                // User already has an attendance record for today
                $attendanceRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                // Now check if they haven't punched out
                $punchOutCheckStmt = $pdo->prepare("SELECT id FROM attendance WHERE id = ? AND punch_out IS NULL");
                $punchOutCheckStmt->execute([$attendanceRecord['id']]);
                
                if ($punchOutCheckStmt->rowCount() > 0) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'You are already punched in for today. Please punch out first.'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'You have already completed your attendance for today.'
                    ]);
                }
                exit;
            }
            
            // Insert new attendance record
            $insertStmt = $pdo->prepare("INSERT INTO attendance 
                                         (user_id, date, punch_in, location, ip_address, device_info, 
                                          latitude, longitude, accuracy, address, status, created_at, modified_at, shift_time) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $insertStmt->execute([
                $userId,
                $today,
                $currentDateTime,
                'on-site', // Default location status
                $ipAddress,
                $deviceInfo,
                $latitude,
                $longitude,
                $accuracy,
                $address,
                'present', // Default status
                $currentDateTime,
                $currentDateTime,
                'IST' // Adding the time zone indicator
            ]);
            
            $attendanceId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Punched in successfully at ' . date('h:i A', strtotime($currentDateTime)) . ' IST',
                'attendance_id' => $attendanceId,
                'time' => date('h:i A', strtotime($currentDateTime)) . ' IST',
                'hours_worked' => 0,
                'minutes_worked' => 0,
                'seconds_worked' => 0
            ]);
            
        } elseif ($action === 'punch_out') {
            $attendanceId = $_POST['attendance_id'] ?? null;
            
            if (!$attendanceId) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid attendance record'
                ]);
                exit;
            }
            
            // Get the punch-in time
            $getTimeStmt = $pdo->prepare("SELECT punch_in FROM attendance WHERE id = ? AND user_id = ?");
            $getTimeStmt->execute([$attendanceId, $userId]);
            $record = $getTimeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$record) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Attendance record not found'
                ]);
                exit;
            }
            
            // Calculate working hours
            $punchIn = new DateTime($record['punch_in']);
            $punchOut = new DateTime($currentDateTime);
            $interval = $punchIn->diff($punchOut);
            
            $workingHours = $interval->h + ($interval->days * 24);
            $workingMinutes = $interval->i;
            $totalMinutes = ($workingHours * 60) + $workingMinutes;
            $decimalHours = round($totalMinutes / 60, 2);
            
            // Update the record with punch-out time
            $updateStmt = $pdo->prepare("UPDATE attendance 
                                         SET punch_out = ?, 
                                            latitude = ?, 
                                            longitude = ?, 
                                            accuracy = ?, 
                                            address = CONCAT(address, ' to ', ?), 
                                            working_hours = ?, 
                                            modified_at = ?
                                         WHERE id = ? AND user_id = ?");
            
            $updateStmt->execute([
                $currentDateTime,
                $latitude,
                $longitude,
                $accuracy,
                $address,
                $decimalHours,
                $currentDateTime,
                $attendanceId,
                $userId
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Punched out successfully at ' . date('h:i A', strtotime($currentDateTime)) . ' IST',
                'working_hours' => $decimalHours,
                'time' => date('h:i A', strtotime($currentDateTime)) . ' IST'
            ]);
            
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
        }
    } catch (PDOException $e) {
        error_log('Attendance Error: ' . $e->getMessage());
        
        // Check for duplicate entry violation
        if (strpos($e->getMessage(), 'Integrity constraint violation') !== false && 
            strpos($e->getMessage(), 'unique_attendance') !== false) {
            
            echo json_encode([
                'success' => false,
                'message' => 'You have already recorded attendance for today.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Database error occurred. Please try again later.'
            ]);
        }
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
} 