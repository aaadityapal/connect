<?php
/**
 * Get User Profile API
 * 
 * Retrieves a user's profile information including profile picture
 * 
 * @param int user_id - The ID of the user to retrieve
 * @return JSON Response with user profile data
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit();
}

// Database connection
require_once 'config/db_connect.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate user ID
$user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user ID'
    ]);
    exit();
}

try {
    // Query to get user profile data
    $query = "SELECT id, username, email, profile_picture, role FROM users WHERE id = ? LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Return only necessary data
        echo json_encode([
            'success' => true,
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'profile_picture' => $user['profile_picture']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving user profile: ' . $e->getMessage()
    ]);
}

// Close the connection
$conn->close(); 