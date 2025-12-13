<?php
// Get construction site tasks from database
header('Content-Type: application/json');

try {
    // Include database connection
    require_once '../config/db_connect.php';
    
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    // Get project_id from query parameter
    $projectId = $_GET['project_id'] ?? null;
    
    if (!$projectId) {
        throw new Exception('Project ID is required');
    }
    
    // Fetch tasks for the specified project
    $query = "SELECT 
                id,
                project_id,
                title,
                description,
                start_date,
                end_date,
                status,
                assign_to,
                assigned_user_id,
                images,
                created_by,
                updated_by,
                created_at,
                updated_at
              FROM construction_site_tasks
              WHERE project_id = ? 
              AND deleted_at IS NULL
              ORDER BY start_date ASC, created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$projectId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedTasks = [];
    foreach ($tasks as $task) {
        // Parse images if stored as JSON
        $images = [];
        if (!empty($task['images'])) {
            $decoded = json_decode($task['images'], true);
            $images = is_array($decoded) ? $decoded : [$task['images']];
        }
        
        $formattedTasks[] = [
            'id' => (int)$task['id'],
            'project_id' => (int)$task['project_id'],
            'title' => $task['title'],
            'description' => $task['description'],
            'start_date' => $task['start_date'],
            'end_date' => $task['end_date'],
            'status' => $task['status'],
            'assign_to' => $task['assign_to'],
            'assigned_user_id' => $task['assigned_user_id'] ? (int)$task['assigned_user_id'] : null,
            'images' => $images,
            'created_by' => $task['created_by'] ? (int)$task['created_by'] : null,
            'updated_by' => $task['updated_by'] ? (int)$task['updated_by'] : null,
            'created_at' => $task['created_at'],
            'updated_at' => $task['updated_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formattedTasks,
        'count' => count($formattedTasks)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
