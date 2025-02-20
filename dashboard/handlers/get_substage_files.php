<?php
require_once '../../config/db_connect.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get substage ID from query parameters
$substageId = isset($_GET['substage_id']) ? intval($_GET['substage_id']) : 0;

if ($substageId === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid substage ID']);
    exit();
}

try {
    // Prepare query to get files
    $query = "SELECT sf.id, sf.file_name, sf.status, sf.file_path, sf.type,
              sf.uploaded_at, sf.updated_at, 
              FROM substage_files sf
              WHERE sf.substage_id = ? 
              AND sf.deleted_at IS NULL
              ORDER BY sf.uploaded_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $substageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $files = $result->fetch_all(MYSQLI_ASSOC);
    
    // Add debug logging
    error_log("Raw files data: " . json_encode($files));
    
    foreach ($files as &$file) {
        // Debug log for each file's status
        error_log("File ID: {$file['id']}, Name: {$file['file_name']}, Status: {$file['status']}");
        
        // Ensure status is properly set
        if (!isset($file['status']) || empty($file['status'])) {
            $file['status'] = 'pending';
            error_log("Empty status found for file ID: {$file['id']}, setting to pending");
        }
        
        // Convert status to display format
        $file['status_display'] = getStatusBadge($file['status']);
        error_log("Status display for file ID: {$file['id']}: {$file['status_display']}");
    }
    
    echo json_encode(['success' => true, 'files' => $files]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();

function getStatusBadge($status) {
    $statusConfig = [
        'pending' => [
            'icon' => 'fa-clock',
            'text' => 'Pending',
            'class' => 'status-pending'
        ],
        'sent_for_approval' => [
            'icon' => 'fa-paper-plane',
            'text' => 'Sent for Approval',
            'class' => 'status-sent'
        ],
        'approved' => [
            'icon' => 'fa-check-circle',
            'text' => 'Approved',
            'class' => 'status-approved'
        ],
        'rejected' => [
            'icon' => 'fa-times-circle',
            'text' => 'Rejected',
            'class' => 'status-rejected'
        ],
        'in_review' => [
            'icon' => 'fa-search',
            'text' => 'In Review',
            'class' => 'status-in-review'
        ]
    ];

    $config = $statusConfig[$status] ?? $statusConfig['pending'];
    
    return "<span class='status-badge {$config['class']}'>
                <i class='fas {$config['icon']}'></i>
                {$config['text']}
            </span>";
} 