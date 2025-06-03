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

// Check if month and year parameters are provided
if (!isset($_GET['month']) || !isset($_GET['year'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Month and year parameters are required'
    ]);
    exit();
}

$month = intval($_GET['month']);
$year = intval($_GET['year']);
$userId = $_SESSION['user_id'];

// Validate month and year
if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid month or year'
    ]);
    exit();
}

try {
    // Include database connection
    require_once('config/db_connect.php');
    
    // Format date range for the selected month
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = date('Y-m-t', strtotime($startDate)); // Last day of the month
    
    // Query to get all work reports for the specified month
    // Only get reports for the logged-in user
    $query = "SELECT a.id, a.work_report, a.date, u.username 
              FROM attendance a 
              JOIN users u ON a.user_id = u.id
              WHERE a.date BETWEEN :startDate AND :endDate
              AND a.user_id = :userId
              AND a.work_report IS NOT NULL AND a.work_report != ''
              ORDER BY a.date ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':startDate' => $startDate,
        ':endDate' => $endDate,
        ':userId' => $userId
    ]);
    
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($reports) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'reports' => $reports
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No work reports found for this month'
        ]);
    }
} catch (PDOException $e) {
    error_log("Database Error in export_work_reports.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred while fetching work reports'
    ]);
}
?> 