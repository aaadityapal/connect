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

// Get parameters - using Bhuvnesh's data from the image
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 1; // Bhuvnesh
$filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : '2024-08';

echo "<h2>Specific Leave Debug for Bhuvnesh</h2>";
echo "<p><strong>User ID:</strong> {$user_id}</p>";
echo "<p><strong>Filter Month:</strong> {$filter_month}</p>";
echo "<hr>";

$month_start = date('Y-m-01', strtotime($filter_month));
$month_end = date('Y-m-t', strtotime($filter_month));

// First, let's see ALL leave types in the database
echo "<h3>All Leave Types in Database</h3>";
$leave_types_query = "SELECT id, name FROM leave_types ORDER BY name";
$leave_types_stmt = $pdo->prepare($leave_types_query);
$leave_types_stmt->execute();
$leave_types = $leave_types_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>ID</th><th>Leave Type Name</th></tr>";
foreach ($leave_types as $type) {
    echo "<tr><td>{$type['id']}</td><td>{$type['name']}</td></tr>";
}
echo "</table>";

echo "<hr>";

// Now let's see this user's specific leave records
echo "<h3>User's Leave Records for {$filter_month}</h3>";
$user_leaves_query = "SELECT lr.*, lt.name as leave_type_name 
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
$user_leaves_stmt = $pdo->prepare($user_leaves_query);
$user_leaves_stmt->execute([$user_id, $month_start, $month_end, $month_start, $month_end, $month_start, $month_end]);
$user_leaves = $user_leaves_stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($user_leaves)) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Leave Type</th><th>Start Date</th><th>End Date</th><th>Duration Type</th><th>Duration</th></tr>";
    
    foreach ($user_leaves as $leave) {
        echo "<tr>";
        echo "<td>{$leave['leave_type_name']}</td>";
        echo "<td>{$leave['start_date']}</td>";
        echo "<td>{$leave['end_date']}</td>";
        echo "<td>{$leave['duration_type']}</td>";
        echo "<td>{$leave['duration']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No leave records found for this user and month.</p>";
}

echo "<hr>";

// Test each leave type pattern match
echo "<h3>Leave Type Pattern Matching Test</h3>";
foreach ($user_leaves as $leave) {
    $leave_name = strtolower($leave['leave_type_name']);
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 5px 0;'>";
    echo "<p><strong>Leave Type:</strong> {$leave['leave_type_name']}</p>";
    echo "<p><strong>Duration Type:</strong> {$leave['duration_type']}</p>";
    echo "<p><strong>Pattern Matches:</strong></p>";
    echo "<ul>";
    echo "<li>LIKE '%half day%': " . (strpos($leave_name, 'half day') !== false ? 'YES' : 'NO') . "</li>";
    echo "<li>LIKE '%short%': " . (strpos($leave_name, 'short') !== false ? 'YES' : 'NO') . "</li>";
    echo "<li>LIKE '%casual%': " . (strpos($leave_name, 'casual') !== false ? 'YES' : 'NO') . "</li>";
    echo "<li>LIKE '%compensate%': " . (strpos($leave_name, 'compensate') !== false ? 'YES' : 'NO') . "</li>";
    echo "</ul>";
    
    // Calculate what should be deducted for this leave
    $deduction = 0;
    if (strpos($leave_name, 'half day') !== false) {
        $deduction = ($leave['duration_type'] === 'half_day') ? 0.5 : 1.0;
        echo "<p><strong>Should Deduct:</strong> {$deduction} days (Half Day Leave - Always deduct)</p>";
    } elseif (strpos($leave_name, 'compensate') !== false) {
        echo "<p><strong>Should Deduct:</strong> 0 days (Compensate Leave - Never deduct)</p>";
    } elseif (strpos($leave_name, 'short') !== false) {
        echo "<p><strong>Should Deduct:</strong> 0 days if ≤2 total short leaves this month (Short Leave allowance)</p>";
    } else {
        echo "<p><strong>Should Deduct:</strong> Need to check specific leave type rules</p>";
    }
    echo "</div>";
}

echo "<hr>";

