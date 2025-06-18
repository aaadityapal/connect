<?php
session_start();
require_once 'config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Define allowed roles
$allowed_roles = ['HR', 'Senior Manager (Studio)'];
$has_access = (in_array($_SESSION['role'], $allowed_roles) || isset($_SESSION['temp_admin_access']));

if (!$has_access) {
    header('Location: unauthorized.php');
    exit();
}

// Get parameters
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : 'all';

// Get all days in the selected month
$first_day_of_month = date('Y-m-01', strtotime($month));
$last_day_of_month = date('Y-m-t', strtotime($month));
$all_dates = [];

$current_date = $first_day_of_month;
while (strtotime($current_date) <= strtotime($last_day_of_month)) {
    $all_dates[] = $current_date;
    $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
}

// Fetch attendance records
$query = "
    SELECT 
        a.date,
        u.username,
        u.unique_id,
        a.work_report
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE DATE_FORMAT(a.date, '%Y-%m') = :month
    " . ($user_id !== 'all' ? "AND a.user_id = :user_id" : "") . "
    ORDER BY a.date ASC, u.username ASC
";

$params = ['month' => $month];
if ($user_id !== 'all') {
    $params['user_id'] = $user_id;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$db_records = $stmt->fetchAll();

// Organize records by date
$records_by_date = [];
foreach ($db_records as $record) {
    $date = $record['date'];
    if (!isset($records_by_date[$date])) {
        $records_by_date[$date] = [];
    }
    $records_by_date[$date][] = $record;
}

// Format month for filename and display
$month_display = date('F_Y', strtotime($month . '-01'));

// Create a unique filename with timestamp
$timestamp = date('Ymd_His');
$filename = "WorkReport_{$month_display}_{$timestamp}.csv";

// Set headers for Excel download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create file pointer connected to PHP output stream
$output = fopen('php://output', 'w');

// Set column headers
fputcsv($output, [
    'Date',
    'Day',
    'Employee',
    'Employee ID',
    'Work Report'
]);

// Add rows for all dates
foreach ($all_dates as $date_str) {
    $date = strtotime($date_str);
    $formatted_date = date('d M Y', $date);
    $day_name = date('l', $date);
    
    if (isset($records_by_date[$date_str]) && !empty($records_by_date[$date_str])) {
        // We have records for this date
        foreach ($records_by_date[$date_str] as $record) {
            fputcsv($output, [
                $formatted_date,              // Date
                $day_name,                    // Day name (e.g., Monday)
                $record['username'],          // Employee name
                $record['unique_id'],         // Employee ID
                $record['work_report'] ?? ''  // Work Report (empty if null)
            ]);
        }
    } else {
        // No records for this date
        fputcsv($output, [
            $formatted_date,                  // Date
            $day_name,                        // Day name (e.g., Monday)
            '',                               // Employee name (blank)
            '',                               // Employee ID (blank)
            ''                                // Work Report (blank)
        ]);
    }
}

// Close the file pointer
fclose($output);
exit;
?>