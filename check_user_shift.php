<?php
require_once 'config.php';

$user_id = 21;
$currentDate = date('Y-m-d');

echo "Checking shift data for user ID: $user_id\n";
echo "Current date: $currentDate\n\n";

try {
    // Check user_shifts table
    $query = "SELECT us.*, s.shift_name, s.start_time, s.end_time 
              FROM user_shifts us 
              JOIN shifts s ON us.shift_id = s.id 
              WHERE us.user_id = :user_id 
              ORDER BY us.effective_from DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($shifts)) {
        echo "No shifts found for user ID: $user_id\n";
    } else {
        echo "Found " . count($shifts) . " shift record(s):\n";
        foreach ($shifts as $shift) {
            echo "Shift ID: " . $shift['shift_id'] . "\n";
            echo "Shift Name: " . $shift['shift_name'] . "\n";
            echo "Start Time: " . $shift['start_time'] . "\n";
            echo "End Time: " . $shift['end_time'] . "\n";
            echo "Effective From: " . $shift['effective_from'] . "\n";
            echo "Effective To: " . ($shift['effective_to'] ?: 'NULL') . "\n";
            echo "Weekly Offs: " . $shift['weekly_offs'] . "\n";
            echo "------------------------\n";
        }
    }
    
    // Check if current date falls within any effective period
    $query = "SELECT us.*, s.shift_name, s.start_time, s.end_time 
              FROM user_shifts us 
              JOIN shifts s ON us.shift_id = s.id 
              WHERE us.user_id = :user_id 
              AND us.effective_from <= :current_date
              AND (us.effective_to IS NULL OR us.effective_to >= :current_date)";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':user_id' => $user_id,
        ':current_date' => $currentDate
    ]);
    
    $currentShift = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($currentShift) {
        echo "\nCurrent active shift:\n";
        echo "Shift ID: " . $currentShift['shift_id'] . "\n";
        echo "Shift Name: " . $currentShift['shift_name'] . "\n";
        echo "Start Time: " . $currentShift['start_time'] . "\n";
        echo "End Time: " . $currentShift['end_time'] . "\n";
        echo "Effective From: " . $currentShift['effective_from'] . "\n";
        echo "Effective To: " . ($currentShift['effective_to'] ?: 'NULL') . "\n";
        echo "Weekly Offs: " . $currentShift['weekly_offs'] . "\n";
    } else {
        echo "\nNo active shift found for current date\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>