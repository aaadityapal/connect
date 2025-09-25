<?php
// Start output buffering to prevent any unwanted output
ob_start();

// Prevent any PHP errors from being displayed in production
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Include database connection
require_once '../config/db_connect.php';

try {
    // Get payment ID from request
    $payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;
    
    if ($payment_id <= 0) {
        throw new Exception('Invalid payment ID provided: ' . $payment_id);
    }
    
    // Use the PDO connection from db_connect.php
    if (!isset($pdo)) {
        throw new Exception('Database connection not available - $pdo variable not set');
    }
    
    if (!($pdo instanceof PDO)) {
        throw new Exception('Database connection is not a valid PDO instance');
    }
    
    // Query to get split payment details
    $query = "
        SELECT 
            main_split_id,
            payment_id,
            amount,
            payment_mode,
            proof_file,
            created_at
        FROM hr_main_payment_splits 
        WHERE payment_id = :payment_id
        ORDER BY main_split_id ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $splits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the split data
    $formatted_splits = [];
    foreach ($splits as $index => $split) {
        $formatted_split = [
            'main_split_id' => $split['main_split_id'],
            'payment_id' => $split['payment_id'],
            'amount' => $split['amount'],
            'formatted_amount' => '₹' . number_format($split['amount'], 2),
            'payment_mode' => $split['payment_mode'],
            'display_payment_mode' => ucfirst(str_replace('_', ' ', $split['payment_mode'])),
            'proof_file' => $split['proof_file'],
            'has_proof' => !empty($split['proof_file']),
            'created_at' => $split['created_at'],
            'formatted_created_at' => date('F j, Y g:i A', strtotime($split['created_at'])),
            'split_number' => $index + 1
        ];
        
        // Add proof file information if exists
        if ($formatted_split['has_proof']) {
            $proof_path = $split['proof_file'];
            $formatted_split['proof_path'] = $proof_path;
            $formatted_split['proof_full_path'] = '../' . $proof_path;
            
            // Check if file exists safely
            $full_file_path = '../' . $proof_path;
            $formatted_split['proof_exists'] = false;
            
            try {
                if (!empty($full_file_path) && file_exists($full_file_path)) {
                    $formatted_split['proof_exists'] = true;
                    $formatted_split['proof_size'] = filesize($full_file_path);
                    
                    // Get file type using multiple methods for compatibility
                    $formatted_split['proof_type'] = 'application/octet-stream'; // default
                    
                    // Method 1: Try finfo (most reliable)
                    if (function_exists('finfo_file')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        if ($finfo) {
                            $mime_type = finfo_file($finfo, $full_file_path);
                            if ($mime_type) {
                                $formatted_split['proof_type'] = $mime_type;
                            }
                            finfo_close($finfo);
                        }
                    }
                    // Method 2: Try mime_content_type if available
                    elseif (function_exists('mime_content_type')) {
                        $mime_type = mime_content_type($full_file_path);
                        if ($mime_type) {
                            $formatted_split['proof_type'] = $mime_type;
                        }
                    }
                    // Method 3: Fallback to file extension
                    else {
                        $extension = strtolower(pathinfo($full_file_path, PATHINFO_EXTENSION));
                        $mime_types = [
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'pdf' => 'application/pdf',
                            'doc' => 'application/msword',
                            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'xls' => 'application/vnd.ms-excel',
                            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'txt' => 'text/plain',
                            'zip' => 'application/zip'
                        ];
                        if (isset($mime_types[$extension])) {
                            $formatted_split['proof_type'] = $mime_types[$extension];
                        }
                    }
                }
            } catch (Exception $fileException) {
                // If file operations fail, just mark as not existing
                $formatted_split['proof_exists'] = false;
            }
        }
        
        $formatted_splits[] = $formatted_split;
    }
    
    // Calculate summary
    $total_amount = array_sum(array_column($splits, 'amount'));
    $total_splits = count($splits);
    
    // Clear any unwanted output and return success response
    ob_end_clean();
    echo json_encode([
        'status' => 'success',
        'message' => 'Split payment details retrieved successfully',
        'splits' => $formatted_splits,
        'summary' => [
            'total_splits' => $total_splits,
            'total_amount' => $total_amount,
            'formatted_total_amount' => '₹' . number_format($total_amount, 2)
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Clear any unwanted output and return error response
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while processing the request',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (PDOException $e) {
    // Clear any unwanted output and return database error response
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Throwable $e) {
    // Clear any unwanted output and return general error response
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Ensure no additional output after this point
exit();
?>