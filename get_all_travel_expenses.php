<?php
// Start session for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Get user ID from request or session
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $_SESSION['user_id'];

// For security, only allow users to view their own data
// This ensures users can only see their own expenses
if ($user_id !== $_SESSION['user_id'] && !isset($_SESSION['role'])) {
    // Default to showing the user's own data if they try to access someone else's
    $user_id = $_SESSION['user_id'];
}

try {
    // Fetch all travel expenses for the user
    $stmt = $conn->prepare("
        SELECT 
            id,
            user_id,
            purpose,
            mode_of_transport,
            from_location,
            to_location,
            travel_date,
            distance,
            amount,
            status,
            notes,
            bill_file_path,
            created_at,
            updated_at
        FROM travel_expenses 
        WHERE user_id = ?
        ORDER BY travel_date DESC
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $expenses = [];
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
    
    // Return expenses as JSON
    header('Content-Type: application/json');
    echo json_encode($expenses);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 