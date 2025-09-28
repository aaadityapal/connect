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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Only POST requests are accepted.'
    ]);
    exit;
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
    // Get user ID from session or set default (you may need to adjust this based on your auth system)
    session_start();
    $updated_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Default to 1 if no session
    
    // Handle both JSON and form data
    $input_data = [];
    
    // Check if we have multipart form data (for file uploads)
    if (isset($_POST['payment_id'])) {
        // Form data (with possible file upload)
        $input_data = $_POST;
        $has_file_upload = isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] !== UPLOAD_ERR_NO_FILE;
    } else {
        // JSON data
        $json_input = file_get_contents('php://input');
        $input_data = json_decode($json_input, true);
        $has_file_upload = false;
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data provided');
        }
    }
    
    // Validate required fields
    $required_fields = ['payment_id', 'project_id', 'payment_date', 'payment_amount', 'payment_mode', 'payment_done_via'];
    foreach ($required_fields as $field) {
        if (!isset($input_data[$field]) || empty($input_data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $payment_id = intval($input_data['payment_id']);
    $project_id = intval($input_data['project_id']);
    $payment_date = $input_data['payment_date'];
    $payment_amount = floatval($input_data['payment_amount']);
    $payment_mode = $input_data['payment_mode'];
    $payment_done_via = intval($input_data['payment_done_via']);
    $project_type = isset($input_data['project_type']) ? $input_data['project_type'] : null;
    $remove_current_proof = isset($input_data['remove_current_proof']) && $input_data['remove_current_proof'] === 'true';
    
    // Validate data
    if ($payment_id <= 0) {
        throw new Exception('Invalid payment ID');
    }
    
    if ($project_id <= 0) {
        throw new Exception('Invalid project ID');
    }
    
    if ($payment_amount <= 0) {
        throw new Exception('Payment amount must be greater than 0');
    }
    
    if (!in_array($payment_mode, ['cash', 'bank_transfer', 'upi', 'cheque', 'credit_card', 'debit_card', 'split_payment', 'other'])) {
        throw new Exception('Invalid payment mode');
    }
    
    if ($payment_done_via <= 0) {
        throw new Exception('Invalid payment done via user ID');
    }
    
    // Validate payment date
    $date_obj = DateTime::createFromFormat('Y-m-d', $payment_date);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $payment_date) {
        throw new Exception('Invalid payment date format. Use YYYY-MM-DD');
    }
    
    // Use the PDO connection from db_connect.php
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Check if payment entry exists
        $check_stmt = $pdo->prepare("SELECT payment_id, payment_proof_image FROM hr_payment_entries WHERE payment_id = ?");
        $check_stmt->execute([$payment_id]);
        $existing_entry = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing_entry) {
            throw new Exception('Payment entry not found');
        }
        
        // Check if project exists
        $project_check = $pdo->prepare("SELECT id FROM projects WHERE id = ?");
        $project_check->execute([$project_id]);
        if (!$project_check->fetch()) {
            throw new Exception('Selected project does not exist');
        }
        
        // Check if user exists
        $user_check = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $user_check->execute([$payment_done_via]);
        if (!$user_check->fetch()) {
            throw new Exception('Selected user does not exist');
        }
        
        // Handle file upload if present
        $new_proof_image = null;
        
        // Debug: Add logging for file upload detection
        $debug_info = [
            'has_file_upload' => $has_file_upload,
            'files_isset' => isset($_FILES['payment_proof']),
            'files_error' => isset($_FILES['payment_proof']) ? $_FILES['payment_proof']['error'] : 'not set',
            'remove_current_proof' => $remove_current_proof
        ];
        
        if ($has_file_upload && isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['payment_proof'];
            
            // Validate file
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('Invalid file type. Only JPG, PNG, and PDF files are allowed.');
            }
            
            if ($file['size'] > $max_size) {
                throw new Exception('File too large. Maximum size is 5MB.');
            }
            
            // Create upload directory
            $upload_dir = '../uploads/payment_proofs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'payment_proof_' . $payment_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                throw new Exception('Failed to upload file');
            }
            
            $new_proof_image = 'uploads/payment_proofs/' . $new_filename;
            
            // Delete old proof file if it exists
            if (!empty($existing_entry['payment_proof_image'])) {
                $old_file_path = '../' . $existing_entry['payment_proof_image'];
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }
        } elseif ($remove_current_proof) {
            // Remove current proof if requested
            if (!empty($existing_entry['payment_proof_image'])) {
                $old_file_path = '../' . $existing_entry['payment_proof_image'];
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }
            $new_proof_image = null;
        } else {
            // Keep existing proof image
            $new_proof_image = $existing_entry['payment_proof_image'];
        }
        
        // Update payment entry
        $update_query = "
            UPDATE hr_payment_entries 
            SET 
                project_id = ?,
                project_type = ?,
                payment_date = ?,
                payment_amount = ?,
                payment_mode = ?,
                payment_done_via = ?,
                payment_proof_image = ?,
                updated_by = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE payment_id = ?
        ";
        
        $update_stmt = $pdo->prepare($update_query);
        $update_result = $update_stmt->execute([
            $project_id,
            $project_type,
            $payment_date,
            $payment_amount,
            $payment_mode,
            $payment_done_via,
            $new_proof_image,
            $updated_by,
            $payment_id
        ]);
        
        if (!$update_result) {
            throw new Exception('Failed to update payment entry');
        }
        
        // Handle split payments if payment mode is split_payment
        if ($payment_mode === 'split_payment') {
            // Only delete and recreate split payments if new split data is provided
            if (isset($input_data['split_amounts']) && is_array($input_data['split_amounts'])) {
                // Get existing split payments before deletion to clean up old files
                $existing_splits_stmt = $pdo->prepare("SELECT proof_file FROM hr_main_payment_splits WHERE payment_id = ?");
                $existing_splits_stmt->execute([$payment_id]);
                $existing_splits = $existing_splits_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Delete existing split payments
                $delete_splits_stmt = $pdo->prepare("DELETE FROM hr_main_payment_splits WHERE payment_id = ?");
                $delete_splits_stmt->execute([$payment_id]);
                
                // Clean up old split proof files
                foreach ($existing_splits as $old_proof_file) {
                    if (!empty($old_proof_file)) {
                        $old_file_path = '../' . $old_proof_file;
                        if (file_exists($old_file_path)) {
                            unlink($old_file_path);
                        }
                    }
                }
                
                $split_amounts = $input_data['split_amounts'];
                $split_modes = isset($input_data['split_modes']) ? $input_data['split_modes'] : [];
                $split_ids = isset($input_data['split_ids']) ? $input_data['split_ids'] : [];
                
                // Handle split proof files
                $split_proofs = [];
                if (isset($_FILES['split_proofs']) && is_array($_FILES['split_proofs']['name'])) {
                    $split_proof_files = $_FILES['split_proofs'];
                    for ($i = 0; $i < count($split_proof_files['name']); $i++) {
                        if ($split_proof_files['error'][$i] === UPLOAD_ERR_OK) {
                            $file_name = $split_proof_files['name'][$i];
                            $file_tmp = $split_proof_files['tmp_name'][$i];
                            $file_type = $split_proof_files['type'][$i];
                            $file_size = $split_proof_files['size'][$i];
                            
                            // Validate file
                            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                            $max_size = 5 * 1024 * 1024; // 5MB
                            
                            if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
                                // Create upload directory
                                $upload_dir = '../uploads/split_payment_proofs/';
                                if (!is_dir($upload_dir)) {
                                    mkdir($upload_dir, 0777, true);
                                }
                                
                                // Generate unique filename
                                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                                $new_filename = 'split_proof_' . $payment_id . '_' . $i . '_' . time() . '.' . $file_extension;
                                $upload_path = $upload_dir . $new_filename;
                                
                                // Move uploaded file
                                if (move_uploaded_file($file_tmp, $upload_path)) {
                                    $split_proofs[$i] = 'uploads/split_payment_proofs/' . $new_filename;
                                }
                            }
                        }
                    }
                }
                
                // Insert split payments
                $insert_split_query = "
                    INSERT INTO hr_main_payment_splits 
                    (payment_id, amount, payment_mode, proof_file, created_at) 
                    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                ";
                $insert_split_stmt = $pdo->prepare($insert_split_query);
                
                for ($i = 0; $i < count($split_amounts); $i++) {
                    $split_amount = floatval($split_amounts[$i]);
                    $split_mode = isset($split_modes[$i]) ? $split_modes[$i] : 'cash';
                    $split_proof = isset($split_proofs[$i]) ? $split_proofs[$i] : null;
                    
                    if ($split_amount > 0) {
                        $insert_split_stmt->execute([
                            $payment_id,
                            $split_amount,
                            $split_mode,
                            $split_proof
                        ]);
                    }
                }
            }
            // If no split_amounts provided, keep existing split payments intact
        }
        // Note: We preserve split payments when payment mode is not 'split_payment'
        // Split payments should only be deleted when explicitly updated or when payment mode changes from split_payment to another mode with explicit user action
        
        // Get updated payment entry data
        $select_query = "
            SELECT 
                pe.payment_id,
                pe.project_type,
                pe.project_id,
                pe.payment_date,
                pe.payment_amount,
                pe.payment_done_via,
                pe.payment_mode,
                pe.payment_proof_image,
                pe.updated_at,
                
                -- Get project title
                COALESCE(p.title, CONCAT('Project #', pe.project_id)) as project_title,
                
                -- Get user info
                COALESCE(u.username, 'Unknown User') as payment_via_username
                
            FROM hr_payment_entries pe
            LEFT JOIN projects p ON pe.project_id = p.id
            LEFT JOIN users u ON pe.payment_done_via = u.id
            WHERE pe.payment_id = ?
        ";
        
        $select_stmt = $pdo->prepare($select_query);
        $select_stmt->execute([$payment_id]);
        $updated_entry = $select_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Commit transaction
        $pdo->commit();
        
        // Format response data
        $response_data = [
            'payment_id' => $updated_entry['payment_id'],
            'project_id' => $updated_entry['project_id'],
            'project_title' => $updated_entry['project_title'],
            'project_type' => $updated_entry['project_type'],
            'payment_date' => $updated_entry['payment_date'],
            'formatted_payment_date' => date('F j, Y', strtotime($updated_entry['payment_date'])),
            'payment_amount' => $updated_entry['payment_amount'],
            'formatted_payment_amount' => '₹' . number_format($updated_entry['payment_amount'], 2),
            'payment_mode' => $updated_entry['payment_mode'],
            'display_payment_mode' => ucfirst(str_replace('_', ' ', $updated_entry['payment_mode'])),
            'payment_done_via' => $updated_entry['payment_done_via'],
            'payment_via_username' => $updated_entry['payment_via_username'],
            'payment_proof_image' => $updated_entry['payment_proof_image'],
            'has_payment_proof' => !empty($updated_entry['payment_proof_image']),
            'updated_at' => $updated_entry['updated_at'],
            'formatted_updated_at' => date('F j, Y g:i A', strtotime($updated_entry['updated_at']))
        ];
        
        // Clean any output buffer and return success response
        ob_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'Payment entry updated successfully',
            'payment_entry' => $response_data,
            'debug_info' => $debug_info, // Add debug info to response
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Clean any output buffer and return error response
    ob_clean();
    http_response_code(400);
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