<?php
// Simple diagnostic script to check resubmit functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// Set content type first
header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit;
    }

    // Include database connection
    include_once('includes/db_connect.php');

    // Check database connection
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
        exit;
    }

    // Check if POST data exists
    if (!isset($_POST['expense_id'])) {
        echo json_encode(['success' => false, 'message' => 'No expense_id provided']);
        exit;
    }

    $expense_id = intval($_POST['expense_id']);
    $user_id = $_SESSION['user_id'];

    // Check if expense exists
    $stmt = $conn->prepare("SELECT id, status, purpose FROM travel_expenses WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $expense_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Expense not found']);
        exit;
    }
    
    $expense = $result->fetch_assoc();
    $stmt->close();

    // Check table structure
    $columns_result = $conn->query("SHOW COLUMNS FROM travel_expenses");
    $columns = [];
    while ($row = $columns_result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    // Test the exact INSERT query that's failing
    if ($expense['status'] == 'rejected') {
        try {
            // Test the INSERT statement
            $test_insert = $conn->prepare("
                INSERT INTO travel_expenses (
                    user_id, purpose, mode_of_transport, from_location, 
                    to_location, travel_date, distance, amount, status, notes, 
                    bill_file_path, manager_status, accountant_status, hr_status, 
                    original_expense_id, resubmission_count, is_resubmitted, 
                    resubmitted_from, max_resubmissions, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, 'pending', 'pending', 'pending', ?, ?, 1, ?, ?, NOW())
            ");
            
            $insert_test_result = $test_insert ? 'Prepare successful' : 'Prepare failed: ' . $conn->error;
            
        } catch (Exception $e) {
            $insert_test_result = 'Exception: ' . $e->getMessage();
        }
    } else {
        $insert_test_result = 'Expense not rejected, cannot test INSERT';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Diagnostic completed successfully',
        'expense_found' => true,
        'expense_status' => $expense['status'],
        'expense_purpose' => $expense['purpose'],
        'user_id' => $user_id,
        'expense_id' => $expense_id,
        'table_columns' => $columns,
        'has_resubmission_columns' => in_array('resubmission_count', $columns),
        'insert_test_result' => $insert_test_result
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>