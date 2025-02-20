<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add CORS headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Simple connection check
if (isset($_GET['check'])) {
    echo json_encode(['status' => 'ok', 'message' => 'Server is reachable']);
    exit;
}

try {
    // Include database configuration
    require_once 'config.php';

    // Test database connection
    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection failed");
    }

    // Get and validate task ID
    if (!isset($_GET['task_id'])) {
        throw new Exception("Task ID is required");
    }

    $taskId = intval($_GET['task_id']);

    // Simple query first to test database connection
    $query = "SELECT 
        id,
        title,
        description,
        created_by,
        category_id,
        priority,
        status,
        due_date,
        priority_id,
        status_id,
        created_at,
        updated_at
    FROM tasks 
    WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Query prepare failed: " . $conn->error);
    }

    $stmt->bind_param('i', $taskId);
    
    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $task = $result->fetch_assoc();

    if (!$task) {
        throw new Exception("Task not found");
    }

    // Return complete task data
    echo json_encode([
        'id' => $task['id'],
        'title' => $task['title'],
        'description' => $task['description'],
        'created_by' => $task['created_by'],
        'category_id' => $task['category_id'],
        'priority' => $task['priority'],
        'status' => $task['status'],
        'due_date' => $task['due_date'],
        'priority_id' => $task['priority_id'],
        'status_id' => $task['status_id'],
        'created_at' => $task['created_at'],
        'updated_at' => $task['updated_at']
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Error in test_task_details.php: " . $e->getMessage());
    
    // Send error response
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

// Close connections
if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close(); 