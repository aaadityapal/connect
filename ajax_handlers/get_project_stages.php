<?php
// Include database connection
require_once '../config/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if project_id is provided
if (!isset($_GET['project_id']) || empty($_GET['project_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Project ID is required'
    ]);
    exit;
}

$projectId = intval($_GET['project_id']);

try {
    // Query to get project stages with assignee names
    $query = "
        SELECT ps.*, 
               u.username as assignee_name
        FROM project_stages ps
        LEFT JOIN users u ON ps.assigned_to = u.id
        WHERE ps.project_id = :project_id 
        AND ps.deleted_at IS NULL
        ORDER BY ps.stage_number ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':project_id', $projectId, PDO::PARAM_INT);
    $stmt->execute();
    
    $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each stage, get its substages
    foreach ($stages as &$stage) {
        $substagesQuery = "
            SELECT pss.*, 
                   u.username as assignee_name
            FROM project_substages pss
            LEFT JOIN users u ON pss.assigned_to = u.id
            WHERE pss.stage_id = :stage_id 
            AND pss.deleted_at IS NULL
            ORDER BY pss.substage_number ASC
        ";
        
        $substagesStmt = $pdo->prepare($substagesQuery);
        $substagesStmt->bindParam(':stage_id', $stage['id'], PDO::PARAM_INT);
        $substagesStmt->execute();
        
        $substages = $substagesStmt->fetchAll(PDO::FETCH_ASSOC);
        $stage['sub_stages'] = $substages;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $stages
    ]);
    
} catch (PDOException $e) {
    // Log error
    error_log("Error fetching project stages: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?> 