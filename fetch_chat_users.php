<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$current_user_id = $_SESSION['user_id'];

try {
    // Fetch all users except the current user
    $query = "SELECT 
        id,
        username,
        profile_picture,
        status
    FROM users 
    WHERE id != ? 
    AND deleted_at IS NULL 
    ORDER BY username ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        // Format the user data
        $users[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'avatar' => $row['profile_picture'] ?? 'assets/default-avatar.png',
            'status' => $row['status'] ?? 'offline' // Default to offline if status is null
        ];
    }

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch users'
    ]);
} 