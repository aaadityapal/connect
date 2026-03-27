<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

try {
    $uStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $uStmt->execute([$_SESSION['user_id']]);
    $userRole = $uStmt->fetchColumn();

    // Accessible for Relationship Manager (and Admin as general safety)
    $allowed = ['Relationship Manager', 'Admin', 'admin'];
    if (!in_array($userRole, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized role: ' . $userRole]);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['role_name']) || !isset($input['require_meters'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE travel_role_config SET require_meters = ? WHERE role_name = ?");
    $stmt->execute([$input['require_meters'], $input['role_name']]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>