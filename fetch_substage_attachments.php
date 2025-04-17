<?php
/**
 * API endpoint to fetch files attached to a specific substage
 * Used by the project brief modal to display file attachments
 */

session_start();
require_once 'config/db_connect.php';

// Enable error handling for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate request parameters
$projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$stageId = isset($_GET['stage_id']) ? intval($_GET['stage_id']) : 0;
$substageId = isset($_GET['substage_id']) ? intval($_GET['substage_id']) : 0;

if (!$projectId || !$stageId || !$substageId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    // Check if we should use PDO or mysqli based on available connection
    if (isset($pdo)) {
        // Using PDO
        $query = "
            SELECT 
                sf.id,
                sf.file_name,
                sf.file_path,
                sf.type as file_type,
                sf.uploaded_by,
                u.username as uploaded_by_name,
                sf.uploaded_at,
                sf.status,
                sf.created_at,
                sf.updated_at
            FROM substage_files sf
            LEFT JOIN users u ON sf.uploaded_by = u.id
            WHERE sf.substage_id = :substage_id
            AND sf.deleted_at IS NULL
            ORDER BY sf.uploaded_at DESC
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':substage_id' => $substageId
        ]);

        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Using mysqli
        $query = "
            SELECT 
                sf.id,
                sf.file_name,
                sf.file_path,
                sf.type as file_type,
                sf.uploaded_by,
                u.username as uploaded_by_name,
                sf.uploaded_at,
                sf.status,
                sf.created_at,
                sf.updated_at
            FROM substage_files sf
            LEFT JOIN users u ON sf.uploaded_by = u.id
            WHERE sf.substage_id = ?
            AND sf.deleted_at IS NULL
            ORDER BY sf.uploaded_at DESC
        ";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        $stmt->bind_param("i", $substageId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $files = [];
        while ($file = $result->fetch_assoc()) {
            $files[] = $file;
        }
    }

    // Process file sizes to be human readable
    foreach ($files as &$file) {
        // File size might not be present in this table structure
        if (isset($file['file_size'])) {
            $file['file_size_formatted'] = formatFileSize($file['file_size']);
        } else {
            $file['file_size_formatted'] = 'Unknown size';
        }
        
        $file['uploaded_at_formatted'] = date('M j, Y g:i A', strtotime($file['uploaded_at']));
        $file['file_icon'] = getFileIconClass($file['file_type'] ?? 'unknown');
        
        // Format file status
        if (isset($file['status'])) {
            switch ($file['status']) {
                case 'approved':
                    $file['status_label'] = 'Approved';
                    $file['status_class'] = 'approved';
                    break;
                case 'pending':
                    $file['status_label'] = 'Pending';
                    $file['status_class'] = 'pending';
                    break;
                case 'rejected':
                    $file['status_label'] = 'Rejected';
                    $file['status_class'] = 'rejected';
                    break;
                case 'sent_for_approval':
                    $file['status_label'] = 'Sent for Approval';
                    $file['status_class'] = 'pending';
                    break;
                default:
                    $file['status_label'] = ucfirst($file['status']);
                    $file['status_class'] = 'pending';
            }
        } else {
            $file['status_label'] = 'Pending';
            $file['status_class'] = 'pending';
        }
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'files' => $files,
        'count' => count($files)
    ]);

} catch (Exception $e) {
    error_log("Error fetching substage files: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching files: ' . $e->getMessage()
    ]);
}

/**
 * Format file size to human readable format
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

/**
 * Get appropriate icon class based on file type
 */
function getFileIconClass($fileType) {
    $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
    $documentTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $spreadsheetTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    $presentationTypes = ['application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
    
    if (in_array($fileType, $imageTypes)) {
        return 'far fa-file-image';
    } elseif (in_array($fileType, $documentTypes)) {
        return 'far fa-file-pdf';
    } elseif (in_array($fileType, $spreadsheetTypes)) {
        return 'far fa-file-excel';
    } elseif (in_array($fileType, $presentationTypes)) {
        return 'far fa-file-powerpoint';
    } else {
        return 'far fa-file';
    }
} 