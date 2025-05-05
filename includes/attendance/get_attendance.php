<?php
// Include database connection
require_once '../../config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone to India Standard Time
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Get attendance ID from URL parameter
$attendanceId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$attendanceId) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid attendance ID'
    ]);
    exit;
}

try {
    // Get attendance details
    $stmt = $pdo->prepare("SELECT a.*, u.username 
                          FROM attendance a 
                          JOIN users u ON a.user_id = u.id 
                          WHERE a.id = ? AND a.user_id = ?");
    $stmt->execute([$attendanceId, $_SESSION['user_id']]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attendance) {
        echo json_encode([
            'success' => false,
            'message' => 'Attendance record not found'
        ]);
        exit;
    }
    
    // Format times for display
    $attendance['punch_in'] = date('h:i A', strtotime($attendance['punch_in'])) . ' IST';
    $attendance['punch_out'] = date('h:i A', strtotime($attendance['punch_out'])) . ' IST';
    
    echo json_encode([
        'success' => true,
        'attendance' => $attendance
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    
    // Log the error
    error_log('Get Attendance Error: ' . $e->getMessage());
} 