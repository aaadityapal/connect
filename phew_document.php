<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$document_id = $_GET['id'] ?? null;
$document_type = $_GET['type'] ?? null;

if (!$document_id || !$document_type) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    // Different queries based on document type
    switch ($document_type) {
        case 'policy':
            $stmt = $pdo->prepare("
                SELECT stored_filename, file_type 
                FROM policy_documents 
                WHERE id = ?
            ");
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid document type']);
            exit();
    }

    $stmt->execute([$document_id]);
    $document = $stmt->fetch();

    if (!$document) {
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit();
    }

    // Set appropriate headers for viewing
    header('Content-Type: ' . $document['file_type']);
    
    // Assuming files are stored in an 'uploads' directory
    $file_path = 'uploads/documents/policy/' . $document['stored_filename'];
    
    if (file_exists($file_path)) {
        readfile($file_path);
    } else {
        echo json_encode(['success' => false, 'message' => 'File not found']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>