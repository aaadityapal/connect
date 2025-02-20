<?php
session_start();
require_once 'config.php';

// Check authentication and HR role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $employeeId = $_POST['employee_id'] ?? null;
        $action = $_POST['action'] ?? '';

        if (!$employeeId) {
            throw new Exception('Employee ID is required');
        }

        // Fetch current employee documents
        $stmt = $pdo->prepare("SELECT documents FROM users WHERE id = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            throw new Exception('Employee not found');
        }

        $documents = json_decode($employee['documents'] ?? '{}', true) ?: [];
        $uploadDir = 'uploads/';

        switch ($action) {
            case 'delete':
                $docType = $_POST['doc_type'] ?? '';
                if (!$docType || !isset($documents[$docType])) {
                    throw new Exception('Invalid document type');
                }

                // Delete file
                $fileToDelete = $uploadDir . $documents[$docType]['filename'];
                if (file_exists($fileToDelete)) {
                    unlink($fileToDelete);
                }

                // Remove from array
                unset($documents[$docType]);
                break;

            case 'add':
                // Handle file upload
                if (!isset($_FILES['file'])) {
                    throw new Exception('No file uploaded');
                }

                $docType = $_POST['type'] ?? '';
                $file = $_FILES['file'];
                
                // Validate file
                $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                
                if (!in_array($fileExt, $allowedExts)) {
                    throw new Exception('Invalid file type');
                }

                // Generate unique filename
                $newFilename = uniqid($employeeId . '_' . $docType . '_') . '.' . $fileExt;
                $destination = $uploadDir . $newFilename;

                // Delete old file if exists
                if (isset($documents[$docType]['filename'])) {
                    $oldFile = $uploadDir . $documents[$docType]['filename'];
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                // Move uploaded file
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    throw new Exception('Failed to upload file');
                }

                // Update documents array
                $documents[$docType] = [
                    'filename' => $newFilename,
                    'original_name' => $file['name'],
                    'uploaded_at' => date('Y-m-d H:i:s')
                ];
                break;

            default:
                throw new Exception('Invalid action');
        }

        // Update database
        $stmt = $pdo->prepare("UPDATE users SET documents = ? WHERE id = ?");
        $stmt->execute([json_encode($documents), $employeeId]);

        echo json_encode(['success' => true, 'message' => 'Documents updated successfully']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}