<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once '../config/db_connect.php';

// Get JSON data from POST request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Check if query parameter exists
if (!isset($data['query'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing query parameter']);
    exit;
}

try {
    // Prepare and execute the query
    $stmt = $pdo->prepare($data['query']);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return the result as JSON
    header('Content-Type: application/json');
    echo json_encode($result);
} catch (PDOException $e) {
    // Log the error
    error_log("Query execution error: " . $e->getMessage());
    
    // Return error message
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 