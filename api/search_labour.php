<?php
// Database connection
require_once '../config/db_connect.php';

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);
$query = isset($data['query']) ? trim($data['query']) : '';
$type = isset($data['type']) ? trim($data['type']) : '';

// Validate input
if (empty($query)) {
    echo json_encode(['status' => 'error', 'message' => 'Search query is required']);
    exit;
}

try {
    // Prepare the SQL query
    $sql = "SELECT labour_id as id, full_name as name, position as type, labour_type, phone_number 
            FROM hr_labours 
            WHERE full_name LIKE :query";
    
    // Add type filter if provided
    $params = [':query' => "%$query%"];
    if (!empty($type) && $type !== 'other' && $type !== 'custom_type') {
        // Check if type is one of the labour types
        if (in_array(strtolower($type), ['permanent_labour', 'chowk_labour', 'vendor_labour'])) {
            $sql .= " AND labour_type = :type";
            $params[':type'] = $type;
        } else {
            $sql .= " AND position = :type";
            $params[':type'] = $type;
        }
    }
    
    // Limit results
    $sql .= " ORDER BY full_name ASC LIMIT 10";
    
    // Execute query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return results
    echo json_encode([
        'status' => 'success',
        'results' => $results
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
