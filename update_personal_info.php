<?php
session_start();
require_once 'config.php';

// Set proper headers
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();
        
        // Handle profile picture upload
        $profile_picture_path = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
                throw new Exception('Invalid file type. Only JPG and PNG are allowed.');
            }
            
            if ($_FILES['profile_picture']['size'] > $max_size) {
                throw new Exception('File size too large. Maximum size is 5MB.');
            }
            
            $upload_dir = 'uploads/profile_pictures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
                $profile_picture_path = $target_path;
            }
        }

        // Update user information
        $sql = "UPDATE users SET 
                username = COALESCE(?, username),
                email = COALESCE(?, email),
                profile_picture = COALESCE(?, profile_picture),
                bio = COALESCE(?, bio),
                gender = COALESCE(?, gender),
                marital_status = COALESCE(?, marital_status),
                nationality = COALESCE(?, nationality),
                languages = COALESCE(?, languages),
                blood_group = COALESCE(?, blood_group),
                skills = COALESCE(?, skills),
                interests = COALESCE(?, interests),
                dob = COALESCE(?, dob),
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['username'] ?? null,
            $_POST['email'] ?? null,
            $profile_picture_path,
            $_POST['bio'] ?? null,
            $_POST['gender'] ?? null,
            $_POST['marital_status'] ?? null,
            $_POST['nationality'] ?? null,
            $_POST['languages'] ?? null,
            $_POST['blood_group'] ?? null,
            $_POST['skills'] ?? null,
            $_POST['interests'] ?? null,
            $_POST['dob'] ?? null,
            $user_id
        ]);
        
        $pdo->commit();
        
        $response = [
            'success' => true,
            'message' => 'Profile updated successfully',
            'profile_picture' => $profile_picture_path
        ];
    } else {
        throw new Exception('Invalid request method');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
exit(); 