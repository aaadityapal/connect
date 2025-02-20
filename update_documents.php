<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$response = ['success' => false];
$userId = $_SESSION['user_id'];

try {
    if ($_POST['action'] === 'add') {
        // Validate file
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== 0) {
            throw new Exception('No file uploaded or upload error');
        }

        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['file']['type'], $allowed_types)) {
            throw new Exception('Invalid file type. Only PDF, DOC, DOCX, JPG, and PNG are allowed.');
        }

        if ($_FILES['file']['size'] > $max_size) {
            throw new Exception('File size too large. Maximum size is 5MB.');
        }

        // Create upload directory if it doesn't exist
        $upload_dir = 'uploads/documents/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate unique filename
        $filename = uniqid() . '_' . basename($_FILES['file']['name']);
        $target_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
            // Get current documents
            $stmt = $pdo->prepare("SELECT documents FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            $documents = json_decode($user['documents'] ?? '[]', true);

            // Add new document
            $newDocument = [
                'type' => $_POST['type'],
                'filename' => basename($_FILES['file']['name']),
                'file_path' => $target_path,
                'upload_date' => date('Y-m-d H:i:s')
            ];

            $documents[] = $newDocument;

            // Update database
            $stmt = $pdo->prepare("UPDATE users SET documents = ? WHERE id = ?");
            $stmt->execute([json_encode($documents), $userId]);

            $response = [
                'success' => true,
                'index' => count($documents) - 1,
                'filename' => $newDocument['filename'],
                'file_path' => $newDocument['file_path'],
                'upload_date' => $newDocument['upload_date']
            ];
        }
    }
    elseif ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("SELECT documents FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $documents = json_decode($user['documents'] ?? '[]', true);

        // Delete file from server
        if (isset($documents[$_POST['index']]['file_path'])) {
            @unlink($documents[$_POST['index']]['file_path']);
        }

        // Remove document from array
        array_splice($documents, $_POST['index'], 1);

        // Update database
        $stmt = $pdo->prepare("UPDATE users SET documents = ? WHERE id = ?");
        $stmt->execute([json_encode($documents), $userId]);

        $response['success'] = true;
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response); 