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
    die("Please provide user_id parameter. Example: ?user_id=1&filter_month=2024-08");
}

echo "<h2>Leave Deduction Debug Report</h2>";
echo "<p><strong>User ID:</strong> {$user_id}</p>";
echo "<p><strong>Filter Month:</strong> {$filter_month}</p>";
echo "<hr>";

// Get user basic info
$user_query = "SELECT id, username, base_salary FROM users WHERE id = ? AND status = 'active'";
$user_stmt = $pdo->prepare($user_query);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found or inactive.");
}

echo "<h3>User Information</h3>";
echo "<p><strong>Username:</strong> {$user['username']}</p>";
echo "<p><strong>Base Salary:</strong> ₹" . number_format($user['base_salary']) . "</p>";

// Get month details
$month_start = date('Y-m-01', strtotime($filter_month));
$month_end = date('Y-m-t', strtotime($filter_month));

echo "<p><strong>Month Range:</strong> {$month_start} to {$month_end}</p>";

// First, let's see all leave records for this user and month
echo "<h3>All Leave Records for This Month</h3>";
$all_leaves_query = "SELECT lr.*, lt.name as leave_type_name 
                    FROM leave_request lr
                    LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                    WHERE lr.user_id = ? 
                    AND lr.status = 'approved'
                    AND (
                        (lr.start_date BETWEEN ? AND ?) 
                        OR 
                        (lr.end_date BETWEEN ? AND ?)
                        OR 
                        (lr.start_date <= ? AND lr.end_date >= ?)
                    )
                    ORDER BY lr.start_date";
$all_leaves_stmt = $pdo->prepare($all_leaves_query);
$all_leaves_stmt->execute([$user_id, $month_start, $month_end, $month_start, $month_end, $month_start, $month_end]);
$all_leaves = $all_leaves_stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($all_leaves)) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Leave Type</th><th>Start Date</th><th>End Date</th><th>Duration Type</th><th>Duration</th><th>Days in Month</th></tr>";
    
    foreach ($all_leaves as $leave) {
        // Calculate days that fall within the selected month
        $leave_start = max($leave['start_date'], $month_start);
        $leave_end = min($leave['end_date'], $month_end);
        $days_in_month = (strtotime($leave_end) - strtotime($leave_start)) / (24 * 3600) + 1;
        
        if ($leave['duration_type'] === 'half_day') {
            $days_in_month = 0.5;
        }
        
        echo "<tr>";
        echo "<td>{$leave['leave_type_name']}</td>";
        echo "<td>{$leave['start_date']}</td>";
        echo "<td>{$leave['end_date']}</td>";
        echo "<td>{$leave['duration_type']}</td>";
        echo "<td>{$leave['duration']}</td>";
        echo "<td>{$days_in_month} days</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No approved leave records found for this month.</p>";
}

echo "<hr>";

// Now let's test the complex leave deduction query from the dashboard
echo "<h3>Dashboard Leave Deduction Calculation</h3>";

