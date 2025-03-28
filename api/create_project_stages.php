<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../config/db_connect.php';

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['project_id']) || !isset($data['stages'])) {
        throw new Exception('Missing required data');
    }

    $pdo->beginTransaction();
    
    foreach ($data['stages'] as $stageIndex => $stage) {
        // Insert stage
        $stageQuery = "INSERT INTO project_stages (
            project_id,
            stage_number,
            assigned_to,
            start_date,
            end_date,
            status,
            created_at,
            updated_by
        ) VALUES (
            :project_id,
            :stage_number,
            :assigned_to,
            :start_date,
            :end_date,
            'pending',
            NOW(),
            :updated_by
        )";
        
        $stmt = $pdo->prepare($stageQuery);
        $stmt->execute([
            ':project_id' => $data['project_id'],
            ':stage_number' => $stageIndex + 1,
            ':assigned_to' => $stage['assignTo'],
            ':start_date' => $stage['startDate'],
            ':end_date' => $stage['dueDate'],
            ':updated_by' => $_SESSION['user_id'] ?? 1
        ]);
        
        $stageId = $pdo->lastInsertId();
        
        // Handle stage files
        if (!empty($stage['files'])) {
            foreach ($stage['files'] as $file) {
                $stageFileQuery = "INSERT INTO stage_files (
                    stage_id,
                    file_name,
                    file_path,
                    original_name,
                    file_type,
                    file_size,
                    uploaded_by,
                    uploaded_at
                ) VALUES (
                    :stage_id,
                    :file_name,
                    :file_path,
                    :original_name,
                    :file_type,
                    :file_size,
                    :uploaded_by,
                    NOW()
                )";
                
                $stmt = $pdo->prepare($stageFileQuery);
                $stmt->execute([
                    ':stage_id' => $stageId,
                    ':file_name' => $file['name'],
                    ':file_path' => $file['path'],
                    ':original_name' => $file['originalName'],
                    ':file_type' => $file['type'],
                    ':file_size' => $file['size'],
                    ':uploaded_by' => $_SESSION['user_id'] ?? 1
                ]);
            }
        }
        
        // Handle substages
        if (!empty($stage['substages'])) {
            foreach ($stage['substages'] as $substageIndex => $substage) {
                $substageQuery = "INSERT INTO project_substages (
                    stage_id,
                    substage_number,
                    title,
                    assigned_to,
                    start_date,
                    end_date,
                    status,
                    created_at,
                    substage_identifier,
                    updated_by
                ) VALUES (
                    :stage_id,
                    :substage_number,
                    :title,
                    :assigned_to,
                    :start_date,
                    :end_date,
                    'pending',
                    NOW(),
                    :substage_identifier,
                    :updated_by
                )";
                
                $substageIdentifier = "S{$stageIndex}SS{$substageIndex}";
                
                $stmt = $pdo->prepare($substageQuery);
                $stmt->execute([
                    ':stage_id' => $stageId,
                    ':substage_number' => $substageIndex + 1,
                    ':title' => $substage['title'],
                    ':assigned_to' => $substage['assignTo'],
                    ':start_date' => $substage['startDate'],
                    ':end_date' => $substage['dueDate'],
                    ':substage_identifier' => $substageIdentifier,
                    ':updated_by' => $_SESSION['user_id'] ?? 1
                ]);
                
                $substageId = $pdo->lastInsertId();
                
                // Handle substage files
                if (!empty($substage['files'])) {
                    foreach ($substage['files'] as $file) {
                        $substageFileQuery = "INSERT INTO substage_files (
                            substage_id,
                            file_name,
                            file_path,
                            type,
                            uploaded_by,
                            uploaded_at,
                            status,
                            created_at
                        ) VALUES (
                            :substage_id,
                            :file_name,
                            :file_path,
                            :type,
                            :uploaded_by,
                            NOW(),
                            'active',
                            NOW()
                        )";
                        
                        $stmt = $pdo->prepare($substageFileQuery);
                        $stmt->execute([
                            ':substage_id' => $substageId,
                            ':file_name' => $file['name'],
                            ':file_path' => $file['path'],
                            ':type' => $file['type'],
                            ':uploaded_by' => $_SESSION['user_id'] ?? 1
                        ]);
                    }
                }
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Stages and substages created successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to create stages: ' . $e->getMessage()
    ]);
}
exit; 