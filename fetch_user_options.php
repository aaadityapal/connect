<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// Get location parameter
$location = isset($_GET['location']) ? $_GET['location'] : 'studio';

try {
    // Fetch users based on location filter
    if ($location === 'studio') {
        // For studio, exclude specific roles
        $query = "SELECT id, username, position FROM users WHERE status = 'active' AND role NOT IN ('Site Supervisor', 'Site Coordinator', 'Purchase Manager', 'Graphic Designer', 'Social Media Marketing') ORDER BY username";
    } else {
        // For site, only include specific roles
        $query = "SELECT id, username, position FROM users WHERE status = 'active' AND role IN ('Site Supervisor', 'Site Coordinator', 'Purchase Manager', 'Graphic Designer', 'Social Media Marketing') ORDER BY username";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to fetch users: ' . $e->getMessage()]);
}
?>