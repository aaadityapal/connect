<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $type = $_POST['document_type'];
    $description = $_POST['description'];
    $user_id = $_SESSION['user_id'];

    // File upload handling
    $file = $_FILES['document'];
    $fileName = time() . '_' . basename($file['name']);
    $targetDir = "uploads/";
    $targetPath = $targetDir . $fileName;

    // Create uploads directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO documents (user_id, title, type, description, file_path, uploaded_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([$user_id, $title, $type, $description, $fileName]);
            
            header("Location: employee_dashboard.php?upload=success");
        } catch(PDOException $e) {
            header("Location: employee_dashboard.php?upload=error");
        }
    } else {
        header("Location: employee_dashboard.php?upload=error");
    }
}
?>

