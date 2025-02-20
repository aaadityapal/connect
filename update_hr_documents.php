<?php
session_start();
require_once 'config.php';

// Check authentication and HR role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

try {
    $uploadDir = 'uploads/hr_documents/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if ($_POST['action'] === 'add_hr_doc') {
        // Handle file upload
        $docType = $_POST['type'];
        $file = $_FILES['file'];
        
        // Validate file
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'doc', 'docx'];
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($fileExt, $allowedExts)) {
            throw new Exception('Invalid file type. Allowed types: PDF, DOC, DOCX');
        }

        if ($file['size'] > $maxFileSize) {
            throw new Exception('File size exceeds limit of 10MB');
        }

        // Generate unique filename with timestamp
        $timestamp = date('Y-m-d_His');
        $newFilename = 'hr_doc_' . $timestamp . '_' . uniqid() . '.' . $fileExt;
        $destination = $uploadDir . $newFilename;

        // Check if file was actually uploaded
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No file was uploaded');
        }

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Save document info to database with additional metadata
            $sql = "INSERT INTO hr_documents (
                type, 
                filename, 
                original_name, 
                file_size,
                file_type,
                upload_date,
                uploaded_by,
                last_modified
            ) VALUES (
                :type, 
                :filename, 
                :original_name,
                :file_size,
                :file_type,
                NOW(),
                :uploaded_by,
                NOW()
            )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'type' => $docType,
                'filename' => $newFilename,
                'original_name' => $file['name'],
                'file_size' => $file['size'],
                'file_type' => $file['type'],
                'uploaded_by' => $_SESSION['user_id']
            ]);

            echo json_encode([
                'success' => true, 
                'message' => 'Document uploaded successfully',
                'document' => [
                    'id' => $pdo->lastInsertId(),
                    'filename' => $newFilename,
                    'original_name' => $file['name'],
                    'type' => $docType,
                    'upload_date' => date('M d, Y')
                ]
            ]);
        } else {
            throw new Exception('Failed to upload file');
        }
    } elseif ($_POST['action'] === 'delete_hr_doc') {
        $docId = $_POST['id'];
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Get document info from database
            $sql = "SELECT filename, type FROM hr_documents WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $docId]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$doc) {
                throw new Exception('Document not found');
            }

            // Delete file
            $filePath = $uploadDir . $doc['filename'];
            if (file_exists($filePath)) {
                if (!unlink($filePath)) {
                    throw new Exception('Failed to delete file');
                }
            }

            // First delete from hr_documents_log
            $sql = "DELETE FROM hr_documents_log WHERE document_id = :doc_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['doc_id' => $docId]);

            // Then delete from hr_documents
            $sql = "DELETE FROM hr_documents WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $docId]);

            // Log deletion (after deleting the document)
            $sql = "INSERT INTO hr_documents_log (
                document_id, 
                action, 
                action_by, 
                action_date, 
                document_type
            ) VALUES (
                NULL, 
                'delete', 
                :user_id, 
                NOW(), 
                :doc_type
            )";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'user_id' => $_SESSION['user_id'],
                'doc_type' => $doc['type']
            ]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Document deleted successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}