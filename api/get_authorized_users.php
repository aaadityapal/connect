<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once '../config/db_connect.php';

// Check if database connection exists
if (!isset($pdo)) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection not available'
    ]);
    exit;
}

try {
    // Define the authorized roles for payment processing
    $authorizedRoles = [
        'site supervisor',
        'purchase manager', 
        'senior manager (site)',
        'coordinator'
    ];
    
    // Create placeholders for the IN clause
    $placeholders = str_repeat('?,', count($authorizedRoles) - 1) . '?';
    
    // Fetch authorized users from the database based on their roles
    $stmt = $pdo->prepare("
        SELECT id, username, role 
        FROM users 
        WHERE LOWER(role) IN ($placeholders) 
        AND status = 'active' 
        ORDER BY role ASC, username ASC
    ");
    
    // Convert roles to lowercase for case-insensitive matching
    $lowercaseRoles = array_map('strtolower', $authorizedRoles);
    $stmt->execute($lowercaseRoles);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'count' => count($users),
        'message' => 'Authorized users fetched successfully'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching authorized users: ' . $e->getMessage()
    ]);
}
?>