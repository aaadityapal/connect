<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'error' => 'Authentication required']));
}

try {
    // Base query with acknowledgment status from document_acknowledgments
    $sql = "SELECT 
        d.id,
        d.type,
        d.filename,
        d.original_name,
        d.upload_date,
        d.file_size,
        d.file_type,
        d.last_modified,
        COALESCE(da.status, 'pending') as status,
        u.username as uploaded_by_name,
        CASE 
            WHEN d.file_size < 1024 THEN CONCAT(d.file_size, ' B')
            WHEN d.file_size < 1048576 THEN CONCAT(ROUND(d.file_size/1024, 2), ' KB')
            ELSE CONCAT(ROUND(d.file_size/1048576, 2), ' MB')
        END as formatted_size,
        CASE 
            WHEN da.status = 'acknowledged' THEN 1 
            ELSE 0 
        END as is_acknowledged,
        da.acknowledged_at
    FROM hr_documents d
    LEFT JOIN users u ON d.uploaded_by = u.id
    LEFT JOIN document_acknowledgments da ON d.id = da.document_id AND da.user_id = ?
    WHERE d.status = 'published'
    ORDER BY d.upload_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process each document to add file icon class
    foreach ($documents as &$doc) {
        $doc['icon_class'] = getFileIconClass($doc['filename']);
    }

    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);

} catch (Exception $e) {
    error_log("Error in get_hr_documents.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving documents'
    ]);
}

function getFileIconClass($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch($ext) {
        case 'pdf':
            return 'fa-file-pdf';
        case 'doc':
        case 'docx':
            return 'fa-file-word';
        case 'xls':
        case 'xlsx':
            return 'fa-file-excel';
        case 'ppt':
        case 'pptx':
            return 'fa-file-powerpoint';
        case 'jpg':
        case 'jpeg':
        case 'png':
            return 'fa-file-image';
        default:
            return 'fa-file';
    }
}