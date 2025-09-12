<?php
session_start();
include 'config.php';

try {
    // Query to get total number of active users
    $query = "SELECT COUNT(*) as total_users FROM users WHERE status = 'active'";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total_users' => $result['total_users']
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching user count'
    ]);
}
?>