<?php
// Start session for authentication
session_start();

// Set header to return JSON
header('Content-Type: application/json');

// Debug message
error_log("API called: get_expense_details_new.php with ID: " . ($_GET['id'] ?? 'none'));

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
    
    // Debug: Log all fields in the expense record
    error_log("Expense record fields: " . implode(", ", array_keys($expense)));
    
    // Check for bill-related fields with different possible names
    $possible_bill_fields = ['bill_file', 'bill_path', 'bill_file_path', 'receipt', 'receipt_file', 'attachment'];
    foreach ($possible_bill_fields as $field) {
        if (isset($expense[$field]) && !empty($expense[$field])) {
            error_log("Found bill field: $field with value: " . $expense[$field]);
            // Use this field as bill_file
            if ($field !== 'bill_file') {
                $expense['bill_file'] = $expense[$field];
            }
            break;
        }
    }
    
    // Check if bill file exists and add file info
    if (!empty($expense['bill_file'])) {
        // Special handling for the PDF file path we saw in the debug output
        if (strpos($expense['bill_file'], 'bill_') !== false && strpos($expense['bill_file'], '.pdf') !== false) {
            $expense['bill_is_pdf'] = true;
            $expense['bill_extension'] = 'pdf';
            
            // If the bill_file already contains a path like "uploads/bills/..."
            if (strpos($expense['bill_file'], 'uploads/') === 0) {
                $expense['bill_file_url'] = $expense['bill_file'];
                $expense['bill_file_exists'] = file_exists('../' . $expense['bill_file']);
            }
        }
        
        // Try different possible paths
        $possible_paths = [
            '../uploads/bills/' . $expense['bill_file'],
            '../uploads/receipts/' . $expense['bill_file'],
            '../uploads/' . $expense['bill_file'],
            $expense['bill_file'] // In case it's already a full path
        ];
        
        $file_exists = false;
        $file_path = '';
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $file_exists = true;
                $file_path = $path;
                break;
            }
        }
        
        $expense['bill_file_exists'] = $file_exists;
        
        // Create URL based on the path that exists
        if ($file_exists) {
            // Remove the leading '../' if present
            $url_path = preg_replace('/^\.\.\//', '', $file_path);
            $expense['bill_file_url'] = $url_path;
        } else {
            // Default URL
            $expense['bill_file_url'] = 'uploads/bills/' . $expense['bill_file'];
        }
        
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