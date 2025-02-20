<?php
header('Content-Type: application/json');
require_once '../config/db_connect.php';

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['stage_id']) || !isset($data['status'])) {
        throw new Exception('Missing required parameters');
    }

    $stage_id = $data['stage_id'];
    $status = $data['status'];
    
    // Validate status against enum values from database
    $valid_statuses = ['not_started', 'pending', 'in_progress', 'in_review', 
                       'completed', 'on_hold', 'cancelled', 'blocked', 'freezed', 'sent_to_client'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception('Invalid status value');
    }

    // First get the project_id for this stage
    $project_query = "SELECT project_id FROM project_stages WHERE id = ?";
    $project_stmt = $conn->prepare($project_query);
    $project_stmt->bind_param('i', $stage_id);
    $project_stmt->execute();
    $result = $project_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Stage not found');
    }
    
    $project_data = $result->fetch_assoc();
    $project_id = $project_data['project_id'];

    // Update the stage status
    $query = "UPDATE project_stages SET 
              status = ?, 
              updated_at = NOW() 
              WHERE id = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $status, $stage_id);
    
    if ($stmt->execute()) {
        // Log the activity
        $activity_query = "INSERT INTO project_activity_log 
            (project_id, stage_id, substage_id, activity_type, description, performed_by, performed_at) 
            VALUES (?, ?, NULL, 'stage_status_update', ?, ?, NOW())";
        
        $description = "Stage status updated to: " . $status;
        $performed_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null; // Assuming you have user session
        
        $log_stmt = $conn->prepare($activity_query);
        $log_stmt->bind_param('iisi', $project_id, $stage_id, $description, $performed_by);
        $log_stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update status');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>