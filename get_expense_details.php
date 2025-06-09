<?php
// Start session for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

// Check if user has the correct role
$allowed_roles = ['Senior Manager (Site)', 'Purchase Manager'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Permission denied']);
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Check if expense ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid expense ID']);
    exit();
}

$expense_id = intval($_GET['id']);

try {
    // Fetch expense details with all columns
    $stmt = $conn->prepare("
        SELECT 
            te.id,
            te.user_id,
            te.purpose,
            te.mode_of_transport,
            te.from_location,
            te.to_location,
            te.travel_date,
            te.distance,
            te.amount,
            te.notes,
            te.status,
            te.created_at,
            te.updated_at,
            te.bill_file_path,
            te.manager_status,
            te.accountant_status,
            te.hr_status,
            te.manager_reason,
            te.accountant_reason,
            te.hr_reason,
            u.username,
            u.unique_id as employee_id,
            u.profile_picture
        FROM travel_expenses te
        JOIN users u ON te.user_id = u.id
        WHERE te.id = ?
    ");
    $stmt->bind_param("i", $expense_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Return expense details as JSON
        header('Content-Type: application/json');
        echo json_encode($row);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Expense not found']);
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 