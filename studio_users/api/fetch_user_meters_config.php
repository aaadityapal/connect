<?php
/**
 * FETCH USER METERS CONFIG
 * studio_users/api/fetch_user_meters_config.php
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

try {
    // 1. Fetch all active users with roles
    $query = "
        SELECT 
            u.id, 
            u.username, 
            u.employee_id, 
            u.role,
            COALESCE(tmmc.meter_mode, 0) as meter_mode
        FROM users u
        LEFT JOIN travel_meter_mode_config tmmc ON u.id = tmmc.user_id
        WHERE u.status = 'active'
        ORDER BY u.username ASC
    ";
    
    $stmt = $pdo->query($query);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>
