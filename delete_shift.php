<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'HR' && !isset($_SESSION['temp_admin_access']))) {
    header('Location: login.php');
    exit();
}

if (isset($_GET['id'])) {
    try {
        // Check if shift is assigned to any users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_shifts WHERE shift_id = ?");
        $stmt->execute([$_GET['id']]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $_SESSION['error_message'] = "Cannot delete shift: It is assigned to " . $count . " users.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM shifts WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $_SESSION['success_message'] = "Shift deleted successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

header('Location: shifts.php');
exit(); 