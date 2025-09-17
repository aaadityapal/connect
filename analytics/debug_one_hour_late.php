<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and check authorization
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    die("Access denied. HR role required.");
}

// Include database connection
require_once '../config/db_connect.php';

// Get parameters
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : date('Y-m');

if (!$user_id) {
    die("Please provide user_id parameter. Example: ?user_id=1&filter_month=2024-01");
}

echo "<h2>1-Hour Late Deduction Debug Report</h2>";
echo "<p><strong>User ID:</strong> {$user_id}</p>";
echo "<p><strong>Filter Month:</strong> {$filter_month}</p>";
echo "<hr>";

// Get user basic info
$user_query = "SELECT id, username, base_salary, shift_id FROM users WHERE id = ? AND status = 'active'";
$user_stmt = $pdo->prepare($user_query);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found or inactive.");
}

echo "<h3>User Information</h3>";
echo "<p><strong>Username:</strong> {$user['username']}</p>";
echo "<p><strong>Base Salary:</strong> ₹" . number_format($user['base_salary']) . "</p>";
echo "<p><strong>Shift ID:</strong> {$user['shift_id']}</p>";

// Get shift information
$shift_query = "SELECT s.shift_name, s.start_time FROM user_shifts us 
                LEFT JOIN shifts s ON us.shift_id = s.id 
                WHERE us.user_id = ? AND (us.effective_to IS NULL OR us.effective_to >= LAST_DAY(?))";
$shift_stmt = $pdo->prepare($shift_query);
$shift_stmt->execute([$user_id, $filter_month]);
$shift_info = $shift_stmt->fetch(PDO::FETCH_ASSOC);

$shift_start = $shift_info['start_time'] ?? '09:00:00';
echo "<p><strong>Shift Start Time:</strong> {$shift_start}</p>";

// Calculate working days
$month_start = date('Y-m-01', strtotime($filter_month));
$month_end = date('Y-m-t', strtotime($filter_month));

// Get weekly offs
$weekly_offs_query = "SELECT weekly_offs FROM user_shifts WHERE user_id = ? AND (effective_to IS NULL OR effective_to >= LAST_DAY(?))";
$weekly_offs_stmt = $pdo->prepare($weekly_offs_query);
$weekly_offs_stmt->execute([$user_id, $filter_month]);
$weekly_offs_result = $weekly_offs_stmt->fetch(PDO::FETCH_ASSOC);
$weekly_offs = !empty($weekly_offs_result['weekly_offs']) ? explode(',', $weekly_offs_result['weekly_offs']) : [];

echo "<p><strong>Weekly Offs:</strong> " . (empty($weekly_offs) ? 'None' : implode(', ', $weekly_offs)) . "</p>";

// Calculate working days
$working_days = 0;
$current_date = new DateTime($month_start);
$end_date = new DateTime($month_end);

while ($current_date <= $end_date) {
    $day_of_week = $current_date->format('l');
    if (!in_array($day_of_week, $weekly_offs)) {
        $working_days++;
    }
    $current_date->modify('+1 day');
}

echo "<p><strong>Working Days:</strong> {$working_days}</p>";

// Get incremented salary if any
$incremented_salary_query = "SELECT salary_after_increment FROM salary_increments 
                            WHERE user_id = ? AND DATE_FORMAT(effective_from, '%Y-%m') = ? 
                            AND status != 'cancelled' ORDER BY effective_from DESC LIMIT 1";
$incremented_stmt = $pdo->prepare($incremented_salary_query);
$incremented_stmt->execute([$user_id, $filter_month]);
$incremented_result = $incremented_stmt->fetch(PDO::FETCH_ASSOC);
$current_salary = $incremented_result['salary_after_increment'] ?? $user['base_salary'];

echo "<p><strong>Current Salary (for calculation):</strong> ₹" . number_format($current_salary) . "</p>";
echo "<p><strong>Daily Salary:</strong> ₹" . number_format($current_salary / $working_days, 2) . "</p>";

echo "<hr>";

// Get 1-hour late attendance records using TIMESTAMPDIFF for accuracy
$one_hour_late_query = "SELECT DATE(date) as attendance_date, TIME(punch_in) as punch_time,
                        TIMESTAMPDIFF(MINUTE, 
                            CONCAT(DATE(date), ' ', ?), 
                            CONCAT(DATE(date), ' ', TIME(punch_in))
                        ) as minutes_late
                        FROM attendance 
                        WHERE user_id = ? 
                        AND DATE_FORMAT(date, '%Y-%m') = ? 
                        AND status = 'present' 
                        AND TIMESTAMPDIFF(MINUTE, 
                            CONCAT(DATE(date), ' ', ?), 
                            CONCAT(DATE(date), ' ', TIME(punch_in))
                        ) > 60
                        ORDER BY date";
