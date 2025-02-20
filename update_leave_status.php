<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $leaveId = $_POST['leave_id'];
        $status = $_POST['status'];
        $remarks = $_POST['remarks'] ?? '';
        
        $stmt = $pdo->prepare("
            UPDATE leaves 
            SET status = ?, 
                remarks = ?,
                approved_by = ?,
                approved_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $status,
            $remarks,
            $_SESSION['user_id'],
            $leaveId
        ]);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update leave status']);
        }
        
    } catch (PDOException $e) {
        error_log("Leave Update Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>
