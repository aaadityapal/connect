<?php
/**
 * Get Current User
 * Returns the current user information from the session
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set up the response
header('Content-Type: application/json');

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Return success with user ID
    echo json_encode([
        'success' => true,
        'user_id' => $_SESSION['user_id']
    ]);
} else {
    // Return failure message
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
}
?> 