<?php
session_start();
require_once '../config/db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not authenticated'
    ]);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Debug log received data
    error_log('Received data: ' . print_r($data, true));
    
    // Validate dates
    if (empty($data['startDate'])) {
        throw new Exception('Start date is required');
    }
    
    // Insert into projects table
    $projectQuery = "INSERT INTO projects (
        title, description, project_type, category_id, 
        start_date, end_date, created_by, assigned_to, 
        status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'not_started', NOW())";
    
    $stmt = $conn->prepare($projectQuery);
    
    // Debug log the dates
    error_log('Start Date: ' . $data['startDate']);
    error_log('Due Date: ' . $data['dueDate']);
    
    $stmt->bind_param("sssissii", 
        $data['projectTitle'],
        $data['projectDescription'],
        $data['projectType'],
        $data['category_id'],
        $data['startDate'],  // Use the date directly
        $data['dueDate'],    // Use the date directly
        $_SESSION['user_id'],
        $data['assignTo']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Error executing query: " . $stmt->error);
    }
    
    $projectId = $conn->insert_id;
    
    // Log project creation in activity log
    $activityQuery = "INSERT INTO project_activity_log (
        project_id, activity_type, description, performed_by, performed_at
    ) VALUES (?, 'create', 'Project created', ?, NOW())";
    
    $stmt = $conn->prepare($activityQuery);
    $stmt->bind_param("ii", $projectId, $_SESSION['user_id']);
    $stmt->execute();
    
    // Log in project history
    $historyQuery = "INSERT INTO project_history (
        project_id, action_type, old_value, new_value, changed_by, changed_at
    ) VALUES (?, 'create', NULL, ?, ?, NOW())";
    
    $historyData = json_encode([
        'title' => $data['projectTitle'],
        'description' => $data['projectDescription'],
        'project_type' => $data['projectType'],
        'category_id' => $data['category_id'],
        'start_date' => $data['startDate'],
        'end_date' => $data['dueDate'],
        'assigned_to' => $data['assignTo']
    ]);
    
    $stmt = $conn->prepare($historyQuery);
    $stmt->bind_param("isi", $projectId, $historyData, $_SESSION['user_id']);
    $stmt->execute();
    
    // Insert stages
    if (!empty($data['stages'])) {
        foreach ($data['stages'] as $stageIndex => $stage) {
            $stageStartDate = date('Y-m-d H:i:s', strtotime($stage['startDate']));
            $stageDueDate = date('Y-m-d H:i:s', strtotime($stage['endDate']));
            
            $stageQuery = "INSERT INTO project_stages (
                project_id, stage_number, assigned_to, 
                start_date, end_date, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'not_started', NOW())";
            
            $stmt = $conn->prepare($stageQuery);
            $stageNum = $stageIndex + 1;
            $stmt->bind_param("iiiss", 
                $projectId, 
                $stageNum,
                $stage['assignTo'],
                $stageStartDate,
                $stageDueDate
            );
            $stmt->execute();
            $stageId = $conn->insert_id;
            
            // Log stage creation in activity log
            $activityQuery = "INSERT INTO project_activity_log (
                project_id, stage_id, activity_type, description, 
                performed_by, performed_at
            ) VALUES (?, ?, 'create', 'Stage created', ?, NOW())";
            
            $stmt = $conn->prepare($activityQuery);
            $stmt->bind_param("iii", $projectId, $stageId, $_SESSION['user_id']);
            $stmt->execute();
            
            // Insert substages if any
            if (!empty($stage['substages'])) {
                foreach ($stage['substages'] as $substageIndex => $substage) {
                    $substageStartDate = date('Y-m-d H:i:s', strtotime($substage['startDate']));
                    $substageDueDate = date('Y-m-d H:i:s', strtotime($substage['endDate']));
                    
                    $substageQuery = "INSERT INTO project_substages (
                        stage_id, substage_number, title, assigned_to,
                        start_date, end_date, status, created_at,
                        substage_identifier
                    ) VALUES (?, ?, ?, ?, ?, ?, 'not_started', NOW(), ?)";
                    
                    $stmt = $conn->prepare($substageQuery);
                    $substageNum = $substageIndex + 1;
                    $identifier = "S{$stageNum}.{$substageNum}";
                    $stmt->bind_param("iisssss", 
                        $stageId,
                        $substageNum,
                        $substage['title'],
                        $substage['assignTo'],
                        $substageStartDate,
                        $substageDueDate,
                        $identifier
                    );
                    $stmt->execute();
                    $substageId = $conn->insert_id;
                    
                    // Log substage creation in activity log
                    $activityQuery = "INSERT INTO project_activity_log (
                        project_id, stage_id, substage_id, activity_type,
                        description, performed_by, performed_at
                    ) VALUES (?, ?, ?, 'create', 'Substage created', ?, NOW())";
                    
                    $stmt = $conn->prepare($activityQuery);
                    $stmt->bind_param("iiii", 
                        $projectId, 
                        $stageId, 
                        $substageId, 
                        $_SESSION['user_id']
                    );
                    $stmt->execute();
                }
            }
        }
    }
    
    $conn->commit();
    echo json_encode([
        'status' => 'success', 
        'project_id' => $projectId,
        'debug' => [
            'start_date' => $data['startDate'],
            'due_date' => $data['dueDate']
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error in create_project.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'received_data' => $data ?? null,
            'error_details' => $e->getTraceAsString()
        ]
    ]);
}

$conn->close();
?> 