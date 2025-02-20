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

if (!isset($data['substageId']) || !isset($data['userId'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    $substage_id = $data['substageId'];
    $new_user_id = $data['userId'];
    $message = $data['message'] ?? '';
    $current_status = $data['currentStatus'] ?? 'pending';
    $current_user_id = $_SESSION['user_id'];

    // First, verify the substage exists and get stage_id and task_id
    $query = "
        SELECT s.stage_id, ts.task_id 
        FROM substages s
        JOIN task_stages ts ON s.stage_id = ts.id
        WHERE s.id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $substage_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Substage not found');
    }
    
    $row = $result->fetch_assoc();
    $stage_id = $row['stage_id'];
    $task_id = $row['task_id'];

    // Update substage assignment
    $update_query = "
        UPDATE substages 
        SET assigned_to = ?,
            last_updated = NOW(),
            updated_by = ?
        WHERE id = ?
    ";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('iii', $new_user_id, $current_user_id, $substage_id);
    $stmt->execute();

    // Log the forward action
    $log_query = "
        INSERT INTO task_substage_history 
        (substage_id, stage_id, task_id, action_type, old_status, new_status, old_user_id, new_user_id, action_by, message, created_at)
        VALUES (?, ?, ?, 'forward', ?, ?, ?, ?, ?, ?, NOW())
    ";
    
    $stmt = $conn->prepare($log_query);
    $stmt->bind_param('iiissiiis', 
        $substage_id,
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
        VALUES (?, 'substage_forward', ?, ?, 'substage', NOW())
    ";
    
    $notification_message = "A substage has been forwarded to you" . ($message ? ": $message" : "");
    $stmt = $conn->prepare($notify_query);
    $stmt->bind_param('isi', 
        $new_user_id,
        $notification_message,
        $substage_id
    );
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Substage forwarded successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Error in forward_substage.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to forward substage: ' . $e->getMessage()
    ]);
}

$conn->close(); 