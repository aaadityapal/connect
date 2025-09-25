<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database connection
require_once '../config/db_connect.php';

try {
    // Get payment ID from request
    $payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;
    
    if ($payment_id <= 0) {
        throw new Exception('Invalid payment ID provided');
    }
    
    // Use the PDO connection from db_connect.php
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    // Query to get split payment details with recipient information
    $query = "
        SELECT 
            hps.split_id,
            hps.recipient_id,
            hps.amount,
            hps.payment_mode,
            hps.payment_for,
            hps.proof_file,
            hps.created_at,
            
            -- Get recipient information from hr_payment_recipients
            hpr.name as recipient_name,
            hpr.category as recipient_category,
            hpr.type as recipient_type,
            
            -- Get created by user info if exists
            COALESCE(u.username, 'System') as created_by_name
            
        FROM hr_payment_splits hps
        LEFT JOIN hr_payment_recipients hpr ON hps.recipient_id = hpr.recipient_id
        LEFT JOIN users u ON hpr.created_by = u.id
        WHERE hpr.payment_id = :payment_id
        ORDER BY hps.split_id ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $split_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data for UI display
    $formatted_splits = [];
    $total_split_amount = 0;
    
    foreach ($split_details as $split) {
        $formatted_split = [
            'split_id' => $split['split_id'],
            'recipient_id' => $split['recipient_id'],
            'recipient_name' => $split['recipient_name'] ?: 'Unknown Recipient',
            'recipient_category' => $split['recipient_category'] ?: 'unknown',
            'recipient_type' => $split['recipient_type'] ?: 'unknown',
            'amount' => $split['amount'],
            'formatted_amount' => '₹' . number_format($split['amount'], 2),
            'payment_mode' => $split['payment_mode'],
            'display_payment_mode' => ucfirst(str_replace('_', ' ', $split['payment_mode'])),
            'payment_for' => $split['payment_for'],
            'proof_file' => $split['proof_file'],
            'has_proof' => !empty($split['proof_file']),
            'created_at' => $split['created_at'],
            'formatted_created_at' => date('F j, Y g:i A', strtotime($split['created_at'])),
            'created_by_name' => $split['created_by_name']
        ];
        
        // Add proof file information if exists
        if ($formatted_split['has_proof']) {
            $proof_path = $split['proof_file'];
            $formatted_split['proof_path'] = $proof_path;
            $formatted_split['proof_full_path'] = '../' . $proof_path;
            
            // Check if file exists
            $full_file_path = '../' . $proof_path;
            $formatted_split['proof_exists'] = file_exists($full_file_path);
            
            if ($formatted_split['proof_exists']) {
                $formatted_split['proof_size'] = filesize($full_file_path);
                $formatted_split['proof_type'] = mime_content_type($full_file_path);
            }
        }
        
        $formatted_splits[] = $formatted_split;
        $total_split_amount += $split['amount'];
    }
    
    // Create summary data
    $summary = [
        'total_splits' => count($formatted_splits),
        'total_amount' => $total_split_amount,
        'formatted_total_amount' => '₹' . number_format($total_split_amount, 2),
        'has_splits' => count($formatted_splits) > 0
    ];
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Split payment details retrieved successfully',
        'splits' => $formatted_splits,
        'summary' => $summary,
        'payment_id' => $payment_id,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (PDOException $e) {
    // Return database error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>