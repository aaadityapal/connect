<?php
// Test script to debug project data
header('Content-Type: application/json');

// Include database connection
$possible_paths = [
    __DIR__ . '/../config/db_connect.php',
    dirname(__DIR__) . '/config/db_connect.php',
    '../config/db_connect.php',
    '../../config/db_connect.php'
];

$db_connected = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_connected = true;
        break;
    }
}

if (!$db_connected) {
    echo json_encode(['error' => 'Database connection not found']);
    exit;
}

try {
    // Test query to check projects data
    $query = "SELECT id, title, project_type, status FROM projects ORDER BY id DESC LIMIT 10";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'projects' => $projects,
        'count' => count($projects)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>