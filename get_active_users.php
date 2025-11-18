<?php
// Get active users API
// This file fetches active users from the users table

header('Content-Type: application/json');

// Include database connection
require_once 'config/db_connect.php';

try {
    // Prepare and execute query to fetch active users
    $query = "SELECT id, username FROM users WHERE status = 'Active' ORDER BY username ASC";
    $stmt = $pdo->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed");
    }

    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users,
        'count' => count($users)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
