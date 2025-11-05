<?php
// Test script to verify the time conversion logic
function testTimeConversion() {
    // Test cases: [24_hour_format, expected_12_hour_format]
    $testCases = [
        ['00:00:00', '12:00 AM'],
        ['05:30:00', '5:30 AM'],
        ['12:00:00', '12:00 PM'],
        ['13:45:00', '1:45 PM'],
        ['18:30:00', '6:30 PM'],
        ['23:59:00', '11:59 PM'],
        ['09:15:00', '9:15 AM'],
        ['17:00:00', '5:00 PM'],
    ];
    
    echo "Testing time conversion logic:\n";
    echo "==============================\n";
    
    foreach ($testCases as $index => $case) {
        [$input, $expected] = $case;
        $result = convertTo12HourFormat($input);
        $status = ($result === $expected) ? "PASS" : "FAIL";
        
        echo "Test " . ($index + 1) . ": $status\n";
        echo "  Input: $input\n";
        echo "  Expected: $expected, Got: $result\n\n";
    }
}

function convertTo12HourFormat($time) {
    if (!$time || $time === 'N/A') {
        return $time;
    }
    
    // Parse the time
    $timeParts = explode(':', $time);
    if (count($timeParts) < 2) {
        return $time;
    }
    
    $hours = (int)$timeParts[0];
    $minutes = $timeParts[1];
    
    // Determine AM/PM
    $period = ($hours >= 12) ? 'PM' : 'AM';
    
    // Convert hours to 12-hour format
    if ($hours == 0) {
        $hours = 12;
    } else if ($hours > 12) {
        $hours = $hours - 12;
    }
    
    return sprintf('%d:%s %s', $hours, $minutes, $period);
}

// Run the test
testTimeConversion();
?>