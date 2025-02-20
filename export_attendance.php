<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

try {
    // Get month and year
    $month = isset($_GET['month']) ? $_GET['month'] : date('m');
    $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
    
    // Connect to database
    require_once 'config/db_connect.php';
    
    // Modified query to match your actual database structure
    $query = "SELECT 
        u.username,
        a.date,
        a.punch_in,
        a.punch_out,
        a.overtime,
        CASE 
            WHEN a.punch_out IS NULL THEN 'Pending'
            ELSE 'Complete'
        END as status
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE MONTH(a.date) = ? AND YEAR(a.date) = ?
    ORDER BY a.date DESC, u.username";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param('ss', $month, $year);
    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    // Create filename
    $filename = 'Attendance_Report_' . date('F_Y', strtotime($year . '-' . $month . '-01')) . '.csv';
    
    // Remove any previous output
    if (ob_get_length()) ob_clean();
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    fputcsv($output, array(
        'Employee Name',
        'Date',
        'Punch In',
        'Punch Out',
        'Overtime',
        'Status'
    ));
    
    // Write data
    while ($row = $result->fetch_assoc()) {
        $exportRow = array(
            $row['username'],
            date('Y-m-d', strtotime($row['date'])),
            $row['punch_in'],
            $row['punch_out'] ?? 'Not Punched Out',
            $row['overtime'] ?? '0',
            $row['status']
        );
        fputcsv($output, $exportRow);
    }
    
    // Close file pointer
    fclose($output);
    exit();

} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    echo "Export Error: " . $e->getMessage();
    exit();
}
