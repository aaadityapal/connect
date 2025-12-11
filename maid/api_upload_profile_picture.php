<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if file was uploaded
if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['profile_picture'];

// Validate file type
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif'];
$file_type = mime_content_type($file['tmp_name']);

if (!in_array($file_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, WebP, and HEIC are allowed.']);
    exit();
}

// Validate file size (max 5MB)
$max_size = 5 * 1024 * 1024; // 5MB in bytes
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
    exit();
}

// Create upload directory if it doesn't exist
$upload_dir = '../uploads/profile_pictures/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Get file extension
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Convert HEIC to JPG for web compatibility
if (in_array($file_type, ['image/heic', 'image/heif']) || in_array($file_extension, ['heic', 'heif'])) {
    // Check if ImageMagick is available
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick($file['tmp_name']);
            $imagick->setImageFormat('jpg');
            $imagick->setImageCompressionQuality(90);

            // Generate unique filename with jpg extension
            $new_filename = 'profile_' . $user_id . '_' . time() . '.jpg';
            $upload_path = $upload_dir . $new_filename;

            // Save as JPG
            $imagick->writeImage($upload_path);
            $imagick->clear();
            $imagick->destroy();

            $file_converted = true;
        } catch (Exception $e) {
            error_log("HEIC conversion error: " . $e->getMessage());
            // Fall back to saving as JPG anyway (will attempt conversion)
            $new_filename = 'profile_' . $user_id . '_' . time() . '.jpg';
            $upload_path = $upload_dir . $new_filename;
            $file_converted = false;
        }
    } else {
        // ImageMagick not available - inform user to upload JPG/PNG instead
        echo json_encode([
            'success' => false,
            'message' => 'HEIC format requires conversion. Please upload a JPG or PNG image instead.'
        ]);
        exit();
    }
} else {
    // Generate unique filename for other formats
    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    $file_converted = false;
}

// Move uploaded file (only if not already converted)
if (!isset($file_converted) || !$file_converted) {
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        exit();
    }
}

// Get old profile picture to delete it later
try {
    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $old_picture = $user['profile_picture'] ?? null;
} catch (PDOException $e) {
    error_log("Error fetching old profile picture: " . $e->getMessage());
    $old_picture = null;
}

// Update database
try {
    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
    $stmt->execute([$new_filename, $user_id]);

    // Update session
    $_SESSION['profile_picture'] = $new_filename;

    // Delete old profile picture if it exists and is not default
    if ($old_picture && $old_picture !== 'default.png' && $old_picture !== $new_filename) {
        $old_file_path = $upload_dir . $old_picture;
        if (file_exists($old_file_path)) {
            unlink($old_file_path);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile picture updated successfully',
        'filename' => $new_filename,
        'url' => $upload_path
    ]);
} catch (PDOException $e) {
    // If database update fails, delete the uploaded file
    if (file_exists($upload_path)) {
        unlink($upload_path);
    }

    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>