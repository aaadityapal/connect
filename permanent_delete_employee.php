<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_POST['employee_id'])) {
    try {
        $pdo->beginTransaction();

        // Get employee details for file cleanup
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = :id");
        $stmt->execute(['id' => $_POST['employee_id']]);
        $employee = $stmt->fetch();

        // Delete profile image if exists
        if ($employee['profile_image']) {
            $filepath = 'uploads/profile_images/' . $employee['profile_image'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }

        // Delete the user record
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute(['id' => $_POST['employee_id']]);

        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee permanently deleted'
        ]);
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting employee: ' . $e->getMessage()
        ]);
    }
    exit();
}
?>
