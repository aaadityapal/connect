<?php
// Include database connection
require_once 'config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get the export type and month/year parameters
$exportType = isset($_GET['type']) ? $_GET['type'] : 'work';
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : $_SESSION['user_id'];

// Prepare date range for the selected month
$startDate = "$year-$month-01";
$endDate = date('Y-m-t', strtotime($startDate));

// Query to fetch overtime notifications
try {
    $notificationsQuery = $pdo->prepare("
        SELECT overtime_id, message
        FROM overtime_notifications
        WHERE employee_id = :userId
        ORDER BY created_at DESC
    ");
    $notificationsQuery->execute([':userId' => $userId]);
    $overtimeNotifications = [];
    
    // Create a lookup array for easier access by overtime_id
    while ($notification = $notificationsQuery->fetch(PDO::FETCH_ASSOC)) {
        $overtimeNotifications[$notification['overtime_id']] = $notification['message'];
    }
    
    // Fetch attendance records for the month
    $attendanceQuery = $pdo->prepare("
        SELECT 
            a.id,
            a.date,
            a.punch_in,
            a.punch_out,
            a.work_report,
            a.overtime_status,
            a.overtime_hours,
            s.end_time as shift_end_time
        FROM attendance a
        LEFT JOIN user_shifts us ON a.user_id = us.user_id
            AND (
                (us.effective_to IS NULL OR us.effective_to >= a.date)
                AND us.effective_from <= a.date
            )
        LEFT JOIN shifts s ON us.shift_id = s.id
        WHERE a.user_id = :userId 
        AND DATE(a.date) BETWEEN :startDate AND :endDate
        AND a.punch_out IS NOT NULL
        AND a.overtime_hours > 0
        ORDER BY a.date ASC
    ");
    $attendanceQuery->execute([':userId' => $userId, ':startDate' => $startDate, ':endDate' => $endDate]);
    $attendanceRecords = $attendanceQuery->fetchAll();
    
    // Get user information for the export filename
    $userQuery = $pdo->prepare("SELECT username FROM users WHERE id = :userId");
    $userQuery->execute([':userId' => $userId]);
    $userData = $userQuery->fetch();
    $username = $userData ? str_replace(' ', '_', $userData['username']) : 'employee';
    
    // Generate a unique filename with timestamp
    $timestamp = date('YmdHis');
    $monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
    $reportType = ($exportType === 'work') ? 'work_reports' : 'overtime_reports';
    $filename = "{$username}_{$reportType}_{$monthName}_{$year}_{$timestamp}.csv";
    
    // Set headers to force download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Set column headers
    fputcsv($output, ['Date', 'Day', $exportType === 'work' ? 'Work Report' : 'Overtime Report']);
    
    // Add data rows
    foreach ($attendanceRecords as $record) {
        $date = date('d/m/Y', strtotime($record['date']));
        $day = date('l', strtotime($record['date']));
        
        // Get the appropriate report based on export type
        if ($exportType === 'work') {
            $report = $record['work_report'] ?? 'No report available';
        } else {
            $report = isset($overtimeNotifications[$record['id']]) ? $overtimeNotifications[$record['id']] : 'No report available';
        }
        
        fputcsv($output, [$date, $day, $report]);
    }
    
    // Close the output stream
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    error_log("Export Error: " . $e->getMessage());
    header('Content-Type: text/plain; charset=utf-8');
    echo "An error occurred during export. Please try again later.";
    exit;
} 