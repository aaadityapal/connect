<?php
session_start();
require_once 'includes/db_connect.php';

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

    // Updated query to match your table structure
    $query = "SELECT 
                id,
                username,
                email,
                position,
                designation,
                profile_picture,
                employee_id,
                role
              FROM users 
              WHERE id != ? 
              AND status = 'active'
              AND deleted_at IS NULL
              ORDER BY username ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        // Format user data with your actual columns
        $users[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'profile_picture' => $row['profile_picture'] ? 'uploads/profile_pictures/' . $row['profile_picture'] : null,
            'position' => $row['position'],
            'designation' => $row['designation'],
            'employee_id' => $row['employee_id'],
            'role' => $row['role']
        ];
    }

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);

} catch (Exception $e) {
    error_log("Error in fetch_users.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching users: ' . $e->getMessage()
    ]);
}

$conn->close();
?> 