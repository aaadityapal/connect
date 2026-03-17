<?php
session_start();
require_once '../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // We only fetch active users
    $query = "SELECT id, username, position, department, role FROM users WHERE deleted_at IS NULL AND status = 'Active' ORDER BY username ASC";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Function to generate consistent appealing colors based on username
    function stringToHSL($str) {
        $hash = 0;
        for ($i = 0; $i < strlen($str); $i++) {
            $hash = ord($str[$i]) + (($hash << 5) - $hash);
        }
        $h = $hash % 360;
        return "hsl(" . abs($h) . ", 70%, 50%)"; // Standard saturation and lightness for clean readability
    }
    
    $processed_users = [];
    foreach ($users as $index => $user) {
        $name_parts = explode(' ', trim($user['username']));
        $initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
        $color = stringToHSL($user['username']);
        
        $processed_users[] = [
            'id' => $user['id'],
            'name' => $user['username'],
            'position' => $user['position'],
            'role' => $user['role'],
            'initials' => $initials,
            'color' => $color
        ];
    }
    
    echo json_encode([
        'success' => true,
        'users' => $processed_users
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
}
?>
