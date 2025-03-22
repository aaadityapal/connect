<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception('Database connection failed');
    }

    $userId = $_SESSION['user_id'];
    
    $query = "SELECT 
        od.*,
        u.username as uploaded_by_name
    FROM official_documents od
    LEFT JOIN users u ON od.uploaded_by = u.id
    WHERE od.assigned_user_id = ? 
    AND od.is_deleted = 0
    ORDER BY od.upload_date DESC";

    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $userId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to fetch documents');
    }

    $result = $stmt->get_result();
    $documents = [];

    while ($row = $result->fetch_assoc()) {
        $documents[] = [
            'id' => $row['id'],
            'document_name' => $row['document_name'],
            'document_type' => formatDocumentType($row['document_type']),
            'upload_date' => date('M d, Y H:i', strtotime($row['upload_date'])),
            'status' => $row['status'],
            'formatted_size' => formatFileSize($row['file_size']),
            'uploaded_by_name' => $row['uploaded_by_name'],
            'icon_class' => getFileIconClass($row['file_type']),
            'assigned_user_id' => $row['assigned_user_id'],
            'current_user_id' => $_SESSION['user_id']
        ];
    }

    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

function formatDocumentType($type) {
    return ucwords(str_replace('_', ' ', $type));
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

function getFileIconClass($fileType) {
    switch ($fileType) {
        case 'application/pdf':
            return 'fa-file-pdf';
        case 'application/msword':
        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            return 'fa-file-word';
        default:
            return 'fa-file-alt';
    }
} 