<?php
// Start session to access session variables
session_start();

// Include database configuration
require_once 'config.php';

// Simulate user ID 21 for debugging
$user_id = 21;
$username = "Aditya Kumar Pal";

echo "<h2>Debugging Greetings Section for User ID: $user_id</h2>\n";
echo "<p>Username: $username</p>\n";

// Initialize shift information
$shift_info = null;
$remaining_time = null;
$is_weekly_off = false;

// Fetch user shift information
if ($user_id) {
    try {
        $currentDate = date('Y-m-d');
        $currentDay = date('l');
        
        echo "<h3>Current Date Information:</h3>\n";
        echo "<p>Date: $currentDate</p>\n";
        echo "<p>Day: $currentDay</p>\n";
        
        echo "<h3>Database Query:</h3>\n";
        $query = "SELECT s.shift_name, s.start_time, s.end_time, us.weekly_offs
                  FROM user_shifts us 
                  JOIN shifts s ON us.shift_id = s.id 
                  WHERE us.user_id = ?
                  AND us.effective_from <= ?
                  AND (us.effective_to IS NULL OR us.effective_to >= ?)";
        
        echo "<p>Query: " . htmlspecialchars($query) . "</p>\n";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id, $currentDate, $currentDate]);
        
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>Query Result:</h3>\n";
        if ($shift) {
            echo "<pre>" . print_r($shift, true) . "</pre>\n";
            
            $shift_info = $shift;
            
            // Check if today is a weekly off
            $weekly_offs = $shift['weekly_offs'];
            echo "<p>Weekly Offs Value: '" . htmlspecialchars($weekly_offs) . "'</p>\n";
            
            // Check if today is a weekly off
            if (!empty($weekly_offs)) {
                if (strpos($weekly_offs, $currentDay) !== false) {
                    $is_weekly_off = true;
                    echo "<p>Today IS a weekly off (found '$currentDay' in weekly_offs)</p>\n";
                } else {
                    echo "<p>Today is NOT a weekly off ('$currentDay' not found in weekly_offs)</p>\n";
                }
            } else {
                echo "<p>No weekly offs specified</p>\n";
            }
            
            // If not a weekly off, calculate remaining time
            if (!$is_weekly_off) {
                echo "<h3>Calculating Remaining Time:</h3>\n";
                // Calculate remaining time
                $endTime = strtotime($currentDate . ' ' . $shift['end_time']);
                $currentTimestamp = strtotime('now');
                $remaining_time = $endTime - $currentTimestamp;
                
                echo "<p>End Time: " . $shift['end_time'] . " -> " . date('Y-m-d H:i:s', $endTime) . "</p>\n";
                echo "<p>Current Time: " . date('Y-m-d H:i:s', $currentTimestamp) . "</p>\n";
                echo "<p>Remaining Time: $remaining_time seconds (" . gmdate('H:i:s', max(0, $remaining_time)) . ")</p>\n";
            }
        } else {
            echo "<p><strong>No shift found for user!</strong></p>\n";
            
            // Let's check what's actually in the database
            echo "<h3>Detailed Database Check:</h3>\n";
            
            // Check all shifts for this user
            $allShiftsQuery = "SELECT us.*, s.shift_name, s.start_time, s.end_time 
                              FROM user_shifts us 
                              JOIN shifts s ON us.shift_id = s.id 
                              WHERE us.user_id = ?
                              ORDER BY us.effective_from DESC";
            $allStmt = $pdo->prepare($allShiftsQuery);
            $allStmt->execute([$user_id]);
            $allShifts = $allStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<p>All shifts for user:</p>\n";
            echo "<pre>" . print_r($allShifts, true) . "</pre>\n";
            
            // Check current date against each shift
            echo "<h3>Date Comparison:</h3>\n";
            foreach ($allShifts as $shiftRecord) {
                echo "<p>Shift ID {$shiftRecord['shift_id']} ({$shiftRecord['shift_name']}):</p>\n";
                echo "<ul>\n";
                echo "  <li>Effective From: {$shiftRecord['effective_from']}</li>\n";
                echo "  <li>Effective To: " . ($shiftRecord['effective_to'] ?: 'NULL') . "</li>\n";
                echo "  <li>Current Date ($currentDate) >= Effective From: " . (($currentDate >= $shiftRecord['effective_from']) ? 'YES' : 'NO') . "</li>\n";
                
                if ($shiftRecord['effective_to']) {
                    echo "  <li>Current Date ($currentDate) <= Effective To: " . (($currentDate <= $shiftRecord['effective_to']) ? 'YES' : 'NO') . "</li>\n";
                } else {
                    echo "  <li>Effective To is NULL: YES</li>\n";
                }
                
                $isActive = ($currentDate >= $shiftRecord['effective_from']) && 
                           ($shiftRecord['effective_to'] === null || $currentDate <= $shiftRecord['effective_to']);
                echo "  <li><strong>Active: " . ($isActive ? 'YES' : 'NO') . "</strong></li>\n";
                echo "</ul>\n";
            }
        }
        
        echo "<h3>Final Display Logic:</h3>\n";
        if ($shift_info) {
            echo "<p>Shift Info: " . htmlspecialchars($shift_info['shift_name']) . "</p>\n";
            if ($is_weekly_off) {
                echo "<p>Display: [Shift Name] shift (Weekly Off Today)</p>\n";
            } elseif ($remaining_time !== null) {
                echo "<p>Display: [Shift Name] shift ends in: " . gmdate('H:i:s', max(0, $remaining_time)) . "</p>\n";
            } else {
                echo "<p>Display: [Shift Name] shift (Active)</p>\n";
            }
        } else {
            echo "<p>Display: No shift assigned</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
        echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
    }
} else {
    echo "<p>No user ID provided</p>\n";
}
?>