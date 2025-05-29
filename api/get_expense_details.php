<?php
// Start session for authentication
session_start();

// Set header to return JSON
header('Content-Type: application/json');

// Debug message
error_log("API called: get_expense_details.php with ID: " . ($_GET['id'] ?? 'none'));

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Authentication required'
    ]);
    exit();
}

// Check if ID parameter is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid expense ID'
    ]);
    exit();
}

// Get expense ID from request
$expense_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Include database connection
require_once('../includes/db_connect.php');

try {
    // Prepare SQL query to fetch expense details
    // Only allow users to view their own expenses unless they are an admin
    $query = "SELECT * FROM travel_expenses WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $expense_id, $user_id);
    
    // Execute query
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if expense exists
    if ($result->num_rows === 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Expense not found or you do not have permission to view it'
        ]);
        exit();
    }
    
    // Fetch expense details
    $expense = $result->fetch_assoc();
    
    // Check if bill file exists and add file info
    if (!empty($expense['bill_file'])) {
        $bill_file_path = '../uploads/bills/' . $expense['bill_file'];
        $expense['bill_file_exists'] = file_exists($bill_file_path);
        $expense['bill_file_url'] = 'uploads/bills/' . $expense['bill_file'];
        
        // Get file type for proper display
        $file_info = pathinfo($expense['bill_file']);
        $extension = strtolower($file_info['extension'] ?? '');
        
        // Determine if it's an image or other document
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        $expense['bill_is_image'] = in_array($extension, $image_extensions);
        $expense['bill_is_pdf'] = ($extension === 'pdf');
        $expense['bill_extension'] = $extension;
    }
    
    // Return success response with expense details
    echo json_encode([
        'status' => 'success',
        'expense' => $expense
    ]);
    
} catch (Exception $e) {
    // Log error (in a production environment)
    error_log("Error fetching expense details: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while fetching expense details'
    ]);
}
?> 