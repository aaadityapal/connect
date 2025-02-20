<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

try {
    // Get current user's ID
    $current_user_id = $_SESSION['user_id'];

    // Get available users excluding current user
    $query = "
        SELECT 
            id,
            username,
            profile_picture,
            designation,
            position
        FROM users 
        WHERE id != ? 
        AND status = 'active'
        ORDER BY username ASC
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param('i', $current_user_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'profile_picture' => $row['profile_picture'],
            'designation' => $row['designation'],
            'position' => $row['position']
        ];
    }

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);

} catch (Exception $e) {
    error_log("Error in get_available_users.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch available users: ' . $e->getMessage()
    ]);
}

$conn->close(); 