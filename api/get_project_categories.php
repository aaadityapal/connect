<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../config/db_connect.php';

error_log("get_project_categories.php was accessed");

try {
    // Add connection check
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Log the query for debugging
    error_log("Executing categories query");
    
    $query = "SELECT 
        id,
        name,
        description,
        parent_id
    FROM project_categories 
    WHERE deleted_at IS NULL 
    ORDER BY id ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    // Check if query was successful
    if (!$stmt) {
        throw new Exception('Query failed: ' . print_r($pdo->errorInfo(), true));
    }
    
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log the results for debugging
    error_log("Found " . count($categories) . " categories");
    error_log("Categories data: " . json_encode($categories));
    
    echo json_encode([
        'status' => 'success',
        'data' => $categories
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_project_categories.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch categories: ' . $e->getMessage()
    ]);
}
exit;
?>