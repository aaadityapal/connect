<?php
session_start();
require_once 'config/db_connect.php';

try {
    // Get current date
    $currentDate = date('Y-m-d');
    
    // Query to get all non-deleted projects including overdue ones
    $query = "SELECT p.*, 
              p.project_type,
              u.username as assigned_to_name,
              DATEDIFF(CURRENT_DATE, p.end_date) as days_overdue
              FROM projects p 
              LEFT JOIN users u ON p.assigned_to = u.id 
              WHERE p.deleted_at IS NULL 
              AND p.status NOT IN ('completed', 'cancelled')
              ORDER BY p.end_date ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Query to get pending stages
    $stagesQuery = "SELECT ps.*, p.title as project_title 
                   FROM project_stages ps
                   JOIN projects p ON ps.project_id = p.id
                   WHERE ps.status NOT IN ('completed', 'cancelled', 'blocked', 'freezed')
                   AND ps.deleted_at IS NULL
                   ORDER BY ps.end_date ASC";
    
    $stagesStmt = $pdo->prepare($stagesQuery);
    $stagesStmt->execute();
    $pendingStages = $stagesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Query to get pending substages
    $substagesQuery = "SELECT pss.*, ps.stage_number, p.title as project_title 
                      FROM project_substages pss
                      JOIN project_stages ps ON pss.stage_id = ps.id
                      JOIN projects p ON ps.project_id = p.id
                      WHERE pss.status NOT IN ('completed', 'cancelled', 'blocked', 'freezed')
                      AND pss.deleted_at IS NULL
                      ORDER BY pss.end_date ASC";
    
    $substagesStmt = $pdo->prepare($substagesQuery);
    $substagesStmt->execute();
    $pendingSubstages = $substagesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter overdue projects
    $overdue_projects = array_filter($projects, function($project) use ($currentDate) {
        return $project['end_date'] < $currentDate && 
               !in_array($project['status'], ['completed', 'cancelled']);
    });
    
    // Count projects by status
    $total_projects = count($projects);
    $in_progress = 0;
    $completed = 0;
    
    foreach ($projects as $project) {
        if ($project['status'] === 'in_progress') {
            $in_progress++;
        } else if ($project['status'] === 'completed') {
            $completed++;
        }
    }
    
    // Format stages data for tooltip
    $formattedStages = array_map(function($stage) {
        return [
            'project_title' => $stage['project_title'],
            'stage_number' => $stage['stage_number'],
            'status' => $stage['status'],
            'end_date' => $stage['end_date'],
            'days_remaining' => ceil((strtotime($stage['end_date']) - time()) / (60 * 60 * 24))
        ];
    }, $pendingStages);
    
    // Format substages data for tooltip
    $formattedSubstages = array_map(function($substage) {
        return [
            'project_title' => $substage['project_title'],
            'stage_number' => $substage['stage_number'],
            'substage_number' => $substage['substage_number'],
            'status' => $substage['status'],
            'end_date' => $substage['end_date'],
            'days_remaining' => ceil((strtotime($substage['end_date']) - time()) / (60 * 60 * 24))
        ];
    }, $pendingSubstages);
    
    echo json_encode([
        'success' => true,
        'projects' => array_map(function($project) {
            return [
                'id' => $project['id'],
                'title' => $project['title'],
                'project_type' => $project['project_type'],
                'start_date' => $project['start_date'],
                'end_date' => $project['end_date'],
                'status' => $project['status']
            ];
        }, $projects),
        'overdue_projects' => array_values($overdue_projects),
        'stats' => [
            'total' => $total_projects,
            'in_progress' => $in_progress,
            'completed' => $completed,
            'overdue' => count($overdue_projects),
            'pending_stages' => count($pendingStages),
            'pending_substages' => count($pendingSubstages)
        ],
        'pending_stages' => $formattedStages,
        'pending_substages' => $formattedSubstages
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch projects'
    ]);
}
?> 