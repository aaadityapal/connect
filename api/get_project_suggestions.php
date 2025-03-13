<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../config/db_connect.php';

try {
    // First get projects with basic info
    $query = "SELECT 
        p.id,
        p.title,
        p.description,
        p.project_type,
        p.category_id,
        pc.name as category_name,
        pc.parent_id as category_parent_id,
        p.start_date,
        p.end_date,
        p.assigned_to
    FROM projects p
    LEFT JOIN project_categories pc ON p.category_id = pc.id
    WHERE p.deleted_at IS NULL
    ORDER BY p.created_at DESC
    LIMIT 10";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each project, get its stages and substages
    foreach ($projects as &$project) {
        // Get stages
        $stageQuery = "SELECT 
            id,
            stage_number,
            assigned_to,
            start_date,
            end_date,
            status
        FROM project_stages 
        WHERE project_id = :project_id 
        AND deleted_at IS NULL 
        ORDER BY stage_number";
        
        $stageStmt = $pdo->prepare($stageQuery);
        $stageStmt->execute([':project_id' => $project['id']]);
        $stages = $stageStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For each stage, get its substages and files
        foreach ($stages as &$stage) {
            // Get substages
            $substageQuery = "SELECT 
                id,
                substage_number,
                title,
                assigned_to,
                start_date,
                end_date,
                status,
                substage_identifier
            FROM project_substages 
            WHERE stage_id = :stage_id 
            AND deleted_at IS NULL 
            ORDER BY substage_number";
            
            $substageStmt = $pdo->prepare($substageQuery);
            $substageStmt->execute([':stage_id' => $stage['id']]);
            $stage['substages'] = $substageStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get stage files
            $stageFileQuery = "SELECT 
                id,
                file_name,
                file_path,
                original_name,
                file_type,
                file_size
            FROM stage_files 
            WHERE stage_id = :stage_id";
            
            $stageFileStmt = $pdo->prepare($stageFileQuery);
            $stageFileStmt->execute([':stage_id' => $stage['id']]);
            $stage['files'] = $stageFileStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get substage files
            foreach ($stage['substages'] as &$substage) {
                $substageFileQuery = "SELECT 
                    id,
                    file_name,
                    file_path,
                    type
                FROM substage_files 
                WHERE substage_id = :substage_id 
                AND deleted_at IS NULL";
                
                $substageFileStmt = $pdo->prepare($substageFileQuery);
                $substageFileStmt->execute([':substage_id' => $substage['id']]);
                $substage['files'] = $substageFileStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($substage); // Clear reference
        }
        unset($stage); // Clear reference
        
        $project['stages'] = $stages;
    }
    unset($project); // Clear reference
    
    echo json_encode([
        'status' => 'success',
        'data' => $projects
    ]);
    
} catch (Exception $e) {
    error_log('Error fetching projects: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch project suggestions: ' . $e->getMessage()
    ]);
}
exit; 