<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once '../config/db_connect.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

// Validate required fields
if (!isset($input['query']) || !isset($input['type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields: query and type']);
    exit;
}

$query = trim($input['query']);
$type = trim($input['type']);

// Validate type
if (!in_array($type, ['vendor', 'labour'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid type. Must be vendor or labour']);
    exit;
}

// Validate query length
if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query must be at least 2 characters long']);
    exit;
}

try {
    $results = [];
    
    if ($type === 'vendor') {
        // Search in hr_vendors table
        $sql = "SELECT vendor_id, full_name, phone_number, vendor_type 
                FROM hr_vendors 
                WHERE (full_name LIKE ? OR phone_number LIKE ?) 
                ORDER BY full_name ASC 
                LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($type === 'labour') {
        // Search in hr_labours table
        $sql = "SELECT labour_id, full_name, phone_number, labour_type 
                FROM hr_labours 
                WHERE (full_name LIKE ? OR phone_number LIKE ?) 
                ORDER BY full_name ASC 
                LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results),
        'query' => $query,
        'type' => $type
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>