<?php
session_start();
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$target_user_id = $data['user_id'] ?? null;
$can_pay = $data['can_pay'] ?? 0;

if (!$target_user_id) {
    echo json_encode(['success' => false, 'message' => 'Missing User ID']);
    exit;
}

try {
    if ($can_pay) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO travel_payment_auth (user_id) VALUES (?)");
        $stmt->execute([$target_user_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM travel_payment_auth WHERE user_id = ?");
        $stmt->execute([$target_user_id]);
    }
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
