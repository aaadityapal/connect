<?php
/**
 * Get All Projects API
 * Fetches all active projects from the projects table
 * Used by payment entry edit modal to populate project dropdown
 */

session_start();
require_once __DIR__ . '/config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            project_type,
            description,
            status
        FROM projects
        WHERE status != 'deleted' AND deleted_at IS NULL
        ORDER BY title ASC
    ");
    $stmt->execute();
    $projects = $stmt->fetchAll();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'projects' => $projects,
        'total' => count($projects)
    ]);

} catch (PDOException $e) {
    error_log('Database Error in get_all_projects: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    exit;
} catch (Exception $e) {
    error_log('Error in get_all_projects: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
    exit;
}
?>