// Test the exact SQL query from the dashboard
echo "<h3>Dashboard SQL Query Test</h3>";
$leave_deduction_query = "SELECT 
                          -- Half Day Leave: Always deduct (0.5 days each)
                          COALESCE(SUM(CASE 
                              WHEN LOWER(lt.name) LIKE '%half day%' AND lr.duration_type = 'half_day' THEN 0.5
                              WHEN LOWER(lt.name) LIKE '%half day%' AND lr.duration_type = 'full_day' THEN 
                                 LEAST(DATEDIFF(
                                     LEAST(lr.end_date, ?), 
                                     GREATEST(lr.start_date, ?)
                                 ) + 1, 
                                 DATEDIFF(?, ?) + 1)
                              ELSE 0
                          END), 0) as half_day_deduction,
                          -- Short Leave: Count total
                          COALESCE(SUM(CASE 
                              WHEN LOWER(lt.name) LIKE '%short%' AND lr.duration_type = 'half_day' THEN 0.5
                              WHEN LOWER(lt.name) LIKE '%short%' AND lr.duration_type = 'full_day' THEN 
                                 LEAST(DATEDIFF(
                                     LEAST(lr.end_date, ?), 
                                     GREATEST(lr.start_date, ?)
                                 ) + 1, 
                                 DATEDIFF(?, ?) + 1)
                              ELSE 0
                          END), 0) as short_leave_total,
                          -- Compensate Leave: Count total (should be 0 deduction)
                          COALESCE(SUM(CASE 
                              WHEN LOWER(lt.name) LIKE '%compensate%' THEN 1
                              ELSE 0
                          END), 0) as compensate_leave_count,
                          -- Total calculation
                          COALESCE(SUM(CASE 
                              WHEN LOWER(lt.name) LIKE '%half day%' AND lr.duration_type = 'half_day' THEN 0.5
                              WHEN LOWER(lt.name) LIKE '%half day%' AND lr.duration_type = 'full_day' THEN 
                                 LEAST(DATEDIFF(
                                     LEAST(lr.end_date, ?), 
                                     GREATEST(lr.start_date, ?)
                                 ) + 1, 
                                 DATEDIFF(?, ?) + 1)
                              ELSE 0
                          END), 0) +
                          GREATEST(0, COALESCE(SUM(CASE 
                              WHEN LOWER(lt.name) LIKE '%short%' AND lr.duration_type = 'half_day' THEN 0.5
                              WHEN LOWER(lt.name) LIKE '%short%' AND lr.duration_type = 'full_day' THEN 
                                 LEAST(DATEDIFF(
                                     LEAST(lr.end_date, ?), 
                                     GREATEST(lr.start_date, ?)
                                 ) + 1, 
                                 DATEDIFF(?, ?) + 1)
                              ELSE 0
                          END), 0) - 2) as total_deduction
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
    // Half Day calculation
    $month_end, $month_start, $month_end, $month_start,
    // Short Leave calculation for counting
    $month_end, $month_start, $month_end, $month_start,
    // Half Day calculation for total
    $month_end, $month_start, $month_end, $month_start,
    // Short Leave calculation for total
    $month_end, $month_start, $month_end, $month_start,
    // WHERE clause
    $user_id, 
    $month_start, $month_end,
    $month_start, $month_end,
    $month_start, $month_end
]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<p><strong>Half Day Deduction:</strong> " . ($result['half_day_deduction'] ?? 0) . " days</p>";
echo "<p><strong>Short Leave Total:</strong> " . ($result['short_leave_total'] ?? 0) . " days</p>";
echo "<p><strong>Short Leave Deduction:</strong> " . max(0, ($result['short_leave_total'] ?? 0) - 2) . " days (Total - 2 allowance)</p>";
echo "<p><strong>Compensate Leave Count:</strong> " . ($result['compensate_leave_count'] ?? 0) . " leaves</p>";
echo "<p><strong>Total Calculated Deduction:</strong> " . ($result['total_deduction'] ?? 0) . " days</p>";

echo "<hr>";
echo "<p><a href='salary_analytics_dashboard.php?filter_month={$filter_month}'>← Back to Dashboard</a></p>";
?>