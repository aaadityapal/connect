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
    // Fetch distinct project types/categories that have payment entries
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            m.project_type_category as id,
            m.project_type_category as title
        FROM tbl_payment_entry_master_records m
        WHERE m.project_type_category IS NOT NULL 
            AND m.project_type_category != ''
        ORDER BY m.project_type_category ASC
    ");
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

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