<?php
// Get users from database for assignee dropdown
header('Content-Type: application/json');

try {
    // Include database connection
    require_once '../config/db_connect.php';
    
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    // Fetch active users only
    $query = "SELECT id, username, position, email, designation, department, profile_image
              FROM users 
              WHERE deleted_at IS NULL 
              AND status = 'active'
              ORDER BY username ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedUsers = [];
    foreach ($users as $row) {
        $formattedUsers[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'position' => $row['position'],
            'email' => $row['email'],
            'designation' => $row['designation'],
            'department' => $row['department'],
            'profile_image' => $row['profile_image']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formattedUsers,
        'count' => count($formattedUsers)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
