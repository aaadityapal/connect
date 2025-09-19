<?php
header('Content-Type: application/json');

// Check if required files exist
$files = [
    'config/db_connect.php',
    'api/get_recent_vendors.php'
];

$status = [
    'files' => [],
    'database' => false,
    'api' => false
];

foreach ($files as $file) {
    $status['files'][$file] = file_exists($file);
}

// Check database connection
if (file_exists('config/db_connect.php')) {
    try {
        require_once 'config/db_connect.php';
        $status['database'] = true;
        
        // Test query
        $stmt = $pdo->query("SELECT 1");
        $status['database_query'] = $stmt !== false;
    } catch (Exception $e) {
        $status['database_error'] = $e->getMessage();
    }
}

// Check API endpoint
if (file_exists('api/get_recent_vendors.php')) {
    $status['api_file'] = true;
}

echo json_encode($status, JSON_PRETTY_PRINT);
?>