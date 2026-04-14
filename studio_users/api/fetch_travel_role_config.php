<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

try {
    $user_id = $_SESSION['user_id'];

    // 1. Fetch all role configs (for settings page usage)
    $stmt    = $pdo->query("SELECT * FROM travel_role_config ORDER BY role_name ASC");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get current user's role
    $uStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $uStmt->execute([$user_id]);
    $userRole = $uStmt->fetchColumn();

    // 3. [NEW] Fetch per-mode meter photo permissions for this user
    //    from the new travel_meter_photo_perms table.
    //    Returns an array of mode names that require meter photos, e.g. ['Bike', 'Car']
    $mStmt = $pdo->prepare("SELECT mode FROM travel_meter_photo_perms WHERE user_id = ?");
    $mStmt->execute([$user_id]);
    $meterModes = $mStmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success'          => true,
        'configs'          => $configs,
        'user_role'        => $userRole,
        'meter_modes'      => $meterModes,           // NEW: array of modes requiring meter photos
        // Legacy fields kept for backward compatibility with other code
        'user_requirement' => count($meterModes) > 0,
        'meter_mode'       => count($meterModes) > 0 ? 1 : 0,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
