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
    
    // Debug log
    error_log('Received data: ' . print_r($data, true));
    
    // Validate dates
    if (empty($data['startDate'])) {
        throw new Exception('Start date is required');
    }
    
    // Insert project
    $projectQuery = "INSERT INTO projects (
        title, description, project_type, category_id, 
        start_date, end_date, created_by, assigned_to, 
        status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'not_started', NOW())";
    
    $stmt = $conn->prepare($projectQuery);
    $stmt->bind_param("sssiisii", 
        $data['projectTitle'],
        $data['projectDescription'],
        $data['projectType'],
        $data['category_id'],
        $data['startDate'],
        $data['dueDate'],
        $_SESSION['user_id'],
        $data['assignTo']
    );
    
    $stmt->execute();
    $projectId = $conn->insert_id;
    error_log('Project created with ID: ' . $projectId);
    
    // Insert stages
    if (!empty($data['stages'])) {
        error_log('Processing ' . count($data['stages']) . ' stages');
        
        foreach ($data['stages'] as $stageIndex => $stage) {
            $stageQuery = "INSERT INTO project_stages (
                project_id, stage_number, assigned_to, 
                start_date, end_date, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'not_started', NOW())";
            
            $stmt = $conn->prepare($stageQuery);
            if (!$stmt) {
                throw new Exception("Stage prepare failed: " . $conn->error);
            }
            
            $stageNum = $stageIndex + 1;
            $stmt->bind_param("iiiss", 
                $projectId, 
                $stageNum,
                $stage['assignTo'],
                $stage['startDate'],
                $stage['endDate']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Stage execute failed: " . $stmt->error);
            }
            
            $stageId = $conn->insert_id;
            error_log("Created stage ID: " . $stageId);
            
            // Insert substages
            if (!empty($stage['substages'])) {
                error_log('Processing ' . count($stage['substages']) . ' substages for stage ' . $stageId);
                
                foreach ($stage['substages'] as $substageIndex => $substage) {
                    // Format dates properly
                    $substageStartDate = date('Y-m-d H:i:s', strtotime($substage['startDate']));
                    $substageEndDate = date('Y-m-d H:i:s', strtotime($substage['endDate']));
                    
                    $substageQuery = "INSERT INTO project_substages (
                        stage_id,
                        substage_number,
                        title,
                        assigned_to,
                        start_date,
                        end_date,
                        status,
                        created_at,
                        substage_identifier
                    ) VALUES (?, ?, ?, ?, ?, ?, 'not_started', NOW(), ?)";
                    
                    $stmt = $conn->prepare($substageQuery);
                    if (!$stmt) {
                        throw new Exception("Substage prepare failed: " . $conn->error);
                    }
                    
                    $substageNum = $substageIndex + 1;
                    $identifier = "S{$stageNum}.{$substageNum}";
                    
                    $stmt->bind_param("iisisss", 
                        $stageId,
                        $substageNum,
                        $substage['title'],
                        $substage['assignTo'],
                        $substageStartDate,
                        $substageEndDate,
                        $identifier
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Substage execute failed: " . $stmt->error . 
                            " Query: " . $substageQuery);
                    }
                    
                    error_log("Created substage with identifier: " . $identifier);
                }
            }
        }
    }
    
    // Log the activity
    $activityQuery = "INSERT INTO project_activity_log (
        project_id, activity_type, description, performed_by, performed_at
    ) VALUES (?, 'create', 'Project created with stages', ?, NOW())";
    
    $stmt = $conn->prepare($activityQuery);
    $stmt->bind_param("ii", $projectId, $_SESSION['user_id']);
    $stmt->execute();
    
    $conn->commit();
    echo json_encode([
        'status' => 'success', 
        'project_id' => $projectId,
        'debug' => [
            'stages_count' => count($data['stages'] ?? []),
            'project_data' => $data
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error in create_project.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
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