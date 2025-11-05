<?php
// Test script to verify the overtime filtering logic
function testOvertimeFilter() {
    // Test cases: [shift_end_time, punch_out_time, expected_result]
    $testCases = [
        ['18:00:00', '19:30:00', true],   // Exactly 1.5 hours after shift end - should show
        ['18:00:00', '19:00:00', false],  // Less than 1.5 hours - should not show
        ['18:00:00', '20:00:00', true],   // More than 1.5 hours - should show
        ['18:00:00', '17:00:00', true],   // Next day punch out - should show
        ['08:00:00', '09:30:00', true],   // Exactly 1.5 hours after shift end - should show
        ['08:00:00', '08:30:00', false],  // Less than 1.5 hours - should not show
    ];
    
    echo "Testing overtime filter logic:\n";
    echo "================================\n";
    
    foreach ($testCases as $index => $case) {
        [$shiftEnd, $punchOut, $expected] = $case;
        $result = shouldShowAttendance($shiftEnd, $punchOut);
        $status = ($result === $expected) ? "PASS" : "FAIL";
        
        echo "Test " . ($index + 1) . ": $status\n";
        echo "  Shift end: $shiftEnd, Punch out: $punchOut\n";
        echo "  Expected: " . ($expected ? "SHOW" : "HIDE") . ", Got: " . ($result ? "SHOW" : "HIDE") . "\n\n";
    }
}

function shouldShowAttendance($shiftEndTime, $punchOutTime) {
    // Convert times to seconds
    $shiftEndSeconds = timeToSeconds($shiftEndTime);
    $punchOutSeconds = timeToSeconds($punchOutTime);
    
    // Check the two conditions:
    // 1. Same day punch out and at least 1.5 hours after shift end
    // 2. Next day punch out (before shift end time)
    $minOvertimeSeconds = 5400; // 1.5 hours in seconds
    
    return ($punchOutSeconds >= $shiftEndSeconds + $minOvertimeSeconds) || 
           ($punchOutSeconds < $shiftEndSeconds);
}

function timeToSeconds($time) {
    list($hours, $minutes, $seconds) = explode(':', $time);
    return ($hours * 3600) + ($minutes * 60) + $seconds;
}

// Run the test
testOvertimeFilter();
?>