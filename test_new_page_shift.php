<?php
// Test what shift end time new_page.php will display
require_once 'config/db_connect.php';
require_once 'includes/functions/shift_functions.php';

// Simulate the logic in new_page.php
$user_id = 21; // This is what we changed the default to

// Fetch user's shift end time
$user_shift = getUserShiftEndTime($pdo, $user_id);
$shift_end_time = $user_shift['end_time'];
$shift_name = $user_shift['shift_name'];

echo "<h1>Test New Page Shift Display</h1>\n";
echo "<p>User ID: $user_id</p>\n";
echo "<p>Shift Name: " . htmlspecialchars($shift_name) . "</p>\n";
echo "<p>Shift End Time: " . htmlspecialchars($shift_end_time) . "</p>\n";

// Convert to 12-hour format for display
if (!empty($shift_end_time)) {
    $time_parts = explode(':', $shift_end_time);
    if (count($time_parts) >= 2) {
        $hour = (int)$time_parts[0];
        $minute = $time_parts[1];
        
        if ($hour == 0) {
            $display_time = "12:$minute AM";
        } else if ($hour < 12) {
            $display_time = "$hour:$minute AM";
        } else if ($hour == 12) {
            $display_time = "12:$minute PM";
        } else {
            $display_time = ($hour - 12) . ":$minute PM";
        }
        
        echo "<p>Display Time: $display_time</p>\n";
    }
}
?>