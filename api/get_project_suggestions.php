<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../config/db_connect.php';

try {
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
    
    // Add debug logging
    error_log('Fetched projects: ' . json_encode($projects));
    
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