<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception('Database connection failed: ' . $db->connect_error);
    }

    // Get filter parameters
    $policyType = isset($_GET['policy_type']) ? $db->real_escape_string($_GET['policy_type']) : null;

    // Build query
    $sql = "SELECT 
                pd.id,
                pd.policy_name,
                pd.policy_type,
                pd.original_filename,
                pd.stored_filename,
                pd.file_size,
                pd.file_type,
                pd.status,
                DATE_FORMAT(pd.created_at, '%d %b %Y') as upload_date,
                DATE_FORMAT(pd.updated_at, '%d %b %Y') as last_updated,
                u.username as uploaded_by_name
            FROM policy_documents pd
            LEFT JOIN users u ON pd.uploaded_by = u.id
            WHERE 1=1";

    // Add filters if provided
    if ($policyType) {
        $sql .= " AND pd.policy_type = '$policyType'";
    }

    // Order by latest uploads first
    $sql .= " ORDER BY pd.created_at DESC";

    $result = $db->query($sql);
    if (!$result) {
        throw new Exception('Failed to fetch policy documents: ' . $db->error);
    }

    $documents = [];
    while ($row = $result->fetch_assoc()) {
        // Determine document icon based on file type/extension
        $fileExt = pathinfo($row['original_filename'], PATHINFO_EXTENSION);
        $iconClass = getFileIconClass($fileExt);

        // Format file size
        $formattedSize = formatFileSize($row['file_size']);

        $documents[] = [
            'id' => $row['id'],
            'policy_name' => $row['policy_name'],
            'policy_type' => $row['policy_type'],
            'document_name' => $row['original_filename'],
            'upload_date' => $row['upload_date'],
            'last_updated' => $row['last_updated'],
            'uploaded_by' => $row['uploaded_by_name'],
            'status' => $row['status'],
            'icon_class' => $iconClass,
            'formatted_size' => $formattedSize
        ];
    }

    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log('Policy document retrieval error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve policy documents: ' . $e->getMessage()
    ]);
}

// Helper function to get file icon
function getFileIconClass($extension) {
    $extension = strtolower($extension);
    switch($extension) {
        case 'pdf': return 'fa-file-pdf';
        case 'doc':
        case 'docx': return 'fa-file-word';
        case 'xls':
        case 'xlsx': return 'fa-file-excel';
        case 'ppt':
        case 'pptx': return 'fa-file-powerpoint';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif': return 'fa-file-image';
        case 'zip':
        case 'rar': return 'fa-file-archive';
        case 'txt': return 'fa-file-alt';
        default: return 'fa-file';
    }
}

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' Bytes';
    }
}