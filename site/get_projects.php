<?php
// Get construction projects from database
header('Content-Type: application/json');

try {
    // Include database connection
    require_once '../config/db_connect.php';
    
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    // Fetch only construction projects
    $query = "SELECT id, title, description, start_date, end_date, status, project_location, client_name 
              FROM projects 
              WHERE project_type = 'construction' 
              AND deleted_at IS NULL 
              ORDER BY title ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($projects)) {
        // If no results, try debugging - check if table exists and fetch all project types
        $debugQuery = "SELECT DISTINCT project_type FROM projects LIMIT 10";
        $debugStmt = $pdo->prepare($debugQuery);
        $debugStmt->execute();
        $types = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $formattedProjects = [];
    foreach ($projects as $row) {
        $formattedProjects[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'status' => $row['status'],
            'location' => $row['project_location'],
            'client_name' => $row['client_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formattedProjects,
        'count' => count($formattedProjects)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
