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

if (!isset($_FILES['document']) || !isset($_POST['doc_type'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing file or document type']);
    exit();
}

$file = $_FILES['document'];
$docType = $_POST['doc_type'];
$docName = $_POST['doc_name'] ?? $file['name'];

// Basic validation
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
$fileName = $file['name'];
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($fileExt, $allowedExtensions)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions)]);
    exit();
}

// Use absolute path for target directory to avoid relative path issues
$targetDir = dirname(__DIR__, 2) . "/uploads/users/documents/$userId/";
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0777, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create document storage directory']);
        exit();
    }
}

$newFileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $fileName);
$targetFile = $targetDir . $newFileName;
$dbPath = "uploads/users/documents/$userId/" . $newFileName;

if (move_uploaded_file($file['tmp_name'], $targetFile)) {
    try {
        // Fetch current documents
        $stmt = $pdo->prepare("SELECT documents FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        $documents = [];
        if (!empty($userData['documents'])) {
            $documents = json_decode($userData['documents'], true);
            if (!is_array($documents)) {
                $documents = [];
            } else {
                // Normalize legacy formats
                foreach ($documents as &$doc) {
                    if (!isset($doc['name']) && isset($doc['filename'])) { $doc['name'] = $doc['filename']; }
                    if (!isset($doc['path']) && isset($doc['file_path'])) { $doc['path'] = $doc['file_path']; }
                    if (!isset($doc['id'])) { $doc['id'] = md5($doc['path'] ?? uniqid()); }
                    if (!isset($doc['extension']) && isset($doc['path'])) {
                        $doc['extension'] = strtolower(pathinfo($doc['path'], PATHINFO_EXTENSION));
                    }
                }
            }
        }

        // Add new document
        $newDoc = [
            'id' => time(),
            'type' => $docType,
            'name' => $docName,
            'path' => $dbPath,
            'uploaded_at' => date('Y-m-d H:i:s'),
            'extension' => $fileExt
        ];

        $documents[] = $newDoc;

        // Update DB
        $updateStmt = $pdo->prepare("UPDATE users SET documents = ? WHERE id = ?");
        $updateStmt->execute([json_encode($documents), $userId]);

        logUserActivity($pdo, $userId, 'document_upload', 'user', "Uploaded document: $docName");

        echo json_encode(['status' => 'success', 'message' => 'Document uploaded successfully', 'documents' => $documents]);

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    $error = error_get_last();
    $diagnostics = [
        'is_dir' => is_dir($targetDir),
        'is_writable' => is_writable($targetDir),
        'tmp_exists' => file_exists($file['tmp_name']),
        'target_file' => $targetFile,
        'php_error' => $error ? $error['message'] : 'none'
    ];
    echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file', 'diagnostics' => $diagnostics]);
}
?>