$one_hour_late_stmt = $pdo->prepare($one_hour_late_query);
$one_hour_late_stmt->execute([$shift_start, $user_id, $filter_month, $shift_start]);
$one_hour_late_records = $one_hour_late_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>1-Hour Late Records (SHOULD be > 60 minutes)</h3>";
echo "<p><strong>Threshold:</strong> Punch-in after " . date('H:i', strtotime($shift_start . ' +1 hour')) . " (more than 60 minutes late)</p>";
echo "<p><strong>Total Occurrences:</strong> " . count($one_hour_late_records) . " (should match dashboard)</p>";

if (!empty($one_hour_late_records)) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Date</th><th>Punch-in Time</th><th>Minutes Late</th><th>Should Count?</th></tr>";
    
    foreach ($one_hour_late_records as $record) {
        $minutes_late = $record['minutes_late'];
        $should_count = $minutes_late > 60 ? 'YES' : 'NO (Error!)';
        $row_color = $minutes_late > 60 ? '' : ' style="background-color: #fee2e2;"';
        
        echo "<tr{$row_color}>";
        echo "<td>{$record['attendance_date']}</td>";
        echo "<td>{$record['punch_time']}</td>";
        echo "<td>{$minutes_late} minutes</td>";
        echo "<td>{$should_count}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'><strong>✓ Correct:</strong> No 1-hour late records found (all punch-ins are ≤ 60 minutes late).</p>";
}

// Also show all late records for comparison (excluding 15-minute grace period)
echo "<hr>";
echo "<h3>All Late Records (for comparison) - Excluding ≤15 minute grace period</h3>";
$all_late_query = "SELECT DATE(date) as attendance_date, TIME(punch_in) as punch_time,
                   TIMESTAMPDIFF(MINUTE, 
                       CONCAT(DATE(date), ' ', ?), 
                       CONCAT(DATE(date), ' ', TIME(punch_in))
                   ) as minutes_late
                   FROM attendance 
                   WHERE user_id = ? 
                   AND DATE_FORMAT(date, '%Y-%m') = ? 
                   AND status = 'present' 
                   AND TIMESTAMPDIFF(MINUTE, 
                       CONCAT(DATE(date), ' ', ?), 
                       CONCAT(DATE(date), ' ', TIME(punch_in))
                   ) > 15
                   ORDER BY date";
$all_late_stmt = $pdo->prepare($all_late_query);
$all_late_stmt->execute([$shift_start, $user_id, $filter_month, $shift_start]);
$all_late_records = $all_late_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p><strong>Total Late Records (>15 min):</strong> " . count($all_late_records) . " (excludes ≤15 minute grace period)</p>";

if (!empty($all_late_records)) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Date</th><th>Punch-in Time</th><th>Minutes Late</th><th>Category</th></tr>";
    
    foreach ($all_late_records as $record) {
        $minutes_late = $record['minutes_late'];
        if ($minutes_late > 60) {
            $category = '1-Hour Late (0.5 day deduction)';
            $row_color = ' style="background-color: #fef3c7;"';
        } elseif ($minutes_late > 15) {
            $category = 'Regular Late (counts toward 3-day rule)';
            $row_color = ' style="background-color: #e0f2fe;"';
        } else {
            $category = 'Grace Period (≤15 min - no penalty)';
            $row_color = ' style="background-color: #f0f9ff;"';
        }
        
        echo "<tr{$row_color}>";
        echo "<td>{$record['attendance_date']}</td>";
        echo "<td>{$record['punch_time']}</td>";
        echo "<td>{$minutes_late} minutes</td>";
        echo "<td>{$category}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";

// Calculate deduction
$one_hour_late_days = count($one_hour_late_records);
$deduction_days = $one_hour_late_days * 0.5;
$daily_salary = $working_days > 0 ? ($current_salary / $working_days) : 0;
$deduction_amount = $deduction_days * $daily_salary;

echo "<h3>Deduction Calculation</h3>";
echo "<p><strong>Occurrences:</strong> {$one_hour_late_days}</p>";
echo "<p><strong>Deduction Days:</strong> {$one_hour_late_days} × 0.5 = {$deduction_days} days</p>";
echo "<p><strong>Daily Salary:</strong> ₹" . number_format($daily_salary, 2) . "</p>";
echo "<p><strong>Total Deduction Amount:</strong> {$deduction_days} × ₹" . number_format($daily_salary, 2) . " = ₹" . number_format($deduction_amount, 2) . "</p>";

echo "<hr>";
echo "<p><a href='salary_analytics_dashboard.php?filter_month={$filter_month}'>← Back to Dashboard</a></p>";
?>