<?php
/**
 * Check if the instant modal should be suppressed for the user
 * This script checks if the user has chosen to suppress the modal for 24 hours
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db_connect.php'; // Adjust path as needed

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = $pdo; // Use PDO connection from db_connect.php

try {
    // Check if the modal is suppressed for this user
    $query = "
        SELECT suppressed_until 
        FROM modal_suppression 
        WHERE user_id = ? 
        AND modal_type = 'instant_modal'
        AND suppressed_until > NOW()
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    if ($result) {
        // Modal is suppressed
        echo json_encode([
            'success' => true,
            'suppressed' => true,
            'suppressed_until' => $result['suppressed_until']
        ]);
    } else {
        // Modal is not suppressed
        echo json_encode([
            'success' => true,
            'suppressed' => false
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error checking modal suppression: ' . $e->getMessage()]);
}
?>