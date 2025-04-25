<?php
session_start();
// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Senior Manager (Studio)') {
    // Redirect to login page if not authorized
    header('Location: login.php');
    exit();
}

// Database connection
require_once 'config/db_connect.php';

// Initialize filter variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$user_filter = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$export_type = isset($_GET['format']) ? $_GET['format'] : 'csv'; // Default to CSV

// Build the SQL query with filters
$query = "SELECT a.id, a.user_id, a.date, a.punch_in, a.punch_out, a.working_hours, 
        a.overtime_hours, a.overtime, a.status, a.remarks, a.work_report, 
        u.username, u.designation, u.email 
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.overtime_hours > 0 ";

// Add date range filter
$query .= " AND a.date BETWEEN '$start_date' AND '$end_date'";

// Add user filter if selected
if (!empty($user_filter)) {
    $query .= " AND a.user_id = '$user_filter'";
}

// Add ordering
$query .= " ORDER BY a.date DESC";

// Execute query
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Database query failed: " . mysqli_error($conn));
}

// Generate file name
$file_name = 'overtime_report_' . date('Y-m-d') . '.' . ($export_type == 'excel' ? 'xls' : 'csv');

// Set headers based on export type
if ($export_type == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    $delimiter = "\t";
} else {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    $delimiter = ',';
}

// Create output file handle
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Define column headers
$headers = [
    'Employee Name',
    'Designation', 
    'Email',
    'Date',
    'Punch In',
    'Punch Out',
    'Working Hours',
    'Overtime Hours',
    'Status',
    'Remarks',
    'Work Report'
];

// Write headers to the file
fputcsv($output, $headers, $delimiter);

// Process and write each row
while ($row = mysqli_fetch_assoc($result)) {
    // Format data
    $formatted_row = [
        $row['username'],
        $row['designation'],
        $row['email'],
        date('d M, Y', strtotime($row['date'])),
        $row['punch_in'] ? date('h:i A', strtotime($row['punch_in'])) : 'N/A',
        $row['punch_out'] ? date('h:i A', strtotime($row['punch_out'])) : 'N/A',
        formatHoursValue($row['working_hours']) . ' hrs',
        formatHoursValue($row['overtime_hours']) . ' hrs',
        ucfirst($row['overtime'] ?? 'Pending'),
        $row['remarks'] ?: 'None',
        $row['work_report'] ?: 'None'
    ];
    
    // Write the row to the file
    fputcsv($output, $formatted_row, $delimiter);
}

// Function to format hours value that might be in time format
function formatHoursValue($value) {
    if (is_numeric($value)) {
        return number_format((float)$value, 2);
    } else if (strpos($value, ':') !== false) {
        // Convert time format (HH:MM:SS) to decimal hours
        $parts = explode(':', $value);
        $hours = (int)$parts[0];
        $minutes = isset($parts[1]) ? (int)$parts[1] / 60 : 0;
        $seconds = isset($parts[2]) ? (int)$parts[2] / 3600 : 0;
        return number_format($hours + $minutes + $seconds, 2);
    }
    return $value;
}

// Close the output file handle
fclose($output);
exit();
?> 