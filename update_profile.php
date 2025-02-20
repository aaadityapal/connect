<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

try {
    // Handle avatar upload
    if (isset($_FILES['avatar'])) {
        $file = $_FILES['avatar'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
        }
        
        if ($file['size'] > $max_size) {
            throw new Exception('File size too large. Maximum size is 5MB.');
        }
        
        $upload_dir = 'uploads/avatars/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $filename = uniqid() . '_' . basename($file['name']);
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            // Update database with new avatar path
            $stmt = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
            $stmt->execute([$target_path, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Avatar updated successfully']);
            exit();
        } else {
            throw new Exception('Failed to upload file.');
        }
    }

    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Start transaction
        $pdo->beginTransaction();
        
        // Update basic information
        $stmt = $pdo->prepare("
            UPDATE users SET 
                name = ?,
                email = ?,
                phone = ?,
                dob = ?,
                department = ?,
                designation = ?,
                joining_date = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['name'] ?? null,
            $_POST['email'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['dob'] ?? null,
            $_POST['department'] ?? null,
            $_POST['designation'] ?? null,
            $_POST['joining_date'] ?? null,
            $user_id
        ]);

        // Handle password update if requested
        if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!password_verify($_POST['current_password'], $user['password'])) {
                throw new Exception('Current password is incorrect');
            }
            
            // Update password
            $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_password_hash, $user_id]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        $response = ['success' => true, 'message' => 'Profile updated successfully'];
    }
} catch (Exception $e) {
    // Rollback transaction if active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response); 