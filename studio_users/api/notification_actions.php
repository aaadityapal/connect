<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    if ($action === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE global_activity_logs SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        echo json_encode(['status' => 'success']);
    } elseif ($action === 'mark_single_read') {
        $notifId = intval($input['notif_id'] ?? 0);
        if ($notifId > 0) {
            $stmt = $pdo->prepare("UPDATE global_activity_logs SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notifId, $userId]);
        }
        echo json_encode(['status' => 'success']);
    } elseif ($action === 'clear_all') {
        $stmt = $pdo->prepare("UPDATE global_activity_logs SET is_dismissed = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
