<?php
session_start();
require_once '../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (empty($search)) {
        echo json_encode(['success' => true, 'projects' => []]);
        exit();
    }

    $query = "SELECT id, title, description, project_type, category_id, start_date, end_date, created_by, assigned_to, status, client_name, client_address, project_location, plot_area, contact_number 
              FROM projects 
              WHERE deleted_at IS NULL 
              AND status NOT IN ('completed', 'cancelled')
              AND (title LIKE :search_start OR title LIKE :search_any)
              ORDER BY 
                CASE WHEN title LIKE :search_start_order THEN 1 ELSE 2 END,
                title ASC
              LIMIT 15";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'search_start' => "$search%",
        'search_any' => "% $search%",
        'search_start_order' => "$search%"
    ]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'projects' => $projects
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
}
?>
