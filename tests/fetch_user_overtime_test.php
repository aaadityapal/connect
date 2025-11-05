<?php
// Test file to fetch overtime for a specific user on a specific date
require_once __DIR__ . '/../config/db_connect.php';

try {
    // User ID and date to test
    $user_id = 21;
    $test_date = '2025-08-06';
    
    echo "<h2>Overtime Test for User ID: $user_id on Date: $test_date</h2>\n";
    
    // Query to fetch attendance data for the specific user and date
    $query = "SELECT 
                a.id as attendance_id,
                u.username,
                u.role,
                a.date,
                a.punch_in,
                a.punch_out,
                a.working_hours,
                a.overtime_hours,
                a.work_report,
                a.overtime_status,
                s.start_time as shift_start_time,
                s.end_time as shift_end_time
              FROM attendance a
              JOIN users u ON a.user_id = u.id
              LEFT JOIN user_shifts us ON u.id = us.user_id AND a.date BETWEEN us.effective_from AND COALESCE(us.effective_to, '9999-12-31')
              LEFT JOIN shifts s ON us.shift_id = s.id
              WHERE a.user_id = ? AND a.date = ?
              LIMIT 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $test_date]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "<h3>Attendance Record Found:</h3>\n";
        echo "<pre>" . print_r($result, true) . "</pre>\n";
        
        // Calculate overtime manually to verify
        if ($result['punch_in'] && $result['punch_out'] && $result['shift_end_time']) {
            echo "<h3>Manual Overtime Calculation:</h3>\n";
            
            // Convert times to timestamps for calculation
            $punch_in_time = strtotime($result['date'] . ' ' . $result['punch_in']);
            $punch_out_time = strtotime($result['date'] . ' ' . $result['punch_out']);
            $shift_end_time = strtotime($result['date'] . ' ' . $result['shift_end_time']);
            
            echo "Punch In Time: " . date('Y-m-d H:i:s', $punch_in_time) . " (" . $result['punch_in'] . ")\n";
            echo "Punch Out Time: " . date('Y-m-d H:i:s', $punch_out_time) . " (" . $result['punch_out'] . ")\n";
            echo "Shift End Time: " . date('Y-m-d H:i:s', $shift_end_time) . " (" . $result['shift_end_time'] . ")\n";
            
            // Calculate working duration in seconds
            $working_seconds = $punch_out_time - $punch_in_time;
            echo "Total Working Seconds: $working_seconds (" . gmdate('H:i:s', $working_seconds) . ")\n";
            
            // Calculate overtime seconds
            $overtime_seconds = 0;
            if ($punch_out_time > $shift_end_time) {
                $overtime_seconds = $punch_out_time - $shift_end_time;
                echo "Overtime Seconds (after shift end): $overtime_seconds (" . gmdate('H:i:s', $overtime_seconds) . ")\n";
            } else {
                echo "No overtime: Punch out time is before or at shift end time\n";
            }
            
            // Apply the 1.5 hour minimum threshold (5400 seconds)
            if ($overtime_seconds >= 5400) {
                echo "Overtime meets minimum threshold of 1.5 hours (5400 seconds)\n";
                
                // Convert to minutes for rounding calculation
                $overtime_minutes = $overtime_seconds / 60;
                
                // Apply rounding rules:
                // - If less than 1.5 hours (90 minutes), return 1.5 (minimum threshold)
                // - For 1.5 hours and above: round down to nearest 30-minute increment
                if ($overtime_minutes < 90) {
                    $final_overtime_hours = 1.5;
                } else {
                    // For 1.5 hours and above:
                    // Round down to the nearest 30-minute increment
                    // First, subtract 1.5 hours (90 minutes) from the total
                    $adjusted_minutes = $overtime_minutes - 90;
                    
                    // Then round down to nearest 30-minute increment
                    $rounded_adjusted = floor($adjusted_minutes / 30) * 30;
                    
                    // Add back the 1.5 hours base
                    $final_minutes = 90 + $rounded_adjusted;
                    
                    // Convert back to hours
                    $final_overtime_hours = $final_minutes / 60;
                }
                
                echo "Final Calculated Overtime Hours: " . number_format($final_overtime_hours, 2) . " hours\n";
            } else {
                echo "Overtime does not meet minimum threshold of 1.5 hours (5400 seconds)\n";
                echo "Final Calculated Overtime Hours: 0.00 hours\n";
            }
        }
    } else {
        echo "<p>No attendance record found for User ID: $user_id on Date: $test_date</p>\n";
        
        // Check if the user exists
        $user_query = "SELECT id, username, role FROM users WHERE id = ?";
        $user_stmt = $pdo->prepare($user_query);
        $user_stmt->execute([$user_id]);
        $user_result = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_result) {
            echo "<p>User found: " . $user_result['username'] . " (" . $user_result['role'] . ")</p>\n";
        } else {
            echo "<p>User ID: $user_id does not exist in the database</p>\n";
        }
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>\n";
}
?>