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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database connection
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
    $payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;
    
    if ($payment_id <= 0) {
        throw new Exception('Invalid payment ID provided');
    }
    
    // Use the PDO connection from db_connect.php
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    // Query to get split payments for the payment entry
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
    
    $split_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the split payments data
    $formatted_splits = [];
    $total_split_amount = 0;
    
    foreach ($split_payments as $split) {
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
            'formatted_created_at' => date('F j, Y g:i A', strtotime($split['created_at']))
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
                $formatted_split['proof_filename'] = basename($proof_path);
                
                // Get file type
                $extension = strtolower(pathinfo($full_file_path, PATHINFO_EXTENSION));
                $formatted_split['proof_extension'] = $extension;
                $formatted_split['is_image'] = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                $formatted_split['is_pdf'] = $extension === 'pdf';
            }
        }
        
        $formatted_splits[] = $formatted_split;
        $total_split_amount += $split['amount'];
    }
    
    // Get main payment amount for comparison
    $main_payment_query = "SELECT payment_amount FROM hr_payment_entries WHERE payment_id = :payment_id";
    $main_stmt = $pdo->prepare($main_payment_query);
    $main_stmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
    $main_stmt->execute();
    $main_payment = $main_stmt->fetch(PDO::FETCH_ASSOC);
    
    $main_payment_amount = $main_payment ? $main_payment['payment_amount'] : 0;
    
    // Create summary
    $summary = [
        'total_splits' => count($formatted_splits),
        'total_split_amount' => $total_split_amount,
        'formatted_total_split_amount' => '₹' . number_format($total_split_amount, 2),
        'main_payment_amount' => $main_payment_amount,
        'formatted_main_payment_amount' => '₹' . number_format($main_payment_amount, 2),
        'amounts_match' => abs($total_split_amount - $main_payment_amount) < 0.01,
        'difference' => $main_payment_amount - $total_split_amount,
        'formatted_difference' => '₹' . number_format(abs($main_payment_amount - $total_split_amount), 2)
    ];
    
    // Clean any output buffer and return success response
    ob_clean();
    echo json_encode([
        'status' => 'success',
        'message' => 'Split payments retrieved successfully',
        'splits' => $formatted_splits,
        'summary' => $summary,
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
?>