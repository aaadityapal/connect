<?php
// Turn off error display, log them instead
error_reporting(E_ALL);
ini_set('display_errors', 1); // Temporarily enable for debugging
ini_set('log_errors', 1);

$configPath = dirname(dirname(__DIR__)) . '/config.php';
if (!file_exists($configPath)) {
    die(json_encode(['success' => false, 'message' => 'Config file not found at: ' . $configPath]));
}

require_once $configPath;
session_start();

// Ensure we're sending JSON response
header('Content-Type: application/json');

try {
    if (!isset($conn)) {
        throw new Exception("Database connection variable not set");
    }
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Get connection details for debugging
    error_log("Host: " . $conn->host_info);
    error_log("Server Info: " . $conn->server_info);

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input data: ' . json_last_error_msg());
    }
    
    // Debug log
    error_log("Received input: " . print_r($input, true));

    // Start transaction
    $conn->begin_transaction();

    // Insert main task
    $stmt = $conn->prepare("INSERT INTO tasks (title, description, due_date, category_id, 
        priority_id, status_id, created_by, task_type, created_at) 
        VALUES (?, ?, ?, ?, ?, 1, ?, ?, NOW())");
    $stmt->bind_param("sssiisi", 
        $input['title'],
        $input['description'],
        $input['due_date'],
        $input['category_id'],
        $input['priority_id'],
        $_SESSION['user_id'],
        $input['project_type']
    );
    $stmt->execute();
    $taskId = $conn->insert_id;

    // Insert stages
    foreach ($input['stages'] as $stageIndex => $stage) {
        $stmt = $conn->prepare("INSERT INTO task_stages (task_id, stage_number, assignee_id, 
            start_date, due_date, status, priority, created_at) 
            VALUES (?, ?, ?, ?, ?, 'not_started', ?, NOW())");
        
        $stageNum = $stageIndex + 1;
        $priority = $stage['priority'] ?? 'medium';
        $stmt->bind_param("iiisss", 
            $taskId,
            $stageNum,
            $stage['assignee_id'],
            $stage['start_date'],
            $stage['due_date'],
            $priority
        );
        $stmt->execute();
        $stageId = $conn->insert_id;

        // Insert substages
        if (isset($stage['substages']) && is_array($stage['substages'])) {
            foreach ($stage['substages'] as $substage) {
                $stmt = $conn->prepare("INSERT INTO task_substages (stage_id, description, 
                    status, start_date, end_date, assignee_id, priority, created_at) 
                    VALUES (?, ?, 'not_started', ?, ?, ?, ?, NOW())");
                
                $substage_priority = $substage['priority'] ?? 'medium';
                $stmt->bind_param("isssss", 
                    $stageId,
                    $substage['title'],
                    $substage['start_date'],
                    $substage['due_date'],
                    $substage['assignee_id'],
                    $substage_priority
                );
                $stmt->execute();
            }
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Task saved successfully']);

} catch (Exception $e) {
    // Rollback on error
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }
    error_log("Task save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?> 