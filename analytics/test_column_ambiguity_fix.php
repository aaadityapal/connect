<?php
// Test script to verify the ambiguous column error is fixed
session_start();

// Check if user is logged in and has HR role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    die('<div style="padding: 20px; color: #dc2626;">Access denied. HR role required.</div>');
}

echo "<h2>Column Ambiguity Fix Test</h2>";
echo "<hr>";

try {
    // Include database connection
    require_once '../config/db_connect.php';
    
    $current_month = date('Y-m');
    
    echo "<h3>Testing Fixed Query for Ambiguous Column Error</h3>";
    echo "<p>Testing month: <strong>$current_month</strong></p>";
    
    // Test the fixed attendance subquery that was causing the ambiguous column error
    $test_query = "SELECT 
        a.user_id,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
        COUNT(CASE WHEN a.status = 'present' AND TIME(a.punch_in) >= TIME(DATE_ADD(TIME(COALESCE(s.start_time, '09:00:00')), INTERVAL 15 MINUTE)) THEN 1 END) as late_days
        FROM attendance a
        LEFT JOIN users u_att ON a.user_id = u_att.id
        LEFT JOIN user_shifts us_att ON u_att.id = us_att.user_id AND 
            (us_att.effective_to IS NULL OR us_att.effective_to >= LAST_DAY(?))
        LEFT JOIN shifts s ON us_att.shift_id = s.id
        WHERE DATE_FORMAT(a.date, '%Y-%m') = ?
        GROUP BY a.user_id
        LIMIT 5";
    
    echo "<h4>Testing Attendance Subquery (Previously Causing Ambiguous Column Error):</h4>";
    
    $stmt = $pdo->prepare($test_query);
    $stmt->execute([$current_month, $current_month]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($results)) {
        echo "<div style='padding: 10px; background: #d1fae5; color: #065f46; border-radius: 8px; margin: 10px 0;'>";
        echo "✅ <strong>SUCCESS!</strong> The ambiguous column error has been fixed.";
        echo "</div>";
        
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>User ID</th><th>Present Days</th><th>Late Days</th></tr>";
        
        foreach ($results as $row) {
            echo "<tr>";
            echo "<td>{$row['user_id']}</td>";
            echo "<td>{$row['present_days']}</td>";
            echo "<td style='color: " . ($row['late_days'] > 0 ? 'red' : 'green') . ";'>{$row['late_days']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<div style='padding: 10px; background: #fef3c7; color: #92400e; border-radius: 8px; margin: 10px 0;'>";
        echo "⚠️ Query executed successfully but no data found for the current month.";
        echo "</div>";
    }
    
    // Test the full main query
    echo "<h4>Testing Full Main Query:</h4>";
    
    $main_query_test = "SELECT 
        u.id, u.username, 
        COALESCE(att.present_days, 0) as present_days,
        COALESCE(att.late_days, 0) as late_days
        FROM users u 
        LEFT JOIN (
            SELECT 
                a.user_id,
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN a.status = 'present' AND TIME(a.punch_in) >= TIME(DATE_ADD(TIME(COALESCE(s.start_time, '09:00:00')), INTERVAL 15 MINUTE)) THEN 1 END) as late_days
            FROM attendance a
            LEFT JOIN users u_att ON a.user_id = u_att.id
            LEFT JOIN user_shifts us_att ON u_att.id = us_att.user_id AND 
                (us_att.effective_to IS NULL OR us_att.effective_to >= LAST_DAY(?))
            LEFT JOIN shifts s ON us_att.shift_id = s.id
            WHERE DATE_FORMAT(a.date, '%Y-%m') = ?
            GROUP BY a.user_id
        ) att ON u.id = att.user_id
        WHERE u.status = 'active' AND u.deleted_at IS NULL 
        LIMIT 3";
    
    $main_stmt = $pdo->prepare($main_query_test);
    $main_stmt->execute([$current_month, $current_month]);
    $main_results = $main_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($main_results)) {
        echo "<div style='padding: 10px; background: #d1fae5; color: #065f46; border-radius: 8px; margin: 10px 0;'>";
        echo "✅ <strong>MAIN QUERY SUCCESS!</strong> The full query with attendance subquery is working correctly.";
        echo "</div>";
        
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Username</th><th>Present Days</th><th>Late Days</th></tr>";
        
        foreach ($main_results as $row) {
            echo "<tr>";
            echo "<td>{$row['username']}</td>";
            echo "<td>{$row['present_days']}</td>";
            echo "<td style='color: " . ($row['late_days'] > 0 ? 'red' : 'green') . ";'>{$row['late_days']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<div style='padding: 10px; background: #fef3c7; color: #92400e; border-radius: 8px; margin: 10px 0;'>";
        echo "⚠️ Main query executed successfully but no users found.";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<div style='padding: 15px; background: #fee2e2; color: #dc2626; border-radius: 8px; margin: 20px 0;'>";
    echo "<strong>❌ DATABASE ERROR:</strong> " . htmlspecialchars($e->getMessage());
    echo "<br><br><strong>Error Code:</strong> " . $e->getCode();
    echo "</div>";
} catch (Exception $e) {
    echo "<div style='padding: 15px; background: #fee2e2; color: #dc2626; border-radius: 8px; margin: 20px 0;'>";
    echo "<strong>❌ ERROR:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<hr>";
echo "<h4>Fix Summary:</h4>";
echo "<ul>";
echo "<li><strong>Problem:</strong> Multiple tables had 'user_id' columns causing ambiguity</li>";
echo "<li><strong>Solution:</strong> Added proper table aliases (a.user_id, u_att.id, us_att.user_id)</li>";
echo "<li><strong>Result:</strong> Query now explicitly specifies which table's user_id to use</li>";
echo "</ul>";

echo "<p><a href='salary_analytics_dashboard.php'>← Back to Dashboard</a> | ";
echo "<a href='debug_production_issues.php'>Run Diagnostics</a></p>";
?>