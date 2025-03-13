<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../config/db_connect.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['projectId'])) {
        throw new Exception('Project ID is required');
    }

    $pdo->beginTransaction();
    
    // Update project
    $projectQuery = "UPDATE projects SET 
        title = :title,
        description = :description,
        project_type = :project_type,
        category_id = :category_id,
        start_date = :start_date,
        end_date = :end_date,
        assigned_to = :assigned_to,
        updated_at = NOW(),
        updated_by = :updated_by
    WHERE id = :project_id";
    
    $stmt = $pdo->prepare($projectQuery);
    $stmt->execute([
        ':title' => $data['projectTitle'],
        ':description' => $data['projectDescription'],
        ':project_type' => $data['projectType'],
        ':category_id' => $data['projectCategory'],
        ':start_date' => $data['startDate'],
        ':end_date' => $data['dueDate'],
        ':assigned_to' => $data['assignTo'],
        ':updated_by' => $_SESSION['user_id'],
        ':project_id' => $data['projectId']
    ]);
    
    // Log the update in project_history
    $historyQuery = "INSERT INTO project_history (
        project_id,
        action_type,
        old_value,
        new_value,
        changed_by,
        changed_at
    ) VALUES (
        :project_id,
        'update',
        :old_value,
        :new_value,
        :changed_by,
        NOW()
    )";
    
    $stmt = $pdo->prepare($historyQuery);
    $stmt->execute([
        ':project_id' => $data['projectId'],
        ':old_value' => json_encode($data['old_value'] ?? null),
        ':new_value' => json_encode($data),
        ':changed_by' => $_SESSION['user_id']
    ]);
    
    // Update stages
    foreach ($data['stages'] as $stage) {
        if (isset($stage['id'])) {
            // Update existing stage
            $stageQuery = "UPDATE project_stages SET 
                assigned_to = :assigned_to,
                start_date = :start_date,
                end_date = :end_date,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :stage_id";
            
            $stmt = $pdo->prepare($stageQuery);
            $stmt->execute([
                ':assigned_to' => $stage['assignTo'],
                ':start_date' => $stage['startDate'],
                ':end_date' => $stage['dueDate'],
                ':updated_by' => $_SESSION['user_id'],
                ':stage_id' => $stage['id']
            ]);
        } else {
            // Insert new stage
            // ... your existing stage creation code ...
        }
        
        // Handle substages similarly
        foreach ($stage['substages'] as $substage) {
            if (isset($substage['id'])) {
                // Update existing substage
                $substageQuery = "UPDATE project_substages SET 
                    title = :title,
                    assigned_to = :assigned_to,
                    start_date = :start_date,
                    end_date = :end_date,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :substage_id";
                
                $stmt = $pdo->prepare($substageQuery);
                $stmt->execute([
                    ':title' => $substage['title'],
                    ':assigned_to' => $substage['assignTo'],
                    ':start_date' => $substage['startDate'],
                    ':end_date' => $substage['dueDate'],
                    ':updated_by' => $_SESSION['user_id'],
                    ':substage_id' => $substage['id']
                ]);
            } else {
                // Insert new substage
                // ... your existing substage creation code ...
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Project updated successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update project: ' . $e->getMessage()
    ]);
}
exit; 