<?php
session_start();
require_once '../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
    
    if ($project_id <= 0) {
        echo json_encode(['success' => true, 'stages' => []]);
        exit();
    }

    $query = "SELECT id, project_id, stage_number, assigned_to, start_date, end_date, status 
              FROM project_stages 
              WHERE project_id = :project_id 
              AND deleted_at IS NULL 
              ORDER BY CAST(REGEXP_REPLACE(stage_number, '[^0-9]', '') AS UNSIGNED) ASC, stage_number ASC";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute(['project_id' => $project_id]);
    $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stages' => $stages
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
}
?>
