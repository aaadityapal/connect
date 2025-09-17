<?php
// Test script to verify late punch-in calculation is working correctly
session_start();

// Check if user is logged in and has HR role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    die('<div style="padding: 20px; color: #dc2626;">Access denied. HR role required.</div>');
}

echo "<h2>Late Punch-In Calculation Test</h2>";
echo "<hr>";

try {
    // Include database connection
    require_once '../config/db_connect.php';
    
    $current_month = date('Y-m');
    
    echo "<h3>Testing Late Punch-In Calculation for Current Month: $current_month</h3>";
    
    // Get a sample user with shift information
    $sample_query = "SELECT 
        u.id, u.username, 
        s.shift_name, s.start_time as shift_start_time,
        us.weekly_offs
        FROM users u 
        LEFT JOIN user_shifts us ON u.id = us.user_id AND 
            (us.effective_to IS NULL OR us.effective_to >= LAST_DAY(?))
        LEFT JOIN shifts s ON us.shift_id = s.id
        WHERE u.status = 'active' AND u.deleted_at IS NULL 
        LIMIT 5";
    
    $stmt = $pdo->prepare($sample_query);
    $stmt->execute([$current_month]);
    $sample_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Sample Users and Their Shift Times:</h4>";
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
    echo "<tr><th>User</th><th>Shift Name</th><th>Start Time</th><th>Late Threshold</th><th>Present Days</th><th>Late Days (15+ min)</th><th>Very Late Days (1+ hour)</th></tr>";
    
    foreach ($sample_users as $user) {
        $shift_start = $user['shift_start_time'] ?? '09:00:00';
        $late_threshold = date('H:i:s', strtotime($shift_start . ' +15 minutes'));
        $very_late_threshold = date('H:i:s', strtotime($shift_start . ' +1 hour'));
        
        // Get attendance data for this user
        $att_query = "SELECT 
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
            COUNT(CASE WHEN status = 'present' AND TIME(punch_in) >= TIME(?) THEN 1 END) as late_days_15min,
            COUNT(CASE WHEN status = 'present' AND TIME(punch_in) > TIME(?) THEN 1 END) as late_days_1hour,
            GROUP_CONCAT(CASE WHEN status = 'present' AND TIME(punch_in) >= TIME(?) THEN CONCAT(DATE(date), ' - ', TIME(punch_in)) END SEPARATOR ', ') as late_dates
            FROM attendance 
            WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?";
        
        $att_stmt = $pdo->prepare($att_query);
        $att_stmt->execute([$late_threshold, $very_late_threshold, $late_threshold, $user['id'], $current_month]);
        $att_result = $att_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<tr>";
        echo "<td>{$user['username']}</td>";
        echo "<td>" . ($user['shift_name'] ?? 'Default') . "</td>";
        echo "<td>$shift_start</td>";
        echo "<td>$late_threshold</td>";
        echo "<td>{$att_result['present_days']}</td>";
        echo "<td style='color: " . ($att_result['late_days_15min'] > 0 ? 'red' : 'green') . ";'>{$att_result['late_days_15min']}</td>";
        echo "<td style='color: " . ($att_result['late_days_1hour'] > 0 ? 'red' : 'green') . ";'>{$att_result['late_days_1hour']}</td>";
        echo "</tr>";
        
        if (!empty($att_result['late_dates'])) {
            echo "<tr><td colspan='7' style='font-size: 12px; color: #666;'>Late dates: {$att_result['late_dates']}</td></tr>";
        }
    }
    
    echo "</table>";
    
    echo "<hr>";
    echo "<h4>Explanation:</h4>";
    echo "<ul>";
    echo "<li><strong>Present Days:</strong> Total days marked as 'present'</li>";
    echo "<li><strong>Late Days (15+ min):</strong> Days where punch-in was 15+ minutes after shift start time</li>";
    echo "<li><strong>Very Late Days (1+ hour):</strong> Days where punch-in was 1+ hours after shift start time</li>";
    echo "</ul>";
    
    echo "<p><strong>Note:</strong> The calculation now correctly uses each user's individual shift start time instead of a hardcoded 09:00:00.</p>";
    
} catch (Exception $e) {
    echo "<div style='padding: 15px; background: #fee2e2; color: #dc2626; border-radius: 8px; margin: 20px 0;'>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='salary_analytics_dashboard.php'>← Back to Dashboard</a> | ";
echo "<a href='debug_production_issues.php'>Run Diagnostics</a></p>";
?>