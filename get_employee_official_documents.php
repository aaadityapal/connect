<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Debug log
    error_log("Current user ID: " . $user_id);

    $query = "SELECT 
                od.*, 
                u.username as uploaded_by_name,
                ? as current_user_id  -- Explicitly add current user ID
              FROM official_documents od
              LEFT JOIN users u ON od.uploaded_by = u.id
              WHERE od.assigned_user_id = ? 
              AND od.is_deleted = 0";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $user_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug log
    error_log("Documents found: " . json_encode($documents));

    // Ensure proper type casting and data structure
    $documents = array_map(function($doc) use ($user_id) {
        return [
            'id' => (int)$doc['id'],
            'document_name' => $doc['document_name'],
            'document_type' => $doc['document_type'],
            'status' => strtolower($doc['status']), // Ensure lowercase status
            'upload_date' => $doc['upload_date'],
            'formatted_size' => formatFileSize($doc['file_size']),
            'uploaded_by_name' => $doc['uploaded_by_name'],
            'assigned_user_id' => (int)$doc['assigned_user_id'],
            'current_user_id' => (int)$user_id,
            'icon_class' => 'fa-file-alt'
        ];
    }, $documents);

    echo json_encode([
        'success' => true,
        'documents' => $documents,
        'debug' => [
            'user_id' => $user_id,
            'document_count' => count($documents)
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_employee_official_documents.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return number_format($bytes) . ' bytes';
    }
}

function formatDocumentType($type) {
    return ucwords(str_replace('_', ' ', $type));
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