<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $user_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            policy_name,
            policy_type,
            original_filename,
            stored_filename,
            file_size,
            file_type,
            created_at,
            updated_at,
            status
        FROM policy_documents
        ORDER BY created_at DESC
    ");
    
    $stmt->execute();
    $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    foreach ($policies as &$policy) {
        $policy['formatted_size'] = formatFileSize($policy['file_size']);
        // Convert timestamps to readable format
        $policy['created_at'] = date('Y-m-d H:i:s', strtotime($policy['created_at']));
        $policy['updated_at'] = $policy['updated_at'] ? date('Y-m-d H:i:s', strtotime($policy['updated_at'])) : null;
    }

    echo json_encode([
        'success' => true,
        'policies' => $policies
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching policy documents'
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
        return $bytes . ' bytes';
    }
}
?> 