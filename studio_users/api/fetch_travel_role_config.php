<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

try {
    // Fetch all role configs
    $stmt = $pdo->query("SELECT * FROM travel_role_config ORDER BY role_name ASC");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get current user's role requirement
    $user_id = $_SESSION['user_id'];
    $uStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $uStmt->execute([$user_id]);
    $userRole = $uStmt->fetchColumn();

    $rStmt = $pdo->prepare("SELECT require_meters FROM travel_role_config WHERE role_name = ?");
    $rStmt->execute([$userRole]);
    $requireMeters = $rStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'configs' => $configs,
        'user_requirement' => $requireMeters == 1,
        'user_role' => $userRole
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>