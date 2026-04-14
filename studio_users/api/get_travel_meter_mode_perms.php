<?php
/**
 * GET TRAVEL METER MODE PERMISSIONS
 * studio_users/api/get_travel_meter_mode_perms.php
 *
 * Returns all users with their per-mode meter_mode flags (Bike, Car).
 * meter_mode = 1 => use uploaded meter photos
 * meter_mode = 0 => use punch-in/out attendance photos
 */
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // Fetch all active, non-admin users
    $usersStmt = $pdo->query("
        SELECT id, username, email, role
        FROM users
        WHERE role != 'admin' AND status = 'active'
        ORDER BY username ASC
    ");
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch existing meter mode settings for Bike and Car only
    $permsStmt = $pdo->query("
        SELECT user_id, mode, meter_mode
        FROM travel_meter_mode_config
        WHERE mode IN ('Bike', 'Car')
    ");
    $permsRows = $permsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Index perms by user_id
    $permsMap = [];
    foreach ($permsRows as $row) {
        $permsMap[$row['user_id']][] = [
            'mode'       => $row['mode'],
            'meter_mode' => (int)$row['meter_mode']
        ];
    }

    // Attach perms to each user
    foreach ($users as &$u) {
        $u['perms'] = $permsMap[$u['id']] ?? [];
    }

    echo json_encode(['success' => true, 'users' => $users]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>
