<?php
/**
 * Payment Entry Details API v2
 * Enhanced version with better error handling and output buffering
 * Following project specification for API error handling
 */

// Include utility functions
require_once __DIR__ . '/../includes/utils.php';

// Prevent any output before headers (following project specification)
ob_start();

// Clear any previous output
if (ob_get_level()) {
    ob_clean();
}

// Set headers first (following project specification)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// Error reporting based on environment
if (in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) || strpos($_SERVER['HTTP_HOST'], '.local') !== false) {
    // Development environment
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Don't display, we'll handle errors ourselves
} else {
    // Production environment
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Function to send JSON response and exit
function sendJsonResponse($data, $httpCode = 200) {
    // Clean any output buffer content
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Set HTTP response code
    http_response_code($httpCode);
    
    // Output JSON with proper encoding
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    // End output buffering and exit
    ob_end_flush();
    exit;
}

// Function to send error response
function sendErrorResponse($message, $httpCode = 500, $details = null) {
    $response = [
        'status' => 'error',
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Add details in development environment
    if (in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) && $details) {
        $response['debug'] = $details;
    }
    
    sendJsonResponse($response, $httpCode);
}

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendErrorResponse('Only GET method is allowed', 405);
    }
    
    // Get and validate payment ID
    $payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($payment_id <= 0) {
        sendErrorResponse('Invalid payment ID provided. Please provide a valid positive integer.', 400);
    }
    
    // Try to include database connection with multiple path attempts
    $db_connected = false;
    $db_paths = [
        __DIR__ . '/../config/db_connect.php',
        dirname(__DIR__) . '/config/db_connect.php',
        '../config/db_connect.php'
    ];
    
    $db_error = '';
    foreach ($db_paths as $path) {
        if (file_exists($path)) {
            try {
                require_once $path;
                $db_connected = true;
                break;
            } catch (Exception $e) {
                $db_error = $e->getMessage();
            }
        }
    }
    
    if (!$db_connected) {
        sendErrorResponse('Database connection failed', 500, [
            'paths_checked' => $db_paths,
            'last_error' => $db_error
        ]);
    }
    
    // Check if PDO connection is available
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        sendErrorResponse('Database connection not available', 500);
    }
    
    // Test database connection
    try {
        $pdo->query("SELECT 1");
    } catch (Exception $e) {
        sendErrorResponse('Database connection test failed', 500, ['error' => $e->getMessage()]);
    }
    
    // Query to get payment entry details
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
        sendErrorResponse("Payment entry not found for ID: {$payment_id}", 404);
    }
    
    // Format the data for UI display
    $formatted_data = [
        'payment_id' => $payment_entry['payment_id'],
        'project_title' => $payment_entry['project_title'],
        'project_type' => ucfirst(str_replace('_', ' ', $payment_entry['project_type'])),
        'project_id' => $payment_entry['project_id'],
        
        // Format payment amount using utility function
        'payment_amount' => $payment_entry['payment_amount'],
        'formatted_payment_amount' => formatCurrency($payment_entry['payment_amount']),
        
        // Format payment date using utility function
        'payment_date' => $payment_entry['payment_date'],
        'formatted_payment_date' => formatDateTime($payment_entry['payment_date'], 'F j, Y'),
        
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
        
        // Timestamps using utility function
        'created_at' => $payment_entry['created_at'],
        'updated_at' => $payment_entry['updated_at'],
        'formatted_created_at' => formatDateTime($payment_entry['created_at']),
        'formatted_updated_at' => formatDateTime($payment_entry['updated_at']),
        
        // Payment proof
        'payment_proof_image' => $payment_entry['payment_proof_image'],
        'has_payment_proof' => !empty($payment_entry['payment_proof_image']),
        
        // Generate reference ID using utility function
        'reference_id' => generateReferenceId($payment_entry['payment_id'], $payment_entry['created_at']),
        
        // Status
        'status' => 'Completed',
        'status_class' => 'success'
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
            $formatted_data['formatted_file_size'] = formatFileSize(filesize($full_file_path));
            $formatted_data['payment_proof_type'] = getSafeMimeType($full_file_path);
            $formatted_data['is_image'] = isImageFile($proof_path);
            $formatted_data['is_pdf'] = isPdfFile($proof_path);
        }
    }
    
    // Fetch payment recipients (simplified for this version)
    $recipients_query = "
        SELECT 
            pr.recipient_id,
            pr.category,
            pr.name,
            pr.payment_for,
            pr.amount
        FROM hr_payment_recipients pr
        WHERE pr.payment_id = :payment_id
        ORDER BY pr.recipient_id ASC
    ";
    
    $recipients_stmt = $pdo->prepare($recipients_query);
    $recipients_stmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
    $recipients_stmt->execute();
    
    $payment_recipients = $recipients_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format recipients data
    $formatted_recipients = [];
    foreach ($payment_recipients as $recipient) {
        $formatted_recipients[] = [
            'recipient_id' => $recipient['recipient_id'],
            'category' => $recipient['category'],
            'name' => $recipient['name'],
            'payment_for' => $recipient['payment_for'],
            'amount' => $recipient['amount'],
            'formatted_amount' => formatCurrency($recipient['amount'])
        ];
    }
    
    // Add recipients to response
    $formatted_data['recipients'] = $formatted_recipients;
    $formatted_data['recipients_count'] = count($formatted_recipients);
    $formatted_data['has_recipients'] = count($formatted_recipients) > 0;
    
    // Send success response
    sendJsonResponse([
        'status' => 'success',
        'message' => 'Payment entry details retrieved successfully',
        'payment_entry' => $formatted_data,
        'timestamp' => date('Y-m-d H:i:s'),
        'api_version' => '2.0'
    ]);
    
} catch (PDOException $e) {
    sendErrorResponse('Database error occurred', 500, [
        'error_code' => $e->getCode(),
        'error_message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    sendErrorResponse('An unexpected error occurred', 500, [
        'error_message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Throwable $e) {
    // Catch any remaining errors
    sendErrorResponse('Critical error occurred', 500, [
        'error_message' => $e->getMessage()
    ]);
}

// This should never be reached, but just in case
sendErrorResponse('Unknown error occurred', 500);
?>