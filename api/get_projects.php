<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once '../config/db_connect.php';

// Check if database connection exists
if (!isset($pdo)) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection not available'
    ]);
    exit;
}

// Get the request data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['project_type']) || empty($input['project_type'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Project type is required'
    ]);
    exit;
}

$projectType = $input['project_type'];

try {
    // Fetch projects from the database based on project type
    $stmt = $pdo->prepare("SELECT id, title FROM projects WHERE project_type = ? ORDER BY title ASC");
    $stmt->execute([$projectType]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'projects' => $projects,
        'count' => count($projects)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching projects: ' . $e->getMessage()
    ]);
}
?>