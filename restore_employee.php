<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_POST['employee_id'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET status = 'active', 
                deleted_at = NULL 
            WHERE id = :id
        ");
        
        $stmt->execute(['id' => $_POST['employee_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee restored successfully'
        ]);
        
    } catch(PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error restoring employee: ' . $e->getMessage()
        ]);
    }
    exit();
}
?>
