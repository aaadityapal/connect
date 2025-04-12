<?php
session_start();
require_once 'config/db_connect.php';

try {
    if (!isset($_GET['project_id'])) {
        throw new Exception('Project ID is required');
    }

    $projectId = $_GET['project_id'];

    // First fetch project details
    $projectQuery = "SELECT p.*, 
                    p.updated_at as project_updated_at,
                    u1.username as assigned_to_name,
                    u2.username as created_by_name,
                    u3.username as updated_by_name
                    FROM projects p
                    LEFT JOIN users u1 ON p.assigned_to = u1.id
                    LEFT JOIN users u2 ON p.created_by = u2.id
                    LEFT JOIN users u3 ON p.updated_by = u3.id
                    WHERE p.id = :project_id AND p.deleted_at IS NULL";
    
    $stmt = $pdo->prepare($projectQuery);
    $stmt->execute(['project_id' => $projectId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        throw new Exception('Project not found');
    }

    // Add logic to determine if update is recent (within last 24 hours)
    if ($project) {
        $project['is_recent_update'] = false;
        if ($project['updated_at']) {
            $updateTime = strtotime($project['updated_at']);
            $currentTime = time();
            $hoursDiff = ($currentTime - $updateTime) / 3600;
            $project['is_recent_update'] = $hoursDiff <= 24;
        }
    }

    // Fetch stages with assigned user information
    $stagesQuery = "SELECT ps.*, 
                   u.username as stage_assigned_to_name
                   FROM project_stages ps
                   LEFT JOIN users u ON ps.assigned_to = u.id
                   WHERE ps.project_id = :project_id 
                   AND ps.deleted_at IS NULL 
                   ORDER BY ps.stage_number";
    
    $stmt = $pdo->prepare($stagesQuery);
    $stmt->execute(['project_id' => $projectId]);
    $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch substages for each stage
    foreach ($stages as &$stage) {
        $substagesQuery = "SELECT ss.*, 
                          u.username as substage_assigned_to_name,
                          ss.drawing_number
                          FROM project_substages ss
                          LEFT JOIN users u ON ss.assigned_to = u.id
                          WHERE ss.stage_id = :stage_id 
                          AND ss.deleted_at IS NULL 
                          ORDER BY ss.substage_number";
        
        $stmt = $pdo->prepare($substagesQuery);
        $stmt->execute(['stage_id' => $stage['id']]);
        $substages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add this new code to fetch files for each substage
        foreach ($substages as &$substage) {
            $filesQuery = "SELECT id, substage_id, file_name, file_path, type, 
                          uploaded_by, uploaded_at, status, created_at, updated_at 
                          FROM substage_files 
                          WHERE substage_id = :substage_id 
                          AND deleted_at IS NULL 
                          ORDER BY created_at DESC";
            
            $stmt = $pdo->prepare($filesQuery);
            $stmt->execute(['substage_id' => $substage['id']]);
            $substage['files'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $stage['substages'] = $substages;
    }

    $project['stages'] = $stages;

    echo json_encode([
        'success' => true,
        'project' => $project
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 