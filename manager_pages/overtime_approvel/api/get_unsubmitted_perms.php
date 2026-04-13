<?php
require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin (basic security)
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Fetch users who can be managers/admins
    // We fetch all users to allow granular control
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.email, u.role, 
               CASE WHEN p.user_id IS NOT NULL THEN 1 ELSE 0 END as has_entry,
               COALESCE(p.can_action_unsubmitted, 0) as can_action_unsubmitted,
               COALESCE(p.can_action_expired, 0) as can_action_expired,
               COALESCE(p.can_action_completed, 0) as can_action_completed
        FROM users u
        LEFT JOIN ot_unsubmitted_action_perms p ON u.id = p.user_id
        WHERE u.role != 'employee' OR p.user_id IS NOT NULL
        ORDER BY u.username ASC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'users' => $users
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
