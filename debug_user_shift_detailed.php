<?php
// Detailed debug script to check user ID 21's shift information
require_once 'config/db_connect.php';
require_once 'includes/functions/shift_functions.php';

$user_id = 21;
$current_date = date('Y-m-d');
$today = new DateTime();

echo "<h1>Detailed Debug User Shift Information</h1>\n";
echo "<p>Checking shift information for user ID: $user_id</p>\n";
echo "<p>Current date: $current_date</p>\n";

// Let's check all shifts for this user with detailed date information
echo "<h2>All Shift Assignments for User (Detailed)</h2>\n";
try {
    $query = "SELECT us.*, s.shift_name, s.start_time, s.end_time 
              FROM user_shifts us
              JOIN shifts s ON us.shift_id = s.id
              WHERE us.user_id = ?
              ORDER BY us.effective_from DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    
    $all_shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_shifts as $shift) {
        $effective_from = new DateTime($shift['effective_from']);
        $effective_to = !empty($shift['effective_to']) ? new DateTime($shift['effective_to']) : null;
        
        $is_active = false;
        if ($effective_from <= $today) {
            if ($effective_to === null || $effective_to >= $today) {
                $is_active = true;
            }
        }
        
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
        echo "<h3>Shift: " . htmlspecialchars($shift['shift_name']) . "</h3>";
        echo "<p>Shift ID: " . $shift['shift_id'] . "</p>";
        echo "<p>End Time: " . $shift['end_time'] . "</p>";
        echo "<p>Effective From: " . $shift['effective_from'] . " (" . $effective_from->format('Y-m-d') . ")</p>";
        echo "<p>Effective To: " . ($shift['effective_to'] ?: 'NULL') . " (" . ($effective_to ? $effective_to->format('Y-m-d') : 'NULL') . ")</p>";
        echo "<p>Is Active Today: " . ($is_active ? 'YES' : 'NO') . "</p>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<p>Error fetching all shifts: " . $e->getMessage() . "</p>\n";
}

// Let's test the actual function with user 21
echo "<h2>Testing getUserShiftEndTime with User ID 21</h2>\n";
$user_shift = getUserShiftEndTime($pdo, $user_id);
echo "<pre>" . print_r($user_shift, true) . "</pre>\n";
?>