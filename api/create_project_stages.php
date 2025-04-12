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

    // Get the user ID from the data, or use a default
    $userId = isset($data['user_id']) ? $data['user_id'] : ($_SESSION['user_id'] ?? 1);

    $pdo->beginTransaction();
    
    foreach ($data['stages'] as $stageIndex => $stage) {
        // Convert assignTo value 0 to NULL for database storage
        $stageAssignedTo = (!empty($stage['assignTo']) && $stage['assignTo'] !== '0') ? $stage['assignTo'] : null;
        
        // Insert stage
        $stageQuery = "INSERT INTO project_stages (
            project_id,
            stage_number,
            assigned_to,
            start_date,
            end_date,
            status,
            created_at,
            created_by,
            updated_by
        ) VALUES (
            :project_id,
            :stage_number,
            :assigned_to,
            :start_date,
            :end_date,
            'pending',
            NOW(),
            :created_by,
            :updated_by
        )";
        
        $stmt = $pdo->prepare($stageQuery);
        $stmt->execute([
            ':project_id' => $data['project_id'],
            ':stage_number' => $stageIndex + 1,
            ':assigned_to' => $stageAssignedTo,
            ':start_date' => $stage['startDate'],
            ':end_date' => $stage['dueDate'],
            ':created_by' => $userId,
            ':updated_by' => $userId
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
                    ':uploaded_by' => $userId
                ]);
            }
        }
        
        // Handle substages
        if (!empty($stage['substages'])) {
            foreach ($stage['substages'] as $substageIndex => $substage) {
                // Convert assignTo value 0 to NULL for database storage
                $substageAssignedTo = (!empty($substage['assignTo']) && $substage['assignTo'] !== '0') ? $substage['assignTo'] : null;
                
                $substageQuery = "INSERT INTO project_substages (
                    stage_id,
                    substage_number,
                    title,
                    assigned_to,
                    start_date,
                    end_date,
                    status,
                    created_at,
                    created_by,
                    substage_identifier,
                    drawing_number,
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
                    :created_by,
                    :substage_identifier,
                    :drawing_number,
                    :updated_by
                )";
                
                $substageIdentifier = "S{$stageIndex}SS{$substageIndex}";
                
                $stmt = $pdo->prepare($substageQuery);
                $stmt->execute([
                    ':stage_id' => $stageId,
                    ':substage_number' => $substageIndex + 1,
                    ':title' => $substage['title'],
                    ':assigned_to' => $substageAssignedTo,
                    ':start_date' => $substage['startDate'],
                    ':end_date' => $substage['dueDate'],
                    ':created_by' => $userId,
                    ':substage_identifier' => $substageIdentifier,
                    ':drawing_number' => $substage['drawingNumber'] ?? null,
                    ':updated_by' => $userId
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
                            ':uploaded_by' => $userId
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