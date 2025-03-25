<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'php-error.log');
error_reporting(E_ALL);

// Clear any previous output
ob_clean();

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Check if config file exists
    if (!file_exists('config.php')) {
        throw new Exception('Configuration file not found');
    }

    require_once 'config.php';

    // Verify database connection
    if (!isset($pdo)) {
        throw new Exception('Database connection not established');
    }

    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }

    $userId = $_SESSION['user_id'];
    $currentTime = date('Y-m-d H:i:s');
    $today = date('Y-m-d');

    // Handle POST request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        
        // Log received data for debugging
        error_log("Received input: " . $input);
        
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input: ' . json_last_error_msg());
        }

        $action = $data['action'] ?? '';
        $workReport = $data['work_report'] ?? null;
        
        if (empty($action)) {
            throw new Exception('Action not specified');
        }

        // Handle punch in
        if ($action === 'punch_in') {
            try {
                error_log("Attempting punch-in for user ID: " . $userId);
                
                // Check for existing punch-in
                $stmt = $pdo->prepare("
                    SELECT id, punch_in, punch_out 
                    FROM attendance 
                    WHERE user_id = ? 
                    AND DATE(date) = ?
                ");
                
                $stmt->execute([$userId, $today]);
                $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingRecord) {
                    if (empty($existingRecord['punch_out'])) {
                        throw new Exception('You have already punched in today. Please punch out first.');
                    } else {
                        throw new Exception('You have already completed your attendance for today.');
                    }
                }

                // Get client IP address
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                
                // Get device info from user agent
                $deviceInfo = $_SERVER['HTTP_USER_AGENT'];
                
                // Default status
                $status = 'present';
                
                // Insert new attendance record
                $insertQuery = "
                    INSERT INTO attendance (
                        user_id,
                        date,
                        punch_in,
                        ip_address,
                        device_info,
                        status,
                        created_at,
                        modified_at
                    ) VALUES (
                        ?, -- user_id
                        ?, -- date
                        ?, -- punch_in
                        ?, -- ip_address
                        ?, -- device_info
                        ?, -- status
                        ?, -- created_at
                        ?  -- modified_at
                    )
                ";
                
                $params = [
                    $userId,
                    $today,
                    $currentTime,
                    $ipAddress,
                    $deviceInfo,
                    $status,
                    $currentTime,
                    $currentTime
                ];
                
                // Log the insert attempt
                error_log("Attempting to insert attendance with params: " . json_encode($params));
                
                $stmt = $pdo->prepare($insertQuery);
                $result = $stmt->execute($params);
                
                if ($result) {
                    error_log("Successfully inserted attendance record with ID: " . $pdo->lastInsertId());
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Punched in successfully',
                        'debug' => [
                            'user_id' => $userId,
                            'punch_time' => $currentTime,
                            'record_id' => $pdo->lastInsertId()
                        ]
                    ]);
                    exit;
                } else {
                    error_log("Database error: " . json_encode($stmt->errorInfo()));
                    throw new Exception('Failed to record punch-in: ' . json_encode($stmt->errorInfo()));
                }
            } catch (Exception $e) {
                error_log("Punch-in error: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
                exit;
            }
        }

        // Handle punch out
        if ($action === 'punch_out') {
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET punch_out = ?, work_report = ?
                WHERE user_id = ? AND date = ? AND punch_out IS NULL
            ");
            $stmt->execute([$currentTime, $workReport, $userId, $today]);
            
            // Calculate working hours
            $stmt = $pdo->prepare("
                SELECT punch_in, punch_out,
                       TIMEDIFF(punch_out, punch_in) as working_hours
                FROM attendance 
                WHERE user_id = ? AND date = ?
            ");
            $stmt->execute([$userId, $today]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => 'Punched out successfully',
                'workingTime' => $attendance['working_hours']
            ]);
            exit;
        }

        throw new Exception('Invalid action specified');
    }

    // In the GET request handling section:
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check_status') {
        try {
            $today = date('Y-m-d');
            $stmt = $pdo->prepare("
                SELECT punch_in, punch_out, 
                       TIMEDIFF(COALESCE(punch_out, NOW()), punch_in) as working_hours
                FROM attendance 
                WHERE user_id = ? AND date = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $today]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'is_punched_in' => !empty($attendance['punch_in']),
                'is_punched_out' => !empty($attendance['punch_out']),
                'punch_in_time' => $attendance['punch_in'] ?? null,
                'punch_out_time' => $attendance['punch_out'] ?? null,
                'working_hours' => $attendance['working_hours'] ?? null
            ]);
            exit;
        } catch (Exception $e) {
            error_log("Status check error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Failed to check status'
            ]);
            exit;
        }
    }

    throw new Exception('Invalid request method');

} catch (Exception $e) {
    error_log("Punch attendance error: " . $e->getMessage());
    http_response_code(200); // Send 200 instead of 500
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
