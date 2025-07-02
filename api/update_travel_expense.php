<?php
// Start session for authentication
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You must be logged in to update expenses'
    ]);
    exit();
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Include database connection
require_once('../includes/db_connect.php');

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Validate required parameters
$required_fields = ['expense_id', 'purpose', 'mode_of_transport', 'from_location', 'to_location', 'travel_date', 'distance', 'amount'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode([
            'status' => 'error',
            'message' => "Missing required field: {$field}"
        ]);
        exit();
    }
}

// Sanitize and validate inputs
$expense_id = intval($_POST['expense_id']);
$purpose = trim(mysqli_real_escape_string($conn, $_POST['purpose']));
$mode_of_transport = trim(mysqli_real_escape_string($conn, $_POST['mode_of_transport']));
$from_location = trim(mysqli_real_escape_string($conn, $_POST['from_location']));
$to_location = trim(mysqli_real_escape_string($conn, $_POST['to_location']));
$travel_date = trim(mysqli_real_escape_string($conn, $_POST['travel_date']));
$distance = floatval($_POST['distance']);
$amount = floatval($_POST['amount']);
$notes = isset($_POST['notes']) ? trim(mysqli_real_escape_string($conn, $_POST['notes'])) : '';
$updated_by = $user_id;

// Verify the expense belongs to this user and is not approved
$check_query = "SELECT id, status FROM travel_expenses WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param('ii', $expense_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You do not have permission to update this expense'
    ]);
    exit();
}

// Check if expense is already approved
$expense_data = $result->fetch_assoc();
if ($expense_data['status'] === 'approved') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Approved expenses cannot be edited'
    ]);
    exit();
}

// Process file upload if present
$file_upload_success = true;
$receipt_file_path = null;

if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
    $uploads_dir = '../uploads/bills/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploads_dir)) {
        mkdir($uploads_dir, 0777, true);
    }
    
    // Get file extension
    $file_extension = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid file type. Only JPG, JPEG, PNG, PDF, DOC, and DOCX files are allowed.'
        ]);
        exit();
    }
    
    // Generate unique filename
    $new_filename = 'receipt_' . time() . '_' . $expense_id . '.' . $file_extension;
    $upload_path = $uploads_dir . $new_filename;
    
    // Move uploaded file
    if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $upload_path)) {
        $receipt_file_path = $new_filename;
    } else {
        $file_upload_success = false;
    }
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Update expense record
    $update_query = "UPDATE travel_expenses SET 
                    purpose = ?,
                    mode_of_transport = ?,
                    from_location = ?,
                    to_location = ?,
                    travel_date = ?,
                    distance = ?,
                    amount = ?,
                    notes = ?,
                    updated_by = ?,
                    updated_at = NOW()";
    
    // Add receipt file path if file was uploaded
    if ($receipt_file_path) {
        $update_query .= ", receipt_file_path = ?, bill_file_path = ?";
    }
    
    $update_query .= " WHERE id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($update_query);
    
    // Bind parameters based on whether a file was uploaded
    if ($receipt_file_path) {
        $stmt->bind_param('sssssddsssii', 
                          $purpose, 
                          $mode_of_transport, 
                          $from_location, 
                          $to_location, 
                          $travel_date, 
                          $distance, 
                          $amount, 
                          $notes, 
                          $updated_by, 
                          $receipt_file_path,
                          $receipt_file_path,
                          $expense_id, 
                          $user_id);
    } else {
        $stmt->bind_param('sssssddssii', 
                          $purpose, 
                          $mode_of_transport, 
                          $from_location, 
                          $to_location, 
                          $travel_date, 
                          $distance, 
                          $amount, 
                          $notes, 
                          $updated_by,
                          $expense_id, 
                          $user_id);
    }
    
    $stmt->execute();
    
    // Check if update was successful
    if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Expense updated successfully',
            'data' => [
                'id' => $expense_id,
                'file_uploaded' => $receipt_file_path !== null,
            ]
        ]);
    } else {
        // Rollback transaction
        $conn->rollback();
        
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update expense'
        ]);
    }
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

// Close database connection
$conn->close(); 