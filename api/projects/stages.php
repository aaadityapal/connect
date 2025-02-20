<?php
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_connect.php';

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Clear any previous output before processing
    ob_clean();

    // Check if the database connection is established
    if (!isset($pdo)) {
        throw new Exception("Database connection not established");
    }

    $query = "
        SELECT 
            ps.*, 
            p.title as project_title 
        FROM project_stages ps
        LEFT JOIN projects p ON ps.project_id = p.id
        LIMIT 10
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $stages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure clean output with proper headers
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type');
    
    echo json_encode([
        'success' => true,
        'data' => $stages,
        'count' => count($stages)
    ]);

} catch (Exception $e) {
    // Clear any previous output
    ob_clean();
    
    error_log("Error in stages.php: " . $e->getMessage());
    http_response_code(500);
    
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type');
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();