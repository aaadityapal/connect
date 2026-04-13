<?php
require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$permissions = $data['permissions'] ?? [];

if (empty($permissions)) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Clear existing permissions
    $pdo->exec("DELETE FROM ot_unsubmitted_action_perms");

    // Re-insert only those with at least one permission granted
    $stmt = $pdo->prepare("INSERT INTO ot_unsubmitted_action_perms (user_id, can_action_unsubmitted, can_action_expired, can_action_completed) VALUES (?, ?, ?, ?)");
    
    foreach ($permissions as $userId => $privs) {
        $canUnsub = isset($privs['can_action_unsubmitted']) && (int)$privs['can_action_unsubmitted'] === 1 ? 1 : 0;
        $canExp = isset($privs['can_action_expired']) && (int)$privs['can_action_expired'] === 1 ? 1 : 0;
        $canComp = isset($privs['can_action_completed']) && (int)$privs['can_action_completed'] === 1 ? 1 : 0;
        
        if ($canUnsub === 1 || $canExp === 1 || $canComp === 1) {
            $stmt->execute([$userId, $canUnsub, $canExp, $canComp]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Permissions saved successfully']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
