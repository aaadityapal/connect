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

    // Base query
    $query = "
        SELECT 
            id,
            document_name,
            original_filename,
            document_type,
            file_type,
            file_size,
            upload_date,
            last_modified,
            document_number,
            issue_date,
            expiry_date,
            issuing_authority,
            verification_status
        FROM personal_documents
        WHERE assigned_user_id = ? 
        AND is_deleted = 0
    ";

    // Add document type filter if provided
    $params = [$_SESSION['user_id']];
    $types = 'i';

    if (isset($_GET['type']) && $_GET['type'] !== '') {
        $query .= " AND document_type = ?";
        $params[] = $_GET['type'];
        $types .= 's';
    }

    $query .= " ORDER BY last_modified DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = [
            'id' => $row['id'],
            'document_name' => $row['document_name'],
            'original_filename' => $row['original_filename'],
            'document_type' => formatDocumentType($row['document_type']),
            'upload_date' => date('M d, Y H:i', strtotime($row['upload_date'])),
            'formatted_size' => formatFileSize($row['file_size']),
            'icon_class' => getFileIconClass($row['file_type']),
            'document_number' => $row['document_number'],
            'issue_date' => $row['issue_date'] ? date('M d, Y', strtotime($row['issue_date'])) : '',
            'expiry_date' => $row['expiry_date'] ? date('M d, Y', strtotime($row['expiry_date'])) : '',
            'issuing_authority' => $row['issuing_authority'],
            'verification_status' => $row['verification_status']
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

function formatFileSize($size) {
    if (!$size) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

function getFileIconClass($fileType) {
    $iconMap = [
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word',
        'docx' => 'fa-file-word',
        'jpg' => 'fa-file-image',
        'jpeg' => 'fa-file-image',
        'png' => 'fa-file-image'
    ];
    return $iconMap[strtolower($fileType)] ?? 'fa-file-alt';
}

function formatDocumentType($type) {
    return ucwords(str_replace('_', ' ', $type));
} 