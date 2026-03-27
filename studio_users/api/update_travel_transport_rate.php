<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

try {
    // Check permission - Relationship Manager or Admin
    $uStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $uStmt->execute([$_SESSION['user_id']]);
    $userRole = strtolower($uStmt->fetchColumn());

    $allowed = ['relationship manager', 'admin'];
    if (!in_array($userRole, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['transport_mode']) || !isset($input['rate_per_km'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE travel_transport_rates SET rate_per_km = ? WHERE transport_mode = ?");
    $stmt->execute([$input['rate_per_km'], $input['transport_mode']]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>