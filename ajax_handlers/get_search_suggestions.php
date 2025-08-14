<?php
// Include database connection
require_once '../config/db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'data' => [],
    'message' => ''
];

try {
    // Get search query
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    
    // Validate query
    if (empty($query) || strlen($query) < 2) {
        throw new Exception('Search query too short');
    }
    
    // Prepare search pattern
    $searchPattern = "%$query%";
    
    // Get project titles
    $titleSql = "SELECT id, title, client_name, project_type 
                FROM projects 
                WHERE deleted_at IS NULL 
                AND title LIKE ? 
                ORDER BY title ASC 
                LIMIT 5";
    
    $titleStmt = $pdo->prepare($titleSql);
    $titleStmt->execute([$searchPattern]);
    $titleResults = $titleStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get client names
    $clientSql = "SELECT DISTINCT client_name 
                 FROM projects 
                 WHERE deleted_at IS NULL 
                 AND client_name LIKE ? 
                 AND client_name IS NOT NULL 
                 AND client_name != '' 
                 ORDER BY client_name ASC 
                 LIMIT 5";
    
    $clientStmt = $pdo->prepare($clientSql);
    $clientStmt->execute([$searchPattern]);
    $clientResults = $clientStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Format results
    $suggestions = [];
    
    // Add title suggestions
    foreach ($titleResults as $result) {
        $suggestions[] = [
            'type' => 'title',
            'value' => $result['title'],
            'extra' => $result['client_name'] ?? null,
            'project_type' => ucwords($result['project_type'] ?? '')
        ];
    }
    
    // Add client suggestions
    foreach ($clientResults as $clientName) {
        // Check if this client name is already included in a title suggestion
        $alreadyIncluded = false;
        foreach ($suggestions as $suggestion) {
            if ($suggestion['type'] === 'title' && $suggestion['extra'] === $clientName) {
                $alreadyIncluded = true;
                break;
            }
        }
        
        if (!$alreadyIncluded) {
            $suggestions[] = [
                'type' => 'client',
                'value' => $clientName
            ];
        }
    }
    
    // Set success response
    $response['success'] = true;
    $response['data'] = $suggestions;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Send JSON response
echo json_encode($response);
exit;
