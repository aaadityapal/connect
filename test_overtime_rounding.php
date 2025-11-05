<?php
// Test script to verify the overtime rounding logic
function testOvertimeRounding() {
    // Test cases: [actual_minutes, expected_display]
    $testCases = [
        [90, '1.5'],   // 1h30m -> 1.5h (minimum threshold)
        [107, '1.5'],  // 1h47m -> 1.5h
        [119, '1.5'],  // 1h59m -> 1.5h
        [120, '2.0'],  // 2h00m -> 2.0h
        [137, '2.0'],  // 2h17m -> 2.0h
        [149, '2.0'],  // 2h29m -> 2.0h
        [150, '2.5'],  // 2h30m -> 2.5h
        [179, '2.5'],  // 2h59m -> 2.5h
        [180, '3.0'],  // 3h00m -> 3.0h
        [209, '3.0'],  // 3h29m -> 3.0h
        [210, '3.5'],  // 3h30m -> 3.5h
    ];
    
    echo "Testing overtime rounding logic:\n";
    echo "================================\n";
    
    foreach ($testCases as $index => $case) {
        [$actualMinutes, $expected] = $case;
        $result = roundOvertimeHours($actualMinutes);
        $status = ($result === $expected) ? "PASS" : "FAIL";
        
        $hoursDecimal = $actualMinutes / 60;
        echo "Test " . ($index + 1) . ": $status\n";
        echo "  Actual: " . $actualMinutes . " minutes (" . $hoursDecimal . " hours)\n";
        echo "  Expected: $expected hours, Got: $result hours\n\n";
    }
}

function roundOvertimeHours($minutes) {
    // Convert minutes to hours
    $hours = $minutes / 60;
    
    // If less than 1.5 hours (90 minutes), return 1.5 (minimum threshold)
    if ($minutes < 90) {
        return number_format(1.5, 1, '.', '');
    }
    
    // For 1.5 hours and above:
    // Round down to the nearest 30-minute increment
    // First, subtract 1.5 hours (90 minutes) from the total
    $adjustedMinutes = $minutes - 90;
    
    // Then round down to nearest 30-minute increment
    $roundedAdjusted = floor($adjustedMinutes / 30) * 30;
    
    // Add back the 1.5 hours base
    $finalMinutes = 90 + $roundedAdjusted;
    
    // Convert back to hours
    $finalHours = $finalMinutes / 60;
    
    return number_format($finalHours, 1, '.', '');
}

// Run the test
testOvertimeRounding();
?>