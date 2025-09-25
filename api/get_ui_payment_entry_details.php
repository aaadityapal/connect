<?php
// Prevent any output before headers
ob_start();

// Enable error reporting for debugging (disable in production)
if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], 'conneqts.io') === false) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database connection
// Try multiple possible paths for the database connection
$possible_paths = [
    __DIR__ . '/../config/db_connect.php',
    dirname(__DIR__) . '/config/db_connect.php',
    '../config/db_connect.php',
    '../../config/db_connect.php'
];

$db_connected = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_connected = true;
        break;
    }
}

if (!$db_connected) {
    throw new Exception('Database connection file not found. Checked paths: ' . implode(', ', $possible_paths));
}

try {
    // Get payment ID from request
    $payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($payment_id <= 0) {
        throw new Exception('Invalid payment ID provided');
    }
    
    // Use the PDO connection from db_connect.php
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    // Query to get payment entry details with related information
    $query = "
        SELECT 
            pe.payment_id,
            pe.project_type,
            pe.project_id,
            pe.payment_date,
            pe.payment_amount,
            pe.payment_done_via,
            pe.payment_mode,
            pe.recipient_count,
            pe.created_by,
            pe.updated_by,
            pe.created_at,
            pe.updated_at,
            pe.payment_proof_image,
            
            -- Get project title from projects table
            COALESCE(p.title, CONCAT('Project #', pe.project_id)) as project_title,
            
            -- Get payment done via username
            COALESCE(pvu.username, 'Unknown User') as payment_via_username,
            
            -- Get created by user info
            COALESCE(cu.username, 'Unknown User') as created_by_name,
            COALESCE(cu.username, 'system') as created_by_username,
            
            -- Get updated by user info
            COALESCE(uu.username, 'Unknown User') as updated_by_name,
            COALESCE(uu.username, 'system') as updated_by_username
            
        FROM hr_payment_entries pe
        LEFT JOIN projects p ON pe.project_id = p.id
        LEFT JOIN users pvu ON pe.payment_done_via = pvu.id
        LEFT JOIN users cu ON pe.created_by = cu.id
        LEFT JOIN users uu ON pe.updated_by = uu.id
        WHERE pe.payment_id = :payment_id
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $payment_entry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment_entry) {
        throw new Exception('Payment entry not found');
    }
    
    // Format the data for UI display
    $formatted_data = [
        'payment_id' => $payment_entry['payment_id'],
        'project_title' => $payment_entry['project_title'],
        'project_type' => ucfirst(str_replace('_', ' ', $payment_entry['project_type'])),
        'project_id' => $payment_entry['project_id'],
        
        // Format payment amount
        'payment_amount' => $payment_entry['payment_amount'],
        'formatted_payment_amount' => '₹' . number_format($payment_entry['payment_amount'], 2),
        
        // Format payment date
        'payment_date' => $payment_entry['payment_date'],
        'formatted_payment_date' => date('F j, Y', strtotime($payment_entry['payment_date'])),
        
        // Payment method and mode
        'payment_done_via' => $payment_entry['payment_done_via'],
        'payment_mode' => $payment_entry['payment_mode'],
        'display_payment_mode' => ucfirst(str_replace('_', ' ', $payment_entry['payment_mode'])),
        'display_payment_via' => $payment_entry['payment_via_username'],
        
        // Recipient information
        'recipient_count' => $payment_entry['recipient_count'],
        
        // User information
        'created_by_name' => $payment_entry['created_by_name'],
        'created_by_username' => $payment_entry['created_by_username'],
        'updated_by_name' => $payment_entry['updated_by_name'],
        'updated_by_username' => $payment_entry['updated_by_username'],
        
        // Timestamps
        'created_at' => $payment_entry['created_at'],
        'updated_at' => $payment_entry['updated_at'],
        'formatted_created_at' => date('F j, Y g:i A', strtotime($payment_entry['created_at'])),
        'formatted_updated_at' => date('F j, Y g:i A', strtotime($payment_entry['updated_at'])),
        
        // Payment proof
        'payment_proof_image' => $payment_entry['payment_proof_image'],
        'has_payment_proof' => !empty($payment_entry['payment_proof_image']),
        
        // Generate reference ID
        'reference_id' => 'REF-' . $payment_entry['payment_id'] . '-' . date('Y', strtotime($payment_entry['created_at'])),
        
        // Determine status based on data (you may need to adjust this based on your business logic)
        'status' => 'Completed', // Default status, adjust as needed
        'status_class' => 'success' // For styling
    ];
    
    // Add payment proof file information if exists
    if ($formatted_data['has_payment_proof']) {
        $proof_path = $payment_entry['payment_proof_image'];
        $formatted_data['payment_proof_path'] = $proof_path;
        $formatted_data['payment_proof_full_path'] = '../' . $proof_path;
        
        // Check if file exists
        $full_file_path = '../' . $proof_path;
        $formatted_data['payment_proof_exists'] = file_exists($full_file_path);
        
        if ($formatted_data['payment_proof_exists']) {
            $formatted_data['payment_proof_size'] = filesize($full_file_path);
            
            // Use safer method to get mime type
            if (function_exists('mime_content_type')) {
                $formatted_data['payment_proof_type'] = mime_content_type($full_file_path);
            } elseif (function_exists('finfo_file')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $formatted_data['payment_proof_type'] = finfo_file($finfo, $full_file_path);
                finfo_close($finfo);
            } else {
                // Fallback based on file extension
                $extension = strtolower(pathinfo($full_file_path, PATHINFO_EXTENSION));
                $mime_types = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'pdf' => 'application/pdf',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ];
                $formatted_data['payment_proof_type'] = $mime_types[$extension] ?? 'application/octet-stream';
            }
        }
    }
    
    // Fetch payment recipients data
    $recipients_query = "
        SELECT 
            pr.recipient_id,
            pr.payment_id,
            pr.category,
            pr.type,
            pr.custom_type,
            pr.entity_id,
            pr.name,
            pr.payment_for,
            pr.amount,
            pr.payment_mode,
            pr.created_by,
            pr.updated_by,
            pr.created_at,
            
            -- Get created by user info for recipients
            COALESCE(rcu.username, 'Unknown User') as recipient_created_by_name
            
        FROM hr_payment_recipients pr
        LEFT JOIN users rcu ON pr.created_by = rcu.id
        WHERE pr.payment_id = :payment_id
        ORDER BY pr.recipient_id ASC
    ";
    
    $recipients_stmt = $pdo->prepare($recipients_query);
    $recipients_stmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
    $recipients_stmt->execute();
    
    $payment_recipients = $recipients_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format recipients data
    $formatted_recipients = [];
    $total_recipients_amount = 0;
    
    foreach ($payment_recipients as $recipient) {
        $formatted_recipient = [
            'recipient_id' => $recipient['recipient_id'],
            'category' => $recipient['category'],
            'type' => $recipient['type'],
            'custom_type' => $recipient['custom_type'],
            'entity_id' => $recipient['entity_id'],
            'name' => $recipient['name'],
            'payment_for' => $recipient['payment_for'],
            'amount' => $recipient['amount'],
            'formatted_amount' => '₹' . number_format($recipient['amount'], 2),
            'payment_mode' => $recipient['payment_mode'],
            'display_payment_mode' => ucfirst(str_replace('_', ' ', $recipient['payment_mode'])),
            'display_category' => ucfirst($recipient['category']),
            'display_type' => ucfirst(str_replace('_', ' ', $recipient['type'])),
            'created_by_name' => $recipient['recipient_created_by_name'],
            'created_at' => $recipient['created_at'],
            'formatted_created_at' => date('F j, Y g:i A', strtotime($recipient['created_at'])),
            'has_splits' => false,
            'splits' => []
        ];
        
        // Check if this recipient has split payments
        $split_query = "
            SELECT 
                split_id,
                amount,
                payment_mode,
                payment_for,
                proof_file,
                created_at
            FROM hr_payment_splits 
            WHERE recipient_id = :recipient_id
            ORDER BY split_id ASC
        ";
        
        $split_stmt = $pdo->prepare($split_query);
        $split_stmt->bindParam(':recipient_id', $recipient['recipient_id'], PDO::PARAM_INT);
        $split_stmt->execute();
        
        $recipient_splits = $split_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($recipient_splits)) {
            $formatted_recipient['has_splits'] = true;
            $splits_data = [];
            $total_split_amount = 0;
            
            foreach ($recipient_splits as $split) {
                $split_data = [
                    'split_id' => $split['split_id'],
                    'amount' => $split['amount'],
                    'formatted_amount' => '₹' . number_format($split['amount'], 2),
                    'payment_mode' => $split['payment_mode'],
                    'display_payment_mode' => ucfirst(str_replace('_', ' ', $split['payment_mode'])),
                    'payment_for' => $split['payment_for'],
                    'proof_file' => $split['proof_file'],
                    'has_proof' => !empty($split['proof_file']),
                    'created_at' => $split['created_at'],
                    'formatted_created_at' => date('F j, Y g:i A', strtotime($split['created_at']))
                ];
                
                // Add proof file information if exists
                if ($split_data['has_proof']) {
                    $proof_path = $split['proof_file'];
                    $split_data['proof_path'] = $proof_path;
                    $split_data['proof_full_path'] = '../' . $proof_path;
                    $split_data['proof_exists'] = file_exists('../' . $proof_path);
                }
                
                $splits_data[] = $split_data;
                $total_split_amount += $split['amount'];
            }
            
            $formatted_recipient['splits'] = $splits_data;
            $formatted_recipient['splits_count'] = count($splits_data);
            $formatted_recipient['total_split_amount'] = $total_split_amount;
            $formatted_recipient['formatted_total_split_amount'] = '₹' . number_format($total_split_amount, 2);
        }
        
        $formatted_recipients[] = $formatted_recipient;
        $total_recipients_amount += $recipient['amount'];
    }
    
    // Add recipients data to formatted response
    $formatted_data['recipients'] = $formatted_recipients;
    $formatted_data['recipients_count'] = count($formatted_recipients);
    $formatted_data['total_recipients_amount'] = $total_recipients_amount;
    $formatted_data['formatted_total_recipients_amount'] = '₹' . number_format($total_recipients_amount, 2);
    $formatted_data['has_recipients'] = count($formatted_recipients) > 0;
    
    // Clean any output buffer and return success response
    ob_clean();
    echo json_encode([
        'status' => 'success',
        'message' => 'Payment entry details retrieved successfully',
        'payment_entry' => $formatted_data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Clean any output buffer and return error response
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'file' => __FILE__,
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    // Clean any output buffer and return database error response
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'file' => __FILE__,
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // Catch any other errors
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unexpected error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'file' => __FILE__,
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

// Ensure no additional output
ob_end_flush();
exit;