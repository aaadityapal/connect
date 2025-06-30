<?php
// Database connection
require_once 'config/db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('m');
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Validate report type
if (!in_array($report_type, ['work', 'overtime'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid report type']);
    exit();
}

// Get user's shift information
$shift_query = "SELECT s.start_time, s.end_time, us.weekly_offs 
                FROM shifts s 
                JOIN user_shifts us ON s.id = us.shift_id 
                WHERE us.user_id = ? 
                AND (us.effective_to IS NULL OR us.effective_to >= CURDATE()) 
                AND us.effective_from <= CURDATE()
                ORDER BY us.effective_from DESC 
                LIMIT 1";

$shift_stmt = $conn->prepare($shift_query);
$shift_stmt->bind_param("i", $user_id);
$shift_stmt->execute();
$shift_result = $shift_stmt->get_result();
$shift_data = $shift_result->fetch_assoc();

// Default shift end time if no shift data found
$shift_end_time = $shift_data ? $shift_data['end_time'] : '18:00:00'; // 6:00 PM default

// Fetch data based on report type
$reports = [];

if ($report_type == 'work') {
    // Query for work reports
    $query = "SELECT a.date, a.work_report
              FROM attendance a
              WHERE a.user_id = ? 
              AND MONTH(a.date) = ? 
              AND YEAR(a.date) = ?
              AND a.work_report IS NOT NULL
              ORDER BY a.date ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $filter_month, $filter_year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $reports[] = [
            'date' => date('Y-m-d', strtotime($row['date'])),
            'report' => $row['work_report']
        ];
    }
} else {
    // Query for overtime reports
    $query = "SELECT a.date, a.id AS attendance_id, a.work_report AS report
              FROM attendance a
              WHERE a.user_id = ? 
              AND MONTH(a.date) = ? 
              AND YEAR(a.date) = ?
              AND a.punch_out IS NOT NULL
              AND TIME_TO_SEC(TIMEDIFF(a.punch_out, ?)) >= 5400
              ORDER BY a.date ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isss", $user_id, $filter_month, $filter_year, $shift_end_time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Check if there's an overtime notification message
        $report_message = $row['report'];
        
        // Try to get overtime notification message if table exists
        try {
            $notif_query = "SELECT message FROM overtime_notifications WHERE overtime_id = ? LIMIT 1";
            $notif_stmt = $conn->prepare($notif_query);
            if ($notif_stmt) {
                $attendance_id = $row['attendance_id'];
                $notif_stmt->bind_param("i", $attendance_id);
                $notif_stmt->execute();
                $notif_result = $notif_stmt->get_result();
                
                if ($notif_result && $notif_result->num_rows > 0) {
                    $notif_row = $notif_result->fetch_assoc();
                    if (!empty($notif_row['message'])) {
                        $report_message = $notif_row['message'];
                    }
                }
            }
        } catch (Exception $e) {
            // Silently ignore - table may not exist
        }
        
        $reports[] = [
            'date' => date('Y-m-d', strtotime($row['date'])),
            'report' => $report_message ?: 'No report available'
        ];
    }
}

// Return data as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'reports' => $reports
]); 