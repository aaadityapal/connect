<?php
// Add these lines at the very top for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log errors to a file
error_log("Starting get_users.php");

// Prevent any output before JSON
ob_clean();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include the correct database connection file
require_once '../config/db_connect.php';

try {
    // Use the existing PDO connection ($pdo)
    $query = "SELECT 
        id,
        username,
        position,
        designation,
        department,
        profile_image,
        role
    FROM users 
    WHERE status = 'active' 
    AND deleted_at IS NULL 
    ORDER BY username ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $users
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch users: ' . $e->getMessage()
    ]);
}
exit; 