<?php
// Start session for user authentication
session_start();

// API endpoint to fetch comprehensive payment entry details
header('Content-Type: application/json');

// Include database connection
require_once '../config/db_connect.php';

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'User not authenticated'
        ]);
        exit;
    }

    // Get payment ID from query parameter
    $paymentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($paymentId <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Valid payment ID is required'
        ]);
        exit;
    }

    // Fetch main payment entry details with project information
    $sql = "SELECT 
                pe.payment_id,
                pe.project_type,
                pe.project_id,
                pe.payment_date,
                pe.payment_amount,
                pe.payment_done_via,
                pe.payment_mode,
                pe.recipient_count,
                pe.payment_proof_image,
                pe.created_at,
                pe.updated_at,
                p.title as project_title,
                p.description as project_description,
                u_via.username as payment_done_via_username";
    
    // Check if created_by and updated_by columns exist
    $checkColumns = $pdo->query("SHOW COLUMNS FROM hr_payment_entries LIKE 'created_by'");
    $hasCreatedBy = $checkColumns->rowCount() > 0;
    
    if ($hasCreatedBy) {
        $sql .= ",
                pe.created_by,
                pe.updated_by,
                u1.username as created_by_username,
                u1.role as created_by_role,
                u2.username as updated_by_username,
                u2.role as updated_by_role";
    }
    
    $sql .= "
            FROM hr_payment_entries pe
            LEFT JOIN projects p ON pe.project_id = p.id
            LEFT JOIN users u_via ON pe.payment_done_via = u_via.id";
    
    if ($hasCreatedBy) {
        $sql .= "
            LEFT JOIN users u1 ON pe.created_by = u1.id
            LEFT JOIN users u2 ON pe.updated_by = u2.id";
    }
    
    $sql .= " WHERE pe.payment_id = :payment_id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':payment_id', $paymentId, PDO::PARAM_INT);
    $stmt->execute();
    
    $paymentEntry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$paymentEntry) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Payment entry not found'
        ]);
        exit;
    }

    // Fetch payment recipients with related data
    $recipientsSql = "SELECT 
                        recipient_id,
                        payment_id,
                        category,
                        type,
                        custom_type,
                        entity_id,
                        name,
                        payment_for,
                        amount,
                        payment_mode,
                        created_at";
    
    if ($hasCreatedBy) {
        $recipientsSql .= ",
                        created_by,
                        updated_by";
    }
    
    $recipientsSql .= " FROM hr_payment_recipients 
                        WHERE payment_id = :payment_id
                        ORDER BY created_at ASC";

    $recipientsStmt = $pdo->prepare($recipientsSql);
    $recipientsStmt->bindParam(':payment_id', $paymentId, PDO::PARAM_INT);
    $recipientsStmt->execute();
    
    $recipients = $recipientsStmt->fetchAll(PDO::FETCH_ASSOC);

    // For each recipient, fetch splits and documents
    foreach ($recipients as &$recipient) {
        $recipientId = $recipient['recipient_id'];
        
        // Fetch payment splits for this recipient
        $splitsSql = "SELECT 
                        split_id,
                        recipient_id,
                        amount,
                        payment_mode,
                        proof_file,
                        created_at
                      FROM hr_payment_splits 
                      WHERE recipient_id = :recipient_id
                      ORDER BY created_at ASC";
        
        $splitsStmt = $pdo->prepare($splitsSql);
        $splitsStmt->bindParam(':recipient_id', $recipientId, PDO::PARAM_INT);
        $splitsStmt->execute();
        
        $recipient['splits'] = $splitsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch payment documents for this recipient
        $documentsSql = "SELECT 
                           document_id,
                           recipient_id,
                           file_name,
                           file_path,
                           file_type,
                           file_size,
                           uploaded_at
                         FROM hr_payment_documents 
                         WHERE recipient_id = :recipient_id
                         ORDER BY uploaded_at ASC";
        
        $documentsStmt = $pdo->prepare($documentsSql);
        $documentsStmt->bindParam(':recipient_id', $recipientId, PDO::PARAM_INT);
        $documentsStmt->execute();
        
        $recipient['documents'] = $documentsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format recipient data
        $recipient['formatted_amount'] = '₹' . number_format($recipient['amount'], 2);
        
        // Proper category display logic
        if ($recipient['category'] == 'vendor') {
            $recipient['display_category'] = 'Vendor';
        } elseif ($recipient['category'] == 'labour') {
            $recipient['display_category'] = 'Labour';
        } else {
            $recipient['display_category'] = ucwords(str_replace('_', ' ', $recipient['category']));
        }
        
        $recipient['display_type'] = ucwords(str_replace('_', ' ', $recipient['type']));
        $recipient['display_payment_mode'] = ucwords(str_replace('_', ' ', $recipient['payment_mode']));
        
        // Debug logging - remove this after fixing
        error_log("Recipient {$recipient['recipient_id']}: category='{$recipient['category']}' -> display_category='{$recipient['display_category']}'");
        
        // Format splits data
        foreach ($recipient['splits'] as &$split) {
            $split['formatted_amount'] = '₹' . number_format($split['amount'], 2);
            $split['display_payment_mode'] = ucwords(str_replace('_', ' ', $split['payment_mode']));
            
            // Format split date
            if ($split['created_at']) {
                $splitDate = new DateTime($split['created_at']);
                $split['formatted_date'] = $splitDate->format('M d, Y H:i');
            }
        }
        
        // Format documents data
        foreach ($recipient['documents'] as &$document) {
            $document['formatted_file_size'] = $document['file_size'] ? formatFileSize($document['file_size']) : 'Unknown';
            $document['display_file_type'] = strtoupper($document['file_type']);
            
            // Ensure file path is properly formatted for web display
            // Remove any leading '../' or './' to normalize paths
            $document['file_path'] = ltrim($document['file_path'], './');
            
            // Format upload date
            if ($document['uploaded_at']) {
                $uploadDate = new DateTime($document['uploaded_at']);
                $document['formatted_upload_date'] = $uploadDate->format('M d, Y H:i');
            }
        }
        
        // Format recipient date
        if ($recipient['created_at']) {
            $recipientDate = new DateTime($recipient['created_at']);
            $recipient['formatted_date'] = $recipientDate->format('M d, Y H:i');
        }
    }

    // Format main payment entry data
    $paymentEntry['formatted_payment_amount'] = '₹' . number_format($paymentEntry['payment_amount'], 2);
    $paymentEntry['display_project_type'] = ucwords(str_replace('_', ' ', $paymentEntry['project_type']));
    $paymentEntry['display_payment_mode'] = ucwords(str_replace('_', ' ', $paymentEntry['payment_mode']));
    
    // Display username instead of user ID for payment done via
    $paymentEntry['display_payment_done_via'] = $paymentEntry['payment_done_via_username'] ?: 'System';
    
    // Format payment date
    if ($paymentEntry['payment_date']) {
        $paymentDate = new DateTime($paymentEntry['payment_date']);
        $paymentEntry['formatted_payment_date'] = $paymentDate->format('M d, Y');
    } else {
        $paymentEntry['formatted_payment_date'] = 'Not specified';
    }
    
    // Format created and updated dates
    if ($paymentEntry['created_at']) {
        $createdAt = new DateTime($paymentEntry['created_at']);
        $paymentEntry['formatted_created_at'] = $createdAt->format('M d, Y H:i');
    }
    
    if ($paymentEntry['updated_at']) {
        $updatedAt = new DateTime($paymentEntry['updated_at']);
        $paymentEntry['formatted_updated_at'] = $updatedAt->format('M d, Y H:i');
    }
    
    // Calculate totals
    $totalRecipientAmount = array_sum(array_column($recipients, 'amount'));
    $totalSplitsAmount = 0;
    foreach ($recipients as $recipient) {
        $totalSplitsAmount += array_sum(array_column($recipient['splits'], 'amount'));
    }
    
    // Set default values for user tracking fields if they don't exist
    if (!$hasCreatedBy) {
        $paymentEntry['created_by'] = null;
        $paymentEntry['updated_by'] = null;
        $paymentEntry['created_by_username'] = 'System';
        $paymentEntry['updated_by_username'] = 'System';
        $paymentEntry['created_by_role'] = null;
        $paymentEntry['updated_by_role'] = null;
    }
    
    // Set default for payment done via if username not found
    if (!isset($paymentEntry['payment_done_via_username']) || empty($paymentEntry['payment_done_via_username'])) {
        $paymentEntry['payment_done_via_username'] = 'System';
        $paymentEntry['display_payment_done_via'] = 'System';
    }
    
    // Return successful response
    echo json_encode([
        'status' => 'success',
        'payment_entry' => $paymentEntry,
        'recipients' => $recipients,
        'summary' => [
            'total_recipients' => count($recipients),
            'total_splits' => array_sum(array_map(function($r) { return count($r['splits']); }, $recipients)),
            'total_documents' => array_sum(array_map(function($r) { return count($r['documents']); }, $recipients)),
            'total_recipient_amount' => $totalRecipientAmount,
            'total_splits_amount' => $totalSplitsAmount,
            'formatted_total_recipient_amount' => '₹' . number_format($totalRecipientAmount, 2),
            'formatted_total_splits_amount' => '₹' . number_format($totalSplitsAmount, 2)
        ]
    ]);
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Error fetching payment entry details: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch payment entry details'
    ]);
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
        return $bytes . ' bytes';
    }
}
?>