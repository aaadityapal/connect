<?php
// Include database connection
require_once 'config/db_connect.php';

// Turn on error reporting for this debug file
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check for POST request to keep this secure
$isPostRequest = ($_SERVER['REQUEST_METHOD'] === 'POST');

// Set content type to JSON
header('Content-Type: application/json');

// Function to get table structure
function getTableStructure($pdo, $tableName) {
    try {
        // Check if table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        $tableExists = $stmt->fetch(PDO::FETCH_NUM);
        
        if (!$tableExists) {
            return [
                'exists' => false,
                'message' => "Table '{$tableName}' does not exist"
            ];
        }
        
        // Get table structure
        $stmt = $pdo->prepare("DESCRIBE {$tableName}");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'exists' => true,
            'columns' => $columns
        ];
    } catch (PDOException $e) {
        return [
            'exists' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Check which related tables exist
$tablesToCheck = [
    'project_sub_stages',
    'project_substages',
    'project_stages',
    'projects'
];

$results = [];

// Only proceed if this is a POST request or if in development
if ($isPostRequest || (isset($_GET['dev']) && $_GET['dev'] === 'true')) {
    foreach ($tablesToCheck as $tableName) {
        $results[$tableName] = getTableStructure($pdo, $tableName);
    }
    
    // Check for any row in project_sub_stages
    if ($results['project_sub_stages']['exists']) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM project_sub_stages");
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            $results['project_sub_stages']['row_count'] = $count['count'];
        } catch (PDOException $e) {
            $results['project_sub_stages']['row_count_error'] = $e->getMessage();
        }
    }
    
    echo json_encode($results, JSON_PRETTY_PRINT);
} else {
    echo json_encode(['error' => 'Unauthorized access. Use POST request.']);
}
?> 