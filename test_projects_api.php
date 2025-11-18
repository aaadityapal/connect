<?php
// Test API for debugging
// Check what's in the projects table

header('Content-Type: application/json');

require_once 'config/db_connect.php';

try {
    // Get all projects
    $query = "SELECT id, title, description, project_type FROM projects LIMIT 20";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $allProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get projects by type
    $architectureQuery = "SELECT id, title, description, project_type FROM projects WHERE project_type = 'Architecture' OR LOWER(project_type) = 'architecture' LIMIT 20";
    $stmt = $pdo->prepare($architectureQuery);
    $stmt->execute();
    $architectureProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get project types
    $typesQuery = "SELECT DISTINCT project_type FROM projects";
    $stmt = $pdo->prepare($typesQuery);
    $stmt->execute();
    $projectTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'all_projects_count' => count($allProjects),
        'all_projects' => $allProjects,
        'architecture_projects_count' => count($architectureProjects),
        'architecture_projects' => $architectureProjects,
        'project_types' => $projectTypes
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
