<?php
/**
 * Get submitted punches for the last 15 days including today
 * This script fetches attendance records that have been submitted but not yet approved
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db_connect.php'; // Adjust path as needed

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = $pdo; // Use PDO connection from db_connect.php

try {
    // Calculate date 15 days ago (including today)
    $date_15_days_ago = date('Y-m-d', strtotime('-15 days'));
    $today = date('Y-m-d');
    
    // Fetch all submitted punch-in records for the last 15 days
    $query1 = "
        SELECT 
            id,
            user_id,
            date as attendance_date,
            punch_in_time as punch_time,
            reason,
            'punch_in' as punch_type,
            status,
            created_at
        FROM missing_punch_in 
        WHERE user_id = ? 
        AND date >= ?
        AND status IN ('pending', 'approved')
        ORDER BY date DESC
    ";
    
    $stmt1 = $conn->prepare($query1);
    $stmt1->execute([$user_id, $date_15_days_ago]);
    $submitted_punch_ins = $stmt1->fetchAll();
    
    // Fetch all submitted punch-out records for the last 15 days
    $query2 = "
        SELECT 
            id,
            user_id,
            date as attendance_date,
            punch_out_time as punch_time,
            reason,
            'punch_out' as punch_type,
            status,
            created_at
        FROM missing_punch_out 
        WHERE user_id = ? 
        AND date >= ?
        AND status IN ('pending', 'approved')
        ORDER BY date DESC
    ";
    
    $stmt2 = $conn->prepare($query2);
    $stmt2->execute([$user_id, $date_15_days_ago]);
    $submitted_punch_outs = $stmt2->fetchAll();
    
    // Combine both arrays
    $submitted_punches = array_merge($submitted_punch_ins, $submitted_punch_outs);
    
    // Create a map of dates and punch types to submitted records
    $submitted_map = [];
    foreach ($submitted_punches as $record) {
        $key = $record['attendance_date'] . '_' . $record['punch_type'];
        $submitted_map[$key] = $record;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $submitted_map,
        'count' => count($submitted_punches)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching data: ' . $e->getMessage()]);
}
?>