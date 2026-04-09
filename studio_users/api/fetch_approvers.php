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

    // 2. Get potential approvers (Senior Managers + the user's specific assigned manager)
    if ($assignedManagerId) {
        $query = "SELECT id, username as name, role FROM users 
                  WHERE (role LIKE 'Senior Manager%' OR id = ?)
                  AND status = 'Active' 
                  AND deleted_at IS NULL
                  ORDER BY username ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$assignedManagerId]);
    } else {
        $query = "SELECT id, username as name, role FROM users 
                  WHERE role LIKE 'Senior Manager%'
                  AND status = 'Active' 
                  AND deleted_at IS NULL
                  ORDER BY username ASC";
        $stmt = $pdo->query($query);
    }
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
