<?php
/**
 * SAVE TRAVEL METER PHOTO PERMISSIONS
 * studio_users/api/save_travel_meter_perms.php
 *
 * Expects JSON body:
 * {
 *   "permissions": {
 *     "12": { "Car": 1, "Bike": 0, "Cab": 1, ... },
 *     "7":  { "Car": 0, "Bike": 1, ... }
 *   }
 * }
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$permissions = $input['permissions'] ?? null;

if (!is_array($permissions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid permissions data']);
    exit();
}

try {
    $pdo->beginTransaction();

    $deleteStmt = $pdo->prepare("DELETE FROM travel_meter_photo_perms WHERE user_id = ?");
    $insertStmt = $pdo->prepare("INSERT INTO travel_meter_photo_perms (user_id, mode) VALUES (?, ?)");

    foreach ($permissions as $userId => $modes) {
        $userId = (int)$userId;
        if ($userId <= 0 || !is_array($modes)) continue;

        // Remove all existing rows for this user, then re-insert enabled ones
        $deleteStmt->execute([$userId]);

        foreach ($modes as $mode => $enabled) {
            $mode = trim((string)$mode);
            if ($mode === '' || (int)$enabled !== 1) continue;
            $insertStmt->execute([$userId, $mode]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Permissions saved successfully']);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>
