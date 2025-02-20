<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['stageId']) || !isset($data['userId'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    $stage_id = $data['stageId'];
    $new_user_id = $data['userId'];
    $message = $data['message'] ?? '';
    $current_status = $data['currentStatus'] ?? 'pending';
    $current_user_id = $_SESSION['user_id'];

    // First, verify the stage exists and get task_id
    $query = "SELECT task_id FROM task_stages WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $stage_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Stage not found');
    }
    
    $task_id = $result->fetch_assoc()['task_id'];

    // Update stage assignment
    $update_query = "
        UPDATE task_stages 
        SET assigned_to = ?,
            last_updated = NOW(),
            updated_by = ?
        WHERE id = ?
    ";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('iii', $new_user_id, $current_user_id, $stage_id);
    $stmt->execute();

    // Log the forward action
    $log_query = "
        INSERT INTO task_stage_history 
        (stage_id, task_id, action_type, old_status, new_status, old_user_id, new_user_id, action_by, message, created_at)
        VALUES (?, ?, 'forward', ?, ?, ?, ?, ?, ?, NOW())
    ";
    
    $action_type = 'forward';
    $stmt = $conn->prepare($log_query);
    $stmt->bind_param('iissiiis', 
        $stage_id,
        $task_id,
        $current_status,
        $current_status,
        $current_user_id,
        $new_user_id,
        $current_user_id,
        $message
    );
    $stmt->execute();

    // Create notification for new user
    $notify_query = "
        INSERT INTO notifications 
        (user_id, type, message, reference_id, reference_type, created_at)
        VALUES (?, 'stage_forward', ?, ?, 'stage', NOW())
    ";
    
    $notification_message = "A stage has been forwarded to you" . ($message ? ": $message" : "");
    $stmt = $conn->prepare($notify_query);
    $stmt->bind_param('isi', 
        $new_user_id,
        $notification_message,
        $stage_id
    );
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Stage forwarded successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Error in forward_stage.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to forward stage: ' . $e->getMessage()
    ]);
}

$conn->close(); 