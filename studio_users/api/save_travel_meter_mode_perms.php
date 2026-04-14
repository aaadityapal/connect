<?php
/**
 * SAVE TRAVEL METER MODE PERMISSIONS
 * studio_users/api/save_travel_meter_mode_perms.php
 *
 * Upserts meter_mode flag for Bike and Car per user into travel_meter_mode_config.
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
    $input = json_decode(file_get_contents('php://input'), true);
    $permissions = $input['permissions'] ?? [];

    if (empty($permissions) || !is_array($permissions)) {
        echo json_encode(['success' => false, 'message' => 'No permissions data received']);
        exit();
    }

    $allowedModes = ['Bike', 'Car'];

    $upsertStmt = $pdo->prepare("
        INSERT INTO travel_meter_mode_config (user_id, mode, meter_mode)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE meter_mode = VALUES(meter_mode)
    ");

    $pdo->beginTransaction();
    foreach ($permissions as $userId => $modes) {
        $userId = (int)$userId;
        if ($userId <= 0) continue;

        foreach ($modes as $mode => $meterMode) {
            if (!in_array($mode, $allowedModes)) continue;
            $upsertStmt->execute([$userId, $mode, (int)$meterMode]);
        }
    }
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Meter mode permissions saved successfully']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
?>
