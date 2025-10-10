<?php
require_once 'config.php';

echo "Checking shift data for user ID 21\n";
echo "=====================================\n\n";

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([21]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "User with ID 21 does not exist!\n";
        exit;
    }
    
    echo "User found: " . $user['username'] . " (ID: " . $user['id'] . ")\n\n";
    
    // Check shifts table
    echo "Checking shifts table:\n";
    $stmt = $pdo->query("SELECT * FROM shifts");
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($shifts)) {
        echo "No shifts found in shifts table\n";
    } else {
        echo "Found " . count($shifts) . " shifts:\n";
        foreach ($shifts as $shift) {
            echo "  ID: " . $shift['id'] . " - " . $shift['shift_name'] . 
                 " (" . $shift['start_time'] . " to " . $shift['end_time'] . ")\n";
        }
    }
    
    echo "\n";
    
    // Check user_shifts table for user 21
    echo "Checking user_shifts table for user ID 21:\n";
    $stmt = $pdo->prepare("SELECT * FROM user_shifts WHERE user_id = ?");
    $stmt->execute([21]);
    $userShifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($userShifts)) {
        echo "No shift assignments found for user ID 21\n";
    } else {
        echo "Found " . count($userShifts) . " shift assignment(s):\n";
        foreach ($userShifts as $shift) {
            echo "  Assignment ID: " . $shift['id'] . "\n";
            echo "  Shift ID: " . $shift['shift_id'] . "\n";
            echo "  Weekly Offs: " . $shift['weekly_offs'] . "\n";
            echo "  Effective From: " . $shift['effective_from'] . "\n";
            echo "  Effective To: " . ($shift['effective_to'] ?: 'NULL') . "\n";
            echo "  ------------------------\n";
        }
    }
    
    echo "\n";
    
    // Check current active shift for user 21
    $currentDate = date('Y-m-d');
    echo "Checking for active shift on $currentDate:\n";
    
    $stmt = $pdo->prepare("SELECT us.*, s.shift_name, s.start_time, s.end_time 
                          FROM user_shifts us 
                          JOIN shifts s ON us.shift_id = s.id 
                          WHERE us.user_id = ? 
                          AND us.effective_from <= ?
                          AND (us.effective_to IS NULL OR us.effective_to >= ?)");
    $stmt->execute([21, $currentDate, $currentDate]);
    $currentShift = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($currentShift) {
        echo "Active shift found:\n";
        echo "  Shift Name: " . $currentShift['shift_name'] . "\n";
        echo "  Start Time: " . $currentShift['start_time'] . "\n";
        echo "  End Time: " . $currentShift['end_time'] . "\n";
        echo "  Weekly Offs: " . $currentShift['weekly_offs'] . "\n";
    } else {
        echo "No active shift found for user ID 21 on $currentDate\n";
    }
    
    echo "\n";
    
    // Check if today is a weekly off
    $currentDay = date('l');
    echo "Today is: $currentDay\n";
    
    if ($currentShift && strpos($currentShift['weekly_offs'], $currentDay) !== false) {
        echo "Today is a weekly off for this shift!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
