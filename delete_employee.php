<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_POST['employee_id'])) {
    try {
        // Debug: Log the incoming employee ID
        error_log("Attempting to delete employee ID: " . $_POST['employee_id']);

        $pdo->beginTransaction();

        // Update user status to 'deleted' and set deleted_at timestamp
        $stmt = $pdo->prepare("
            UPDATE users 
            SET status = 'deleted', 
                deleted_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        
        $result = $stmt->execute(['id' => $_POST['employee_id']]);
        
        if ($result && $stmt->rowCount() > 0) {
            $pdo->commit();
            // Debug: Log successful deletion
            error_log("Employee deleted successfully");
            echo json_encode([
                'success' => true,
                'message' => 'Employee deleted successfully'
            ]);
        } else {
            // Debug: Log failed deletion
            error_log("No employee was updated");
            throw new Exception('No employee was updated');
        }
        
    } catch(Exception $e) {
        $pdo->rollBack();
        // Debug: Log error
        error_log("Error deleting employee: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting employee: ' . $e->getMessage()
        ]);
    }
    exit();
}
?>
