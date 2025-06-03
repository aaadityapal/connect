<?php
// Start session for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit();
}

// Check if ID parameter is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request. Attendance ID is required.'
    ]);
    exit();
}

$attendanceId = intval($_GET['id']);
$userId = $_SESSION['user_id'];

try {
    // Include database connection
    require_once('config/db_connect.php');
    
    // Query to get work report for the specified attendance record
    // Only allow users to view their own reports unless they're an admin/manager
    $query = "SELECT a.work_report, a.user_id, a.date, u.username 
              FROM attendance a 
              JOIN users u ON a.user_id = u.id
              WHERE a.id = :id 
              AND (a.user_id = :userId OR :isManager = 1)";
    
    $isManager = in_array($_SESSION['role'], ['Site Manager', 'Senior Manager (Site)', 'Admin']) ? 1 : 0;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':id' => $attendanceId,
        ':userId' => $userId,
        ':isManager' => $isManager
    ]);
    
    $result = $stmt->fetch();
    
    if ($result) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'report' => $result['work_report'] ?: 'No work report submitted for this day.',
            'date' => $result['date'],
            'username' => $result['username']
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Work report not found or you do not have permission to view it.'
        ]);
    }
} catch (PDOException $e) {
    error_log("Database Error in get_work_report.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred while fetching the work report.'
    ]);
}
?> 