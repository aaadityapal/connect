<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Senior Manager (Studio)') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $leaveId = $_POST['leave_id'];
        $status = $_POST['status'];
        $remarks = $_POST['remarks'];
        
        $stmt = $pdo->prepare("
            UPDATE leaves 
            SET studio_manager_status = ?,
                studio_manager_remarks = ?,
                studio_manager_approved_by = ?,
                studio_manager_approved_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([$status, $remarks, $_SESSION['user_id'], $leaveId]);
        
        echo json_encode(['success' => true]);
        
    } catch (PDOException $e) {
        error_log("Error updating leave: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
