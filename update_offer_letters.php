<?php
session_start();
require_once 'config.php';

// Check authentication and HR role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/offer_letters';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_offer_letter':
            handleOfferLetterUpload();
            break;
        case 'delete_offer_letter':
            handleOfferLetterDelete();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit();
    }
}

function handleOfferLetterUpload() {
    global $pdo, $upload_dir;

    try {
        // Validate input
        if (!isset($_POST['user_id']) || !isset($_FILES['file'])) {
            throw new Exception('Missing required fields');
        }

        $user_id = $_POST['user_id'];
        $file = $_FILES['file'];

        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed');
        }

        // Validate file type
        $allowed_types = ['application/pdf'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $file['tmp_name']);
        finfo_close($file_info);

        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception('Invalid file type. Only PDF files are allowed.');
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_filename = 'offer_letter_' . $user_id . '_' . uniqid() . '.' . $extension;
        $file_path = $upload_dir . '/' . $unique_filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Failed to save file');
        }

        // Save to database
        $stmt = $pdo->prepare("
            INSERT INTO offer_letters (
                user_id, 
                file_name, 
                original_name, 
                file_path, 
                file_size, 
                status
            ) VALUES (?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->execute([
            $user_id,
            $unique_filename,
            $file['name'],
            $file_path,
            $file['size']
        ]);

        echo json_encode(['success' => true, 'message' => 'Offer letter uploaded successfully']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleOfferLetterDelete() {
    global $pdo;

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;

        if (!$id) {
            throw new Exception('Invalid offer letter ID');
        }

        // Get file path before deletion
        $stmt = $pdo->prepare("SELECT file_path FROM offer_letters WHERE id = ?");
        $stmt->execute([$id]);
        $letter = $stmt->fetch();

        if (!$letter) {
            throw new Exception('Offer letter not found');
        }

        // Delete file
        if (file_exists($letter['file_path'])) {
            unlink($letter['file_path']);
        }

        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM offer_letters WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Offer letter deleted successfully']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} 