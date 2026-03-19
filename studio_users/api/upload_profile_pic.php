<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';
require_once 'activity_helper.php';

$userId = $_SESSION['user_id'];

if (isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    $fileType = $file['type'];

    $fileExt = explode('.', $fileName);
    $fileActualExt = strtolower(end($fileExt));

    $allowed = array('jpg', 'jpeg', 'png');

    if (in_array($fileActualExt, $allowed)) {
        if ($fileError === 0) {
            if ($fileSize < 5000000) { // 5MB limit
                $fileNameNew = "profile_" . $userId . "_" . uniqid('', true) . "." . $fileActualExt;
                $fileDestination = '../../uploads/profile_pictures/' . $fileNameNew;
                $dbPath = 'uploads/profile_pictures/' . $fileNameNew;

                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                    try {
                        // Update database
                        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        $stmt->execute([$dbPath, $userId]);

                        logUserActivity($pdo, $userId, 'profile_pic_update', 'user', 'Updated profile picture');

                        echo json_encode(['status' => 'success', 'message' => 'Profile picture updated', 'filename' => $dbPath]);
                    } catch (PDOException $e) {
                        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
                    }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'File too large (max 5MB)']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'There was an error uploading your file']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid file type (JPG, JPEG, PNG only)']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No file provided']);
}
?>
