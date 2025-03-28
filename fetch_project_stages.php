<?php
session_start();
require_once 'config/db_connect.php';

try {
    // Get stages that are not completed
    $stagesQuery = "SELECT ps.*, p.title as project_title 
                   FROM project_stages ps 
                   JOIN projects p ON ps.project_id = p.id 
                   WHERE ps.status != 'completed' 
                   AND ps.deleted_at IS NULL 
                   ORDER BY ps.end_date ASC";
    
    $stagesResult = $conn->query($stagesQuery);
    $pendingStages = [];
    
    while($stage = $stagesResult->fetch_assoc()) {
        $pendingStages[] = [
            'id' => $stage['id'],
            'project_title' => $stage['project_title'],
            'stage_number' => $stage['stage_number'],
            'status' => $stage['status'],
            'end_date' => $stage['end_date']
        ];
    }

    // Get substages that are not completed
    $substagesQuery = "SELECT pss.*, ps.stage_number, p.title as project_title 
                      FROM project_substages pss 
                      JOIN project_stages ps ON pss.stage_id = ps.id 
                      JOIN projects p ON ps.project_id = p.id 
                      WHERE pss.status != 'completed' 
                      AND pss.deleted_at IS NULL 
                      ORDER BY pss.end_date ASC";
    
    $substagesResult = $conn->query($substagesQuery);
    $pendingSubstages = [];
    
    while($substage = $substagesResult->fetch_assoc()) {
        $pendingSubstages[] = [
            'id' => $substage['id'],
            'project_title' => $substage['project_title'],
            'stage_number' => $substage['stage_number'],
            'substage_number' => $substage['substage_number'],
            'title' => $substage['title'],
            'status' => $substage['status'],
            'end_date' => $substage['end_date']
        ];
    }

    echo json_encode([
        'success' => true,
        'stages' => [
            'count' => count($pendingStages),
            'items' => $pendingStages
        ],
        'substages' => [
            'count' => count($pendingSubstages),
            'items' => $pendingSubstages
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 