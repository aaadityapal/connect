<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../config/db_connect.php';

try {
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
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $categories
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch categories: ' . $e->getMessage()
    ]);
}
exit;
?>