<?php
// Include database connection
require_once '../config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Get current user ID
$currentUserId = $_SESSION['user_id'];

try {
    // Query to get user role
    $query = "SELECT role FROM users WHERE id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $stmt->execute();
    
    $userRole = $stmt->fetchColumn();
    
    if ($userRole) {
        echo json_encode([
            'success' => true,
            'role' => $userRole
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User role not found'
        ]);
    }
} catch (PDOException $e) {
    // Log error
    error_log("Error fetching user role: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}