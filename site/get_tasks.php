<?php
// Get construction site tasks from database
header('Content-Type: application/json');
session_start(); // Start session to access user_id for filtering

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

    // Check for my_tasks filter
    $myTasksOnly = isset($_GET['my_tasks']) && $_GET['my_tasks'] === 'true';
    $currentUserId = $_SESSION['user_id'] ?? 0;

    // Fetch tasks for the specified project, including creator info
    $query = "SELECT 
                t.id,
                t.project_id,
                t.title,
                t.description,
                t.supervisor_notes,
                t.start_date,
                t.end_date,
                t.status,
                t.assign_to,
                t.assigned_user_id,
                t.images,
                t.created_by,
                t.updated_by,
                t.created_at,
                t.updated_at,
                c.username as creator_name,
                c.role as creator_role,
                c.id as creator_id
              FROM construction_site_tasks t
              LEFT JOIN users c ON t.created_by = c.id
              WHERE t.project_id = ? 
              AND t.deleted_at IS NULL";

    $params = [$projectId];

    if ($myTasksOnly) {
        $query .= " AND t.assigned_user_id = ?";
        $params[] = $currentUserId;
    }

    $query .= " ORDER BY t.start_date ASC, t.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
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
            'id' => (int) $task['id'],
            'project_id' => (int) $task['project_id'],
            'title' => $task['title'],
            'description' => $task['description'],
            'supervisor_notes' => $task['supervisor_notes'],
            'start_date' => $task['start_date'],
            'end_date' => $task['end_date'],
            'status' => $task['status'],
            'assign_to' => $task['assign_to'],
            'assigned_user_id' => $task['assigned_user_id'] ? (int) $task['assigned_user_id'] : null,
            'images' => $images,
            'created_by' => $task['created_by'] ? (int) $task['created_by'] : null,
            'creator_name' => $task['creator_name'],
            'creator_role' => $task['creator_role'],
            'updated_by' => $task['updated_by'] ? (int) $task['updated_by'] : null,
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