<?php
// Test script to verify the overtime calculation logic
function testOvertimeCalculation() {
    // Test cases: [shift_end_time, punch_out_time, expected_result]
    $testCases = [
        ['17:30:00', '19:15:00', '1.8'],  // 1 hour 45 minutes = 1.8 hours
        ['18:00:00', '19:30:00', '1.5'],  // 1 hour 30 minutes = 1.5 hours
        ['18:00:00', '20:00:00', '2.0'],  // 2 hours = 2.0 hours
        ['18:00:00', '17:00:00', '23.0'], // Next day (worked 23 hours)
        ['08:00:00', '09:30:00', '1.5'],  // 1 hour 30 minutes = 1.5 hours
        ['08:00:00', '08:30:00', '0.5'],  // 30 minutes = 0.5 hours
    ];
    
    echo "Testing overtime calculation logic:\n";
    echo "====================================\n";
    
    foreach ($testCases as $index => $case) {
        [$shiftEnd, $punchOut, $expected] = $case;
        $result = calculateOvertimeHours($shiftEnd, $punchOut);
        $status = ($result === $expected) ? "PASS" : "FAIL";
        
        echo "Test " . ($index + 1) . ": $status\n";
        echo "  Shift end: $shiftEnd, Punch out: $punchOut\n";
        echo "  Expected: $expected, Got: $result\n\n";
    }
}

function calculateOvertimeHours($shiftEndTime, $punchOutTime) {
    if (!$shiftEndTime || !$punchOutTime) {
        return '0.0';
    }
    
    // Convert times to seconds
    $shiftEndSeconds = timeToSeconds($shiftEndTime);
    $punchOutSeconds = timeToSeconds($punchOutTime);
    
    // Calculate overtime in seconds
    $overtimeSeconds = 0;
    
    if ($punchOutSeconds > $shiftEndSeconds) {
        // Same day punch out
        $overtimeSeconds = $punchOutSeconds - $shiftEndSeconds;
    } else if ($punchOutSeconds < $shiftEndSeconds) {
        // Next day punch out (worked past midnight)
        $overtimeSeconds = (24 * 3600 - $shiftEndSeconds) + $punchOutSeconds;
    }
    
    // Convert seconds to decimal hours with 1 decimal place
    $overtimeHours = $overtimeSeconds / 3600;
    return number_format($overtimeHours, 1);
}

function timeToSeconds($time) {
    list($hours, $minutes, $seconds) = explode(':', $time);
    return ($hours * 3600) + ($minutes * 60) + $seconds;
}

// Run the test
testOvertimeCalculation();
?>