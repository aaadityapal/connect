<?php
/**
 * AJAX handler to fetch travel expense details by ID
 */

// Include database connection
require_once '../config/db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Expense ID is required'
    ]);
    exit;
}

$expenseId = intval($_GET['id']);

try {
    // Prepare query to fetch expense details
    $query = "SELECT te.*, 
                     u.username, 
                     u.designation, 
                     u.profile_picture,
                     u2.username as updated_by_name
              FROM travel_expenses te
              LEFT JOIN users u ON te.user_id = u.id
              LEFT JOIN users u2 ON te.updated_by = u2.id
              WHERE te.id = :id";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $expenseId, PDO::PARAM_INT);
    $stmt->execute();
    
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expense) {
        echo json_encode([
            'success' => false,
            'message' => 'Expense not found'
        ]);
        exit;
    }
    
    // Format dates for better readability
    if (!empty($expense['created_at'])) {
        $expense['created_at_formatted'] = date('M d, Y h:i A', strtotime($expense['created_at']));
    }
    
    if (!empty($expense['updated_at'])) {
        $expense['updated_at_formatted'] = date('M d, Y h:i A', strtotime($expense['updated_at']));
    }
    
    if (!empty($expense['travel_date'])) {
        $expense['travel_date_formatted'] = date('M d, Y', strtotime($expense['travel_date']));
    }
    
    if (!empty($expense['paid_on_date'])) {
        $expense['paid_on_date_formatted'] = date('M d, Y', strtotime($expense['paid_on_date']));
    }
    
    // Return success response with expense data
    echo json_encode([
        'success' => true,
        'expense' => $expense
    ]);
    
} catch (PDOException $e) {
    // Log error for debugging (in a production environment)
    error_log('Database error in get_expense_details.php: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} 