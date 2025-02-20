<?php
// Add these at the very top of the file
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Log the request for debugging
error_log("Received request: " . file_get_contents('php://input'));

// Prevent any output before our JSON response
ob_start();

// To use the correct path (adjust according to your directory structure)
require_once '../../config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure proper content type
header('Content-Type: application/json');

// Add this near the top of your PHP file, after the headers
$rawInput = file_get_contents('php://input');
error_log('Received raw input: ' . $rawInput);

$input = json_decode($rawInput, true);
error_log('Decoded input: ' . print_r($input, true));

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }

    $user_id = $_SESSION['user_id'];

    // Verify user exists in database
    $user_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $user_check->bind_param('i', $user_id);
    $user_check->execute();
    
    if ($user_check->get_result()->num_rows === 0) {
        throw new Exception('Invalid user session');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    error_log("Received input: " . json_encode($input)); // Log the input

    // Validate required fields
    if (!isset($input['entity_type']) || !isset($input['entity_id']) || !isset($input['new_status']) || !isset($input['task_id'])) {
        throw new Exception('Missing required fields');
    }

    // Sanitize inputs
    $entity_type = $input['entity_type'];
    $entity_id = intval($input['entity_id']);
    $new_status = $input['new_status'];
    $task_id = intval($input['task_id']);

    // Validate entity type
    if (!in_array($entity_type, ['stage', 'substage'])) {
        throw new Exception('Invalid entity type');
    }

    // Validate status
    if (!in_array($new_status, [
        'not_started',
        'in_progress',
        'completed',
        'delayed',
        'on_hold',
        'freezed',
        'for_approval',
        'pending'
    ])) {
        throw new Exception('Invalid status value: ' . $new_status);
    }

    // After your database connection
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed'
        ]);
        exit;
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Get current status
        $table = ($entity_type === 'stage') ? 'task_stages' : 'task_substages';
        
        // Debug log for table name and query
        error_log("Table being queried: " . $table);
        error_log("Entity ID being queried: " . $entity_id);
        
        // First, get the current status with explicit error checking
        $status_check_sql = "SELECT status FROM {$table} WHERE id = ?";
        error_log("Status check SQL: " . $status_check_sql);
        
        $status_query = $conn->prepare($status_check_sql);
        if (!$status_query) {
            throw new Exception("Failed to prepare status query: " . $conn->error);
        }
        
        $status_query->bind_param('i', $entity_id);
        
        if (!$status_query->execute()) {
            throw new Exception("Failed to execute status query: " . $status_query->error);
        }
        
        $result = $status_query->get_result();
        error_log("Number of rows found: " . $result->num_rows);
        
        if ($result->num_rows === 0) {
            throw new Exception("Entity not found in table {$table} with ID {$entity_id}");
        }
        
        $row = $result->fetch_assoc();
        $old_status = $row['status'];
        
        error_log("Retrieved old status: " . ($old_status ?: 'NULL'));
        
        // Now update the status
        $update_sql = "UPDATE {$table} SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        error_log("Update SQL: " . $update_sql);
        error_log("New status to set: " . $new_status);
        
        $update_query = $conn->prepare($update_sql);
        if (!$update_query) {
            throw new Exception("Failed to prepare update query: " . $conn->error);
        }
        
        $update_query->bind_param('si', $new_status, $entity_id);
        
        if (!$update_query->execute()) {
            throw new Exception("Failed to execute update query: " . $update_query->error);
        }
        
        error_log("Rows affected by update: " . $update_query->affected_rows);

        // Record in history with explicit error checking
        $history_sql = "
            INSERT INTO task_status_history 
            (entity_type, entity_id, old_status, new_status, changed_by, task_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        error_log("History SQL: " . $history_sql);
        
        $history_query = $conn->prepare($history_sql);
        if (!$history_query) {
            throw new Exception("Failed to prepare history query: " . $conn->error);
        }
        
        error_log("Binding parameters for history - Entity Type: $entity_type, Entity ID: $entity_id, Old Status: $old_status, New Status: $new_status, User ID: $user_id, Task ID: $task_id");
        
        $history_query->bind_param('sissii', $entity_type, $entity_id, $old_status, $new_status, $user_id, $task_id);
        
        if (!$history_query->execute()) {
            throw new Exception("Failed to execute history query: " . $history_query->error);
        }
        
        error_log("History record inserted. Rows affected: " . $history_query->affected_rows);

        // Commit transaction
        $conn->commit();
        error_log("Transaction committed successfully");

        // Send success response
        $response_data = [
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => [
                'entity_type' => $entity_type,
                'entity_id' => $entity_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'task_id' => $task_id
            ]
        ];
        
        error_log("Sending response: " . json_encode($response_data));
        echo json_encode($response_data);

    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        error_log("Error occurred: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        throw $e;
    }

} catch (Exception $e) {
    // Rollback transaction if started
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }

    error_log("Status update error: " . $e->getMessage());
    
    // Send error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// End output buffer and flush
ob_end_flush();
?> 