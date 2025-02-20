<?php
session_start();
require_once 'config.php';

if (isset($_POST['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = :id");
        $stmt->execute(['id' => $_POST['user_id']]);
        $profile_image = $stmt->fetchColumn();
        
        $_SESSION['profile_image'] = $profile_image;
        
        echo json_encode([
            'success' => true,
            'profile_image' => $profile_image
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>
