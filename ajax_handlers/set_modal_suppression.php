<?php
/**
 * Set modal suppression for the user
 * This script sets the user's preference to suppress the instant modal for 24 hours
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
    // Set suppression for 24 hours from now
    $suppressed_until = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Insert or update the suppression record
    $query = "
        INSERT INTO modal_suppression (user_id, modal_type, suppressed_until) 
        VALUES (?, 'instant_modal', ?)
        ON DUPLICATE KEY UPDATE suppressed_until = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id, $suppressed_until, $suppressed_until]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Modal suppression set successfully',
        'suppressed_until' => $suppressed_until
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error setting modal suppression: ' . $e->getMessage()]);
}
?>