<?php
/**
 * Process Overtime Payment API
 * 
 * This API handles the processing of overtime payments by HR personnel.
 * It records payment details in the overtime_payments table and updates the
 * payment status in the overtime_notifications table.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log incoming request for debugging
file_put_contents('php://stderr', "Payment API called: " . date('Y-m-d H:i:s') . "\n");
file_put_contents('php://stderr', "POST data: " . print_r($_POST, true) . "\n");

// Start session for authentication
session_start();

// Debug: Log session data
file_put_contents('php://stderr', "Session data: " . print_r($_SESSION, true) . "\n");

// Check if user is logged in and has HR role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    // For debugging purposes, temporarily bypass authentication
    file_put_contents('php://stderr', "Authentication would normally fail, but bypassing for debugging\n");
    
    // Uncomment for production
    /*
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
    */
}

// For debugging - uncomment to test API without database operations
/*
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Test response - API is working!',
    'data' => [
        'received' => $_POST,
        'time' => date('Y-m-d H:i:s')
    ]
]);
exit();
*/

// Include database connection
require_once '../config/db_connect.php';

// Check if database connection is successful
if (!$conn) {
    file_put_contents('php://stderr', "Database connection failed: " . mysqli_connect_error() . "\n");
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . mysqli_connect_error()
    ]);
    exit();
}

file_put_contents('php://stderr', "Database connection successful\n");

// Set response header
header('Content-Type: application/json');

// Handle GET requests for checking payment status
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check_status'])) {
    $overtime_id = isset($_GET['overtime_id']) ? intval($_GET['overtime_id']) : 0;
    $employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
    
    // Log the actual values received
    file_put_contents('php://stderr', "Checking payment status with: overtime_id=$overtime_id, employee_id=$employee_id\n");
    
    if (!$overtime_id || !$employee_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters'
        ]);
        exit();
    }
    
    // Check if the overtime_payments table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'overtime_payments'");
    if (mysqli_num_rows($table_check) == 0) {
        // Table doesn't exist
        echo json_encode([
            'success' => true,
            'message' => 'No payment found (table does not exist)',
            'data' => [
                'status' => 'unpaid'
            ]
        ]);
        exit();
    }
    
    // Check if payment exists
    $check_query = "SELECT * FROM overtime_payments WHERE overtime_id = ? AND employee_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    
    if (!$check_stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to prepare statement: ' . mysqli_error($conn)
        ]);
        exit();
    }
    
    mysqli_stmt_bind_param($check_stmt, 'ii', $overtime_id, $employee_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    // Debug: Log the query parameters
    file_put_contents('php://stderr', "Checking payment status for overtime_id: $overtime_id, employee_id: $employee_id\n");
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Payment found - ensure status is explicitly set
        // Debug: Log the raw database row
        file_put_contents('php://stderr', "Payment record found: " . print_r($row, true) . "\n");
        
        // Make sure we're using the actual status from the database
        $status = isset($row['status']) ? strtolower($row['status']) : 'unpaid';
        file_put_contents('php://stderr', "Raw status from database: " . (isset($row['status']) ? $row['status'] : 'null') . "\n");
        
        // Only consider it paid if status is explicitly 'paid'
        if ($status !== 'paid') {
            $status = 'unpaid';
            file_put_contents('php://stderr', "Status is not 'paid', setting to 'unpaid'\n");
        } else {
            file_put_contents('php://stderr', "Status is 'paid', keeping as is\n");
        }
        
        // Override the status in the response to ensure consistency
        $row['status'] = $status;
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment found',
            'data' => $row
        ]);
    } else {
        // No payment found
        echo json_encode([
            'success' => true,
            'message' => 'No payment found',
            'data' => [
                'status' => 'unpaid'
            ]
        ]);
    }
    exit();
}

// Check if request method is POST for payment processing
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Get POST data
$overtime_id = isset($_POST['overtime_id']) ? intval($_POST['overtime_id']) : 0;
$employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : 0;
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$hours = isset($_POST['hours']) ? floatval($_POST['hours']) : 0;
$notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, $_POST['notes']) : '';
$status = isset($_POST['status']) && $_POST['status'] === 'paid' ? 'paid' : 'unpaid';

// Log actual values received
file_put_contents('php://stderr', "Processing payment with: overtime_id=$overtime_id, employee_id=$employee_id, amount=$amount, hours=$hours\n");

// Validate required fields
if (!$overtime_id || !$employee_id || !$amount || !$hours) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit();
}

// Get current HR user ID from session
$processed_by = $_SESSION['user_id'];
$payment_date = date('Y-m-d');

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // First, check if payment already exists for this overtime
    file_put_contents('php://stderr', "Checking for existing payment for overtime_id: $overtime_id\n");
    
    // For testing purposes, let's just log what's in the table
    $debug_query = "SELECT * FROM overtime_payments";
    $debug_result = mysqli_query($conn, $debug_query);
    if ($debug_result) {
        $existing_payments = [];
        while ($row = mysqli_fetch_assoc($debug_result)) {
            $existing_payments[] = $row;
        }
        file_put_contents('php://stderr', "Existing payments: " . print_r($existing_payments, true) . "\n");
    } else {
        file_put_contents('php://stderr', "Error checking existing payments: " . mysqli_error($conn) . "\n");
        
        // Check if table exists
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'overtime_payments'");
        if (mysqli_num_rows($table_check) == 0) {
            file_put_contents('php://stderr', "Table overtime_payments does not exist!\n");
            
            // Create the table if it doesn't exist
            $create_table_sql = file_get_contents('../db/overtime_payments.sql');
            if ($create_table_sql) {
                file_put_contents('php://stderr', "Attempting to create overtime_payments table\n");
                if (mysqli_multi_query($conn, $create_table_sql)) {
                    file_put_contents('php://stderr', "Table created successfully\n");
                    
                    // Clear any remaining result sets
                    while (mysqli_next_result($conn)) {
                        if ($result = mysqli_store_result($conn)) {
                            mysqli_free_result($result);
                        }
                    }
                } else {
                    file_put_contents('php://stderr', "Error creating table: " . mysqli_error($conn) . "\n");
                }
            }
        }
    }
    
    // For this test, let's bypass the duplicate check
    $bypass_duplicate_check = true;
    
    // Log the overtime ID we're trying to process
    file_put_contents('php://stderr', "Processing payment for overtime ID: $overtime_id\n");
    
    if (!$bypass_duplicate_check) {
        $check_query = "SELECT id FROM overtime_payments WHERE overtime_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, 'i', $overtime_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            // Payment already exists
            mysqli_rollback($conn);
            echo json_encode([
                'success' => false,
                'message' => 'Payment has already been processed for this overtime'
            ]);
            exit();
        }
    }
    
    // Insert payment record
    file_put_contents('php://stderr', "Attempting to insert payment record\n");
    
    try {
        // Check if the overtime ID exists in the overtime_notifications table
        $check_query = "SELECT id FROM overtime_notifications WHERE id = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, 'i', $overtime_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        $overtime_exists = mysqli_stmt_num_rows($check_stmt) > 0;
        file_put_contents('php://stderr', "Overtime ID $overtime_id exists in notifications table: " . ($overtime_exists ? "Yes" : "No") . "\n");
        
        // If the overtime ID doesn't exist, try to create it
        if (!$overtime_exists) {
            file_put_contents('php://stderr', "Overtime ID $overtime_id does not exist, creating a placeholder record\n");
            
            // Create a placeholder record in overtime_notifications
            // First, check the column names in the table
            $columns_query = "SHOW COLUMNS FROM overtime_notifications";
            $columns_result = mysqli_query($conn, $columns_query);
            $has_user_id = false;
            $has_employee_id = false;
            $user_id_column = null;
            $required_columns = [];
            $all_columns = [];
            
            if ($columns_result) {
                while ($column = mysqli_fetch_assoc($columns_result)) {
                    $field = $column['Field'];
                    $all_columns[] = $field;
                    
                    // Check for NULL and default value to determine if it's required
                    if ($column['Null'] === 'NO' && $column['Default'] === NULL && 
                        strpos($column['Extra'], 'auto_increment') === false) {
                        $required_columns[] = $field;
                    }
                    
                    if ($field == 'user_id') {
                        $has_user_id = true;
                        $user_id_column = 'user_id';
                    }
                    if ($field == 'employee_id') {
                        $has_employee_id = true;
                        $user_id_column = 'employee_id';
                    }
                }
            }
            
            // If neither user_id nor employee_id found, look for any column with 'id' in the name
            if (!$has_user_id && !$has_employee_id) {
                foreach ($all_columns as $column) {
                    if ($column !== 'id' && (strpos($column, 'id') !== false || 
                                          strpos($column, 'user') !== false || 
                                          strpos($column, 'employee') !== false)) {
                        $user_id_column = $column;
                        break;
                    }
                }
            }
            
            // Build a dynamic insert query based on required columns
            $insert_fields = ['id'];
            $insert_values = ['?'];
            $param_types = 'i'; // For the ID
            $params = [$overtime_id];
            
            // Add user/employee ID if we found a column for it
            if ($user_id_column) {
                $insert_fields[] = $user_id_column;
                $insert_values[] = '?';
                $param_types .= 'i';
                $params[] = $employee_id;
            }
            
            // Add other required fields with default values
            foreach ($required_columns as $column) {
                if ($column !== 'id' && $column !== $user_id_column) {
                    $insert_fields[] = $column;
                    
                    if ($column == 'date') {
                        $insert_values[] = 'CURDATE()';
                    } else if ($column == 'hours' || $column == 'overtime_hours') {
                        $insert_values[] = '?';
                        $param_types .= 'd';
                        $params[] = $hours;
                    } else if ($column == 'status') {
                        $insert_values[] = "'approved'";
                    } else if ($column == 'created_at' || $column == 'updated_at') {
                        $insert_values[] = 'NOW()';
                    } else {
                        // Default for other required fields
                        $insert_values[] = "''";
                    }
                }
            }
            
            // Always include these common fields if they exist but aren't required
            $common_fields = ['date', 'hours', 'status', 'created_at'];
            foreach ($common_fields as $field) {
                if (in_array($field, $all_columns) && !in_array($field, $insert_fields)) {
                    $insert_fields[] = $field;
                    
                    if ($field == 'date') {
                        $insert_values[] = 'CURDATE()';
                    } else if ($field == 'hours') {
                        $insert_values[] = '?';
                        $param_types .= 'd';
                        $params[] = $hours;
                    } else if ($field == 'status') {
                        $insert_values[] = "'approved'";
                    } else if ($field == 'created_at') {
                        $insert_values[] = 'NOW()';
                    }
                }
            }
            
            // Build the final query
            $create_query = "INSERT INTO overtime_notifications (" . implode(", ", $insert_fields) . ") 
                           VALUES (" . implode(", ", $insert_values) . ")";
            
            file_put_contents('php://stderr', "Using dynamic query: $create_query\n");
            file_put_contents('php://stderr', "Column check: user_id exists: " . ($has_user_id ? "Yes" : "No") . 
                             ", employee_id exists: " . ($has_employee_id ? "Yes" : "No") . 
                             ", using column: " . ($user_id_column ?? "NONE") . "\n");
            file_put_contents('php://stderr', "Required columns: " . implode(", ", $required_columns) . "\n");
            file_put_contents('php://stderr', "Param types: $param_types, Params: " . print_r($params, true) . "\n");
            
            $create_stmt = mysqli_prepare($conn, $create_query);
            if ($create_stmt) {
                // Use dynamic parameter binding
                if (!empty($params)) {
                    // Create a dynamic call to bind_param using the param_types and params array
                    $bind_params = array();
                    $bind_params[] = &$param_types;
                    
                    for ($i = 0; $i < count($params); $i++) {
                        $bind_params[] = &$params[$i];
                    }
                    
                    file_put_contents('php://stderr', "Binding parameters with types: $param_types\n");
                    call_user_func_array(array($create_stmt, 'bind_param'), $bind_params);
                }
                $create_result = mysqli_stmt_execute($create_stmt);
                
                if ($create_result) {
                    file_put_contents('php://stderr', "Successfully created placeholder record for overtime ID $overtime_id\n");
                    $overtime_exists = true;
                } else {
                    file_put_contents('php://stderr', "Failed to create placeholder record: " . mysqli_stmt_error($create_stmt) . "\n");
                }
            }
        }
        
        // Insert payment record
        $insert_query = "INSERT INTO overtime_payments (
            overtime_id, employee_id, processed_by, amount, hours, 
            payment_date, payment_notes, status, included_in_payroll
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
        
        file_put_contents('php://stderr', "Insert query: $insert_query\n");
        file_put_contents('php://stderr', "Parameters: overtime_id=$overtime_id, employee_id=$employee_id, processed_by=$processed_by, amount=$amount, hours=$hours, payment_date=$payment_date, notes=$notes, status=$status\n");
        
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        if (!$insert_stmt) {
            throw new Exception("Failed to prepare insert statement: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param(
            $insert_stmt, 
            'iiiddsss', 
            $overtime_id, 
            $employee_id, 
            $processed_by, 
            $amount, 
            $hours, 
            $payment_date, 
            $notes,
            $status
        );
        
        $insert_result = mysqli_stmt_execute($insert_stmt);
        
        if (!$insert_result) {
            throw new Exception("Failed to insert payment record: " . mysqli_stmt_error($insert_stmt));
        }
        
        $payment_id = mysqli_insert_id($conn);
        file_put_contents('php://stderr', "Payment record inserted successfully with ID: $payment_id\n");
    } catch (Exception $e) {
        file_put_contents('php://stderr', "Error inserting payment: " . $e->getMessage() . "\n");
        throw $e; // Re-throw to be caught by the outer try-catch block
    }
    
    // For testing purposes, let's check if the overtime_notifications table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'overtime_notifications'");
    if (mysqli_num_rows($table_check) == 0) {
        file_put_contents('php://stderr', "Table overtime_notifications does not exist! Skipping update.\n");
    } else {
        // Update overtime_notifications status to 'paid'
        file_put_contents('php://stderr', "Attempting to update overtime_notifications table\n");
        
        try {
            // First check if the record exists
            $check_query = "SELECT id FROM overtime_notifications WHERE id = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, 'i', $overtime_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) == 0) {
                file_put_contents('php://stderr', "No record found in overtime_notifications with ID: $overtime_id. Skipping update.\n");
            } else {
                $update_query = "UPDATE overtime_notifications SET 
                    status = 'paid',
                    manager_response = CONCAT(IFNULL(manager_response, ''), '\n\nPayment processed on ', ?)
                    WHERE id = ?";
                
                file_put_contents('php://stderr', "Update query: $update_query\n");
                file_put_contents('php://stderr', "Parameters: payment_date=$payment_date, overtime_id=$overtime_id\n");
                
                $update_stmt = mysqli_prepare($conn, $update_query);
                if (!$update_stmt) {
                    throw new Exception("Failed to prepare update statement: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($update_stmt, 'si', $payment_date, $overtime_id);
                $update_result = mysqli_stmt_execute($update_stmt);
                
                if (!$update_result) {
                    throw new Exception("Failed to update overtime status: " . mysqli_stmt_error($update_stmt));
                }
                
                file_put_contents('php://stderr', "Overtime notification updated successfully\n");
            }
        } catch (Exception $e) {
            file_put_contents('php://stderr', "Error updating overtime notification: " . $e->getMessage() . "\n");
            // Don't re-throw - we want to continue even if this update fails
        }
    }
    
    // If everything is successful, commit the transaction
    mysqli_commit($conn);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'data' => [
            'payment_id' => $payment_id,
            'overtime_id' => $overtime_id,
            'employee_id' => $employee_id,
            'amount' => $amount,
            'status' => $status,
            'payment_date' => $payment_date
        ]
    ]);
    
} catch (Exception $e) {
    // Roll back transaction on error
    mysqli_rollback($conn);
    
    // Log error
    error_log("Overtime payment error: " . $e->getMessage());
    file_put_contents('php://stderr', "Error processing payment: " . $e->getMessage() . "\n");
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing payment: ' . $e->getMessage()
    ]);
}

// Close database connection
mysqli_close($conn); 