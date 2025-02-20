<?php
require_once '../../config/db_connect.php';
require_once '../../includes/auth_check.php';

header('Content-Type: application/json');

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required_fields = ['project_id', 'stage_id', 'substage_id', 'activity_type', 'description'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }

    // Get current user ID from session
    $performed_by = $_SESSION['user_id'];
    
    // Current timestamp
    $performed_at = date('Y-m-d H:i:s');

    // Insert into activity log
    $sql = "INSERT INTO project_activity_log 
            (project_id, stage_id, substage_id, activity_type, description, performed_by, performed_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "iiissss",
        $data['project_id'],
        $data['stage_id'],
        $data['substage_id'],
        $data['activity_type'],
        $data['description'],
        $performed_by,
        $performed_at
    );

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Activity logged successfully'
        ]);
    } else {
        throw new Exception("Failed to log activity: " . $stmt->error);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$stmt->close();
$conn->close();
?>