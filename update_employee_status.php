<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    header('Location: login.php');
    exit();
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
        $stmt->execute([
            'id' => $_GET['id'],
            'status' => $_GET['status']
        ]);

        $_SESSION['success'] = "Employee status updated successfully!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error updating employee status: " . $e->getMessage();
    }
}

header('Location: view_employee.php?id=' . $_GET['id']);
exit();
?>