$leave_deduction_query = "SELECT 
                          SUM(CASE 
                              -- Casual Leave: Allow 2 per month, deduct excess
                              WHEN LOWER(lt.name) LIKE '%casual%' THEN 
                                  GREATEST(0, 
                                      CASE 
                                          WHEN lr.duration_type = 'half_day' THEN 0.5 
                                          WHEN lr.duration_type = 'full_day' THEN 
                                             LEAST(DATEDIFF(
                                                 LEAST(lr.end_date, ?), 
                                                 GREATEST(lr.start_date, ?)
                                             ) + 1, 
                                             DATEDIFF(?, ?) + 1)
                                          ELSE 1
                                      END - 2)
                              -- Half Day Leave: Always deduct
                              WHEN LOWER(lt.name) LIKE '%half day%' THEN 
                                  CASE 
                                      WHEN lr.duration_type = 'half_day' THEN 0.5 
                                      WHEN lr.duration_type = 'full_day' THEN 
                                         LEAST(DATEDIFF(
                                             LEAST(lr.end_date, ?), 
                                             GREATEST(lr.start_date, ?)
                                         ) + 1, 
                                         DATEDIFF(?, ?) + 1)
                                      ELSE 1
                                  END
                              -- Unpaid Leave/Holiday: Always deduct
                              WHEN LOWER(lt.name) LIKE '%unpaid%' THEN 
                                  CASE 
                                      WHEN lr.duration_type = 'half_day' THEN 0.5 
                                      WHEN lr.duration_type = 'full_day' THEN 
                                         LEAST(DATEDIFF(
                                             LEAST(lr.end_date, ?), 
                                             GREATEST(lr.start_date, ?)
                                         ) + 1, 
                                         DATEDIFF(?, ?) + 1)
                                      ELSE 1
                                  END
                              -- Short Leave: Allow 2 per month, deduct excess
                              WHEN LOWER(lt.name) LIKE '%short%' THEN 
                                  GREATEST(0, 
                                      CASE 
                                          WHEN lr.duration_type = 'half_day' THEN 0.5 
                                          WHEN lr.duration_type = 'full_day' THEN 
                                             LEAST(DATEDIFF(
                                                 LEAST(lr.end_date, ?), 
                                                 GREATEST(lr.start_date, ?)
                                             ) + 1, 
                                             DATEDIFF(?, ?) + 1)
                                          ELSE 1
                                      END - 2)
                              -- Compensate Leave: No deduction
                              WHEN LOWER(lt.name) LIKE '%compensate%' THEN 0
                              -- Other leaves: Deduct all
                              ELSE 
                                  CASE 
                                      WHEN lr.duration_type = 'half_day' THEN 0.5 
                                      WHEN lr.duration_type = 'full_day' THEN 
                                         LEAST(DATEDIFF(
                                             LEAST(lr.end_date, ?), 
                                             GREATEST(lr.start_date, ?)
                                         ) + 1, 
                                         DATEDIFF(?, ?) + 1)
                                      ELSE 1
                                  END
                          END) as leave_deduction_days,
                          GROUP_CONCAT(CONCAT(lt.name, ': ', 
                              CASE 
                                  WHEN lr.duration_type = 'half_day' THEN 0.5 
                                  WHEN lr.duration_type = 'full_day' THEN 
                                     LEAST(DATEDIFF(
                                         LEAST(lr.end_date, ?), 
                                         GREATEST(lr.start_date, ?)
                                     ) + 1, 
                                     DATEDIFF(?, ?) + 1)
                                  ELSE 1
                              END, ' days') SEPARATOR '; ') as breakdown
                         FROM leave_request lr
                         LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                         WHERE lr.user_id = ? 
                         AND lr.status = 'approved'
                         AND (
                             (lr.start_date BETWEEN ? AND ?) 
                             OR 
                             (lr.end_date BETWEEN ? AND ?)
                             OR 
                             (lr.start_date <= ? AND lr.end_date >= ?)
                         )";

$stmt = $pdo->prepare($leave_deduction_query);
$stmt->execute([
    $month_end, $month_start, $month_end, $month_start, // Casual leave calculation
    $month_end, $month_start, $month_end, $month_start, // Half day leave calculation
    $month_end, $month_start, $month_end, $month_start, // Unpaid leave calculation
    $month_end, $month_start, $month_end, $month_start, // Short leave calculation
    $month_end, $month_start, $month_end, $month_start, // Other leaves calculation
    $month_end, $month_start, $month_end, $month_start, // Breakdown calculation
    $user_id, 
    $month_start, $month_end, 
    $month_start, $month_end,
    $month_start, $month_end
]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<p><strong>Total Leave Deduction Days:</strong> " . ($result['leave_deduction_days'] ?? 0) . "</p>";
echo "<p><strong>Breakdown:</strong> " . ($result['breakdown'] ?? 'None') . "</p>";

echo "<h3>Expected Calculation for Bhuvnesh</h3>";
echo "<div style='background-color: #f5f5f5; padding: 15px; border-radius: 8px;'>";
echo "<p><strong>2 Compensate Leave:</strong> 0 days deduction (No deduction policy)</p>";
echo "<p><strong>2 Short Leave:</strong> 0 days deduction (Within 2-day allowance)</p>";
echo "<p><strong>1 Half Day Leave:</strong> 0.5 days deduction (Always deducted)</p>";
echo "<p><strong>Expected Total:</strong> 0.5 days</p>";
echo "<p><strong>Actual Total:</strong> " . ($result['leave_deduction_days'] ?? 0) . " days</p>";
echo "</div>";

echo "<hr>";
echo "<p><a href='salary_analytics_dashboard.php?filter_month={$filter_month}'>← Back to Dashboard</a></p>";
?>