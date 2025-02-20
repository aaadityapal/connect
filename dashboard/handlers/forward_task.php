<?php
// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Start output buffering
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set headers
header('Content-Type: application/json');

try {
    // Log the start of request processing
    error_log("Starting forward_task.php processing");
    
    // Check database configuration
    $dbPath = __DIR__ . '/../../config/db_connect.php';
    if (!file_exists($dbPath)) {
        throw new Exception("Database configuration file not found at: $dbPath");
    }
    
    require_once $dbPath;
    
    // Verify database connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn->connect_error ?? 'Connection not established'));
    }

    // Get and log the raw input
    $raw_input = file_get_contents('php://input');
    error_log("Raw input: " . $raw_input);

    // Parse JSON data
    $data = json_decode($raw_input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON decode error: " . json_last_error_msg());
    }

    // Log decoded data
    error_log("Decoded data: " . print_r($data, true));

    // Validate required data
    if (!isset($data['type']) || !isset($data['id']) || !isset($data['projectId']) || !isset($data['selectedUsers'])) {
        throw new Exception("Missing required data. Required: type, id, projectId, selectedUsers. Received: " . print_r($data, true));
    }

    // Validate session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("No user session found");
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        $currentTime = date('Y-m-d H:i:s');
        $performedBy = $_SESSION['user_id'];

        foreach ($data['selectedUsers'] as $userId) {
            error_log("Processing user ID: " . $userId);

            // For stage forwarding
            if ($data['type'] === 'stage') {
                // Verify stage exists
                $stageCheck = $conn->prepare("SELECT id FROM project_stages WHERE id = ?");
                $stageCheck->bind_param("i", $data['id']);
                $stageCheck->execute();
                if (!$stageCheck->get_result()->num_rows) {
                    throw new Exception("Stage not found: " . $data['id']);
                }

                // Insert into forward_tasks
                $insertForward = $conn->prepare("
                    INSERT INTO forward_tasks 
                    (project_id, stage_id, forwarded_by, forwarded_to, type, status, created_at)
                    VALUES (?, ?, ?, ?, 'stage', 'pending', ?)
                ");
                $insertForward->bind_param("iiiis", 
                    $data['projectId'],
                    $data['id'],
                    $performedBy,
                    $userId,
                    $currentTime
                );

            } else {
                // For substage forwarding
                // Verify substage exists and get stage_id
                $substageCheck = $conn->prepare("SELECT stage_id FROM project_substages WHERE id = ?");
                $substageCheck->bind_param("i", $data['id']);
                $substageCheck->execute();
                $result = $substageCheck->get_result();
                if (!$result->num_rows) {
                    throw new Exception("Substage not found: " . $data['id']);
                }
                $stageData = $result->fetch_assoc();

                // Insert into forward_tasks
                $insertForward = $conn->prepare("
                    INSERT INTO forward_tasks 
                    (project_id, stage_id, substage_id, forwarded_by, forwarded_to, type, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'substage', 'pending', ?)
                ");
                $insertForward->bind_param("iiiiss", 
                    $data['projectId'],
                    $stageData['stage_id'],
                    $data['id'],
                    $performedBy,
                    $userId,
                    $currentTime
                );
            }

            if (!$insertForward->execute()) {
                throw new Exception("Failed to insert forward task: " . $insertForward->error);
            }

            // Log the activity
            $description = $data['type'] === 'stage' 
                ? "Stage forwarded to user ID: $userId"
                : "Substage forwarded to user ID: $userId";

            $logStmt = $conn->prepare("
                INSERT INTO project_activity_log 
                (project_id, stage_id, substage_id, activity_type, description, performed_by, performed_at)
                VALUES (?, ?, ?, 'forward', ?, ?, ?)
            ");

            $substageId = $data['type'] === 'substage' ? $data['id'] : null;
            $stageId = $data['type'] === 'stage' ? $data['id'] : ($stageData['stage_id'] ?? null);

            $logStmt->bind_param("iiisss", 
                $data['projectId'],
                $stageId,
                $substageId,
                $description,
                $performedBy,
                $currentTime
            );

            if (!$logStmt->execute()) {
                throw new Exception("Failed to log activity: " . $logStmt->error);
            }
        }

        // Commit transaction
        $conn->commit();
        
        // Clear output buffer and send success response
        ob_clean();
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    // Log the error
    error_log("Error in forward_task.php: " . $e->getMessage());
    
    // Clear output buffer
    ob_clean();
    
    // Send error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}

// End output buffering
ob_end_flush();