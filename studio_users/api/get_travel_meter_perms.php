<?php
/**
 * GET TRAVEL METER PHOTO PERMISSIONS
 * studio_users/api/get_travel_meter_perms.php
 *
 * Returns all active users + their per-mode meter photo requirement flags.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

try {
    // 1. Get all active users
    $usersStmt = $pdo->query("
        SELECT id, username, email, role
        FROM users
        WHERE status = 'active'
        ORDER BY username ASC
    ");
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get all permission rows from our new table
    $permsStmt = $pdo->query("
        SELECT user_id, mode
        FROM travel_meter_photo_perms
    ");
    $allPerms = $permsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Group permissions by user_id
    $permsMap = [];
    foreach ($allPerms as $row) {
        $uid = (int)$row['user_id'];
        if (!isset($permsMap[$uid])) $permsMap[$uid] = [];
        $permsMap[$uid][] = ['mode' => $row['mode']];
    }

    // 4. Attach permissions to users
    foreach ($users as &$u) {
        $uid = (int)$u['id'];
        $u['perms'] = $permsMap[$uid] ?? [];
    }
    unset($u);

    echo json_encode(['success' => true, 'users' => $users]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>
