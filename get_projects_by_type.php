<?php
// Get projects by type API
// This file fetches projects from the Projects table based on project type

header('Content-Type: application/json');

// Get project type from query parameter
$projectType = isset($_GET['projectType']) ? $_GET['projectType'] : '';

// Validate input
if (empty($projectType)) {
    echo json_encode([
        'success' => false,
        'message' => 'Project type is required'
    ]);
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

try {
    // Map project type values to database values if needed
    $typeMapping = [
        'architecture' => 'Architecture',
        'interior' => 'Interior',
        'construction' => 'Construction'
    ];

    $dbProjectType = isset($typeMapping[$projectType]) ? $typeMapping[$projectType] : $projectType;

    // Prepare and execute query using PDO
    // First, try to fetch projects with or without status filter
    $query = "SELECT id, title, description, project_type FROM projects WHERE project_type = ? ORDER BY title ASC";
    $stmt = $pdo->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed");
    }

    $stmt->execute([$dbProjectType]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no projects found, try case-insensitive search
    if (empty($projects)) {
        $query = "SELECT id, title, description, project_type FROM projects WHERE LOWER(project_type) = LOWER(?) ORDER BY title ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$dbProjectType]);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'projects' => $projects,
        'count' => count($projects)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
