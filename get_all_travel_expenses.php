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
    // Fetch all travel expenses for the user including meter photos and resubmission data
    $stmt = $pdo->prepare("
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
            payment_status,
            notes,
            bill_file_path,
            meter_start_photo_path as meter_start_photo,
            meter_end_photo_path as meter_end_photo,
            manager_status,
            accountant_status,
            hr_status,
            manager_reason,
            accountant_reason,
            hr_reason,
            original_expense_id,
            resubmission_count,
            is_resubmitted,
            resubmitted_from,
            resubmission_date,
            max_resubmissions,
            created_at,
            updated_at
        FROM travel_expenses 
        WHERE user_id = ?
        ORDER BY travel_date DESC, created_at DESC
    ");
    
    $stmt->execute([$user_id]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return expenses as JSON
    header('Content-Type: application/json');
    echo json_encode($expenses);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 