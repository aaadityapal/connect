<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $userId = $_SESSION['user_id'];

    // 1. Get the assigned manager for the current user
    $stmt = $pdo->prepare("SELECT manager_id FROM leave_approval_mapping WHERE employee_id = ?");
    $stmt->execute([$userId]);
    $assigned = $stmt->fetch();
    $assignedManagerId = $assigned ? $assigned['manager_id'] : null;

    // 2. Get all potential approvers (Managers and Admins)
    $query = "SELECT id, username as name, position FROM users 
              WHERE (position LIKE '%Manager%' OR role = 'admin' OR role = 'manager')
              AND status = 'Active' 
              AND deleted_at IS NULL
              ORDER BY username ASC";
    
    $stmt = $pdo->query($query);
    $approvers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'approvers' => $approvers,
        'assigned_id' => $assignedManagerId
    ]);

} catch (Exception $e) {
    error_log("Fetch approvers error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
