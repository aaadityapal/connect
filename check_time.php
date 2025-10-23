<?php
echo "Current day (N): " . date('N') . "\n";
echo "Current hour (H): " . date('H') . "\n";
echo "Current minute (i): " . date('i') . "\n";
echo "Current day name: " . date('l') . "\n";
echo "Current time: " . date('H:i') . "\n";
echo "Full timestamp: " . date('Y-m-d H:i:s') . "\n";

// Test the logic
$currentDay = date('N');
$currentHour = date('H');
$currentMinute = date('i');

echo "\nTesting actual logic:\n";

// Check if current time is within approval window
$isApprovalTime = false;

// Sunday (day 7) - approval starts at 00:01
if ($currentDay == 7 && ($currentHour > 0 || ($currentHour == 0 && $currentMinute >= 1))) {
    $isApprovalTime = true;
    echo "Matched Sunday condition\n";
}
// Monday (day 1), Tuesday (day 2)
elseif ($currentDay == 1 || $currentDay == 2) {
    $isApprovalTime = true;
    echo "Matched Monday/Tuesday condition\n";
}
// Wednesday (day 3) - approval ends at 13:00
elseif ($currentDay == 3 && ($currentHour < 13 || ($currentHour == 13 && $currentMinute == 0))) {
    $isApprovalTime = true;
    echo "Matched Wednesday condition\n";
} else {
    echo "No condition matched - should be locked\n";
}

echo "Is approval time: " . ($isApprovalTime ? "Yes" : "No") . "\n";
$isLockedDueToWeekday = !$isApprovalTime;
echo "Is locked due to weekday/time: " . ($isLockedDueToWeekday ? "Yes" : "No") . "\n";

echo "\nTesting hypothetical time (15:16 on Wednesday):\n";
// Test with hypothetical time 15:16 on Wednesday
$testDay = 3; // Wednesday
$testHour = 15; // 3 PM
$testMinute = 16; // 16 minutes

$isApprovalTimeTest = false;

// Sunday (day 7) - approval starts at 00:01
if ($testDay == 7 && ($testHour > 0 || ($testHour == 0 && $testMinute >= 1))) {
    $isApprovalTimeTest = true;
    echo "Matched Sunday condition\n";
}
// Monday (day 1), Tuesday (day 2)
elseif ($testDay == 1 || $testDay == 2) {
    $isApprovalTimeTest = true;
    echo "Matched Monday/Tuesday condition\n";
}
// Wednesday (day 3) - approval ends at 13:00
elseif ($testDay == 3 && ($testHour < 13 || ($testHour == 13 && $testMinute == 0))) {
    $isApprovalTimeTest = true;
    echo "Matched Wednesday condition\n";
} else {
    echo "No condition matched - should be locked\n";
}

echo "At 15:16 on Wednesday, is approval time: " . ($isApprovalTimeTest ? "Yes" : "No") . "\n";
$isLockedDueToWeekdayTest = !$isApprovalTimeTest;
echo "At 15:16 on Wednesday, is locked due to weekday/time: " . ($isLockedDueToWeekdayTest ? "Yes" : "No") . "\n";

// Simulate the full locking logic
echo "\nSimulating full locking logic:\n";
echo "Assuming Accountant and HR have approved (both true):\n";
$accountantApproved = true;
$hrApproved = true;
$allRejected = false;

$isLocked = (!($accountantApproved && $hrApproved) && !$allRejected) || $isLockedDueToWeekday;
echo "Full lock status (current time): " . ($isLocked ? "Locked" : "Unlocked") . "\n";

$isLockedTest = (!($accountantApproved && $hrApproved) && !$allRejected) || $isLockedDueToWeekdayTest;
echo "Full lock status (15:16 on Wednesday): " . ($isLockedTest ? "Locked" : "Unlocked") . "\n";
?>