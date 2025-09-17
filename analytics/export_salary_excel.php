<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Start output buffering to catch any errors
ob_start();

// Start session and check authorization
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    die("Access denied. HR role required.");
}

// Include database connection
require_once '../config/db_connect.php';

// Get the filter month
$selected_filter_month = $_GET['filter_month'] ?? date('Y-m');

// Set headers for Excel download (CSV format)
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="salary_analytics_' . $selected_filter_month . '.xls"');
header('Cache-Control: max-age=0');

// Simple HTML table format (Excel compatible)
$excel_content = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Salary Analytics Report</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #4472C4; color: white; font-weight: bold; text-align: center; }
        .title { font-size: 18px; font-weight: bold; text-align: center; margin: 20px 0; }
        .currency { text-align: right; }
        .positive { color: #008000; }
        .negative { color: #FF0000; }
    </style>
</head>
<body>
    <div class="title">Salary Analytics Report - ' . date('F Y', strtotime($selected_filter_month)) . '</div>
    <table>
        <thead>
            <tr>
                <th>Employee Name</th>
                <th>Employee ID</th>
                <th>Email</th>
                <th>Base Salary (₹)</th>
                <th>Incremented Salary (₹)</th>
                <th>Working Days</th>
                <th>Present Days</th>
                <th>Excess Days</th>
                <th>Late Punch In</th>
                <th>Late Deduction (₹)</th>
                <th>Leave Taken</th>
                <th>Leave Types</th>
                <th>Leave Deduction (₹)</th>
                <th>1 Hour Late Days</th>
                <th>1 Hour Late Deduction (₹)</th>
                <th>4th Saturday Penalty (₹)</th>
                <th>Final Monthly Salary (₹)</th>
            </tr>
        </thead>
        <tbody>';

// Fetch basic user data first
$basic_users_query = "SELECT u.id, u.username, u.unique_id, u.email, u.base_salary, u.status
                      FROM users u
                      WHERE u.status = 'active' AND u.deleted_at IS NULL 
                      ORDER BY u.username ASC";

try {
    $stmt = $pdo->prepare($basic_users_query);
    $stmt->execute();
    $active_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add basic calculations for each user
    foreach ($active_users as &$user) {
        // Set default values
        $user['working_days'] = 26; // Default working days
        $user['present_days'] = 0;
        $user['excess_days'] = 0;
        $user['late_days'] = 0;
        $user['short_leave_days'] = 0;
        $user['late_deduction_days'] = 0;
        $user['late_deduction_amount'] = 0;
        $user['total_leave_days'] = 0;
        $user['leave_types_taken'] = 'None';
        $user['leave_deduction_days'] = 0;
        $user['leave_deduction_amount'] = 0;
        $user['one_hour_late_days'] = 0;
        $user['one_hour_late_deduction'] = 0;
        $user['one_hour_late_deduction_amount'] = 0;
        $user['fourth_saturday_penalty'] = 0;
        $user['fourth_saturday_penalty_amount'] = 0;
        $user['incremented_salary'] = $user['base_salary'] ?? 0;
        $user['monthly_salary_after_deductions'] = $user['incremented_salary'];
        
        // Get basic attendance data
        try {
            $attendance_query = "SELECT COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days
                                FROM attendance 
                                WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?";
            $attendance_stmt = $pdo->prepare($attendance_query);
            $attendance_stmt->execute([$user['id'], $selected_filter_month]);
            $attendance_result = $attendance_stmt->fetch(PDO::FETCH_ASSOC);
            $user['present_days'] = $attendance_result['present_days'] ?? 0;
        } catch (Exception $e) {
            error_log("Attendance query error for user {$user['id']}: " . $e->getMessage());
            // Continue with default values
        }
    }
    
    // Generate HTML table rows
    foreach ($active_users as $user) {
        $excel_content .= '<tr>';
        $excel_content .= '<td>' . htmlspecialchars($user['username']) . '</td>';
        $excel_content .= '<td>' . htmlspecialchars($user['unique_id'] ?? 'N/A') . '</td>';
        $excel_content .= '<td>' . htmlspecialchars($user['email']) . '</td>';
        $excel_content .= '<td class="currency">' . number_format($user['base_salary'] ?? 0) . '</td>';
        $excel_content .= '<td class="currency">' . number_format($user['incremented_salary']) . '</td>';
        $excel_content .= '<td>' . $user['working_days'] . '</td>';
        $excel_content .= '<td class="positive">' . ($user['present_days'] ?? 0) . '</td>';
        $excel_content .= '<td>' . $user['excess_days'] . '</td>';
        $excel_content .= '<td>' . ($user['late_days'] ?? 0) . '</td>';
        $excel_content .= '<td class="currency negative">' . number_format($user['late_deduction_amount'], 0) . '</td>';
        $excel_content .= '<td>' . $user['total_leave_days'] . '</td>';
        $excel_content .= '<td>' . htmlspecialchars($user['leave_types_taken']) . '</td>';
        $excel_content .= '<td class="currency negative">' . number_format($user['leave_deduction_amount'], 0) . '</td>';
        $excel_content .= '<td>' . $user['one_hour_late_days'] . '</td>';
        $excel_content .= '<td class="currency negative">' . number_format($user['one_hour_late_deduction_amount'], 0) . '</td>';
        $excel_content .= '<td class="currency negative">' . number_format($user['fourth_saturday_penalty_amount'], 0) . '</td>';
        $excel_content .= '<td class="currency">' . number_format($user['monthly_salary_after_deductions'], 0) . '</td>';
        $excel_content .= '</tr>';
    }
    
} catch (PDOException $e) {
    // Clear any output buffer
    ob_clean();
    error_log("Error fetching users for export: " . $e->getMessage());
    
    // Send error as HTML instead of trying to download
    header('Content-Type: text/html');
    echo "<h3>Error generating export</h3>";
    echo "<p>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='salary_analytics_dashboard.php'>Go back to dashboard</a></p>";
    exit;
} catch (Exception $e) {
    // Clear any output buffer
    ob_clean();
    error_log("General error in export: " . $e->getMessage());
    
    // Send error as HTML
    header('Content-Type: text/html');
    echo "<h3>Error generating export</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='salary_analytics_dashboard.php'>Go back to dashboard</a></p>";
    exit;
}

// Clear any previous output and send the Excel file
ob_clean();

// Close the HTML structure
$excel_content .= '
        </tbody>
    </table>
</body>
</html>';

// Output the Excel content
echo $excel_content;
?>