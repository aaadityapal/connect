<?php
// Test file to verify overtime calculation logic
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions/shift_functions.php';

echo "<h2>Overtime Calculation Test</h2>\n";

// Test cases
$test_cases = [
    [
        'shift_end_time' => '17:30:00',
        'punch_out_time' => '12:09:53', // Before shift end - should be 0 hours
        'description' => 'Punch out before shift end time'
    ],
    [
        'shift_end_time' => '17:30:00',
        'punch_out_time' => '19:30:00', // 2 hours after shift end - should be 2.0 hours
        'description' => '2 hours after shift end'
    ],
    [
        'shift_end_time' => '17:30:00',
        'punch_out_time' => '19:00:00', // 1.5 hours after shift end - should be 1.5 hours
        'description' => '1.5 hours after shift end'
    ],
    [
        'shift_end_time' => '17:30:00',
        'punch_out_time' => '18:45:00', // 1.25 hours after shift end - should be 1.5 hours (minimum)
        'description' => '1.25 hours after shift end (rounded to minimum)'
    ]
];

/**
 * Calculate overtime hours based on shift end time and punch out time
 */
function calculateOvertimeHours($shiftEndTime, $punchOutTime) {
    if (!$shiftEndTime || !$punchOutTime) {
        return '0.0';
    }
    
    // Convert times to seconds
    $shiftEndSeconds = timeToSeconds($shiftEndTime);
    $punchOutSeconds = timeToSeconds($punchOutTime);
    
    // Calculate overtime in seconds
    $overtimeSeconds = 0;
    
    // Only calculate overtime if punch out time is after shift end time
    if ($punchOutSeconds > $shiftEndSeconds) {
        // Same day punch out - calculate overtime as difference
        $overtimeSeconds = $punchOutSeconds - $shiftEndSeconds;
    }
    // If punchOutSeconds <= shiftEndSeconds, overtimeSeconds remains 0
    
    // If no overtime, return 0
    if ($overtimeSeconds <= 0) {
        return '0.0';
    }
    
    // Convert seconds to minutes
    $overtimeMinutes = $overtimeSeconds / 60;
    
    // Apply rounding logic:
    // - If less than 90 minutes (1.5 hours), return 1.5 (minimum threshold)
    // - Otherwise, round down to nearest 30-minute increment
    $roundedHours = roundOvertimeHours($overtimeMinutes);
    
    return number_format($roundedHours, 1, '.', '');
}

/**
 * Round overtime hours according to the specified rules:
 * - Minimum 1.5 hours
 * - Round down to nearest 30-minute increment
 */
function roundOvertimeHours($minutes) {
    // If less than 1.5 hours (90 minutes), return 1.5 (minimum threshold)
    if ($minutes < 90) {
        return 1.5;
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
    
    return $finalHours;
}

/**
 * Convert TIME format (HH:MM:SS) to seconds
 */
function timeToSeconds($time) {
    list($hours, $minutes, $seconds) = explode(':', $time);
    return ($hours * 3600) + ($minutes * 60) + $seconds;
}

/**
 * Convert 24-hour format to 12-hour AM/PM format
 */
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

// Run tests
foreach ($test_cases as $index => $test_case) {
    $shift_end_time = $test_case['shift_end_time'];
    $punch_out_time = $test_case['punch_out_time'];
    $description = $test_case['description'];
    
    $calculated_hours = calculateOvertimeHours($shift_end_time, $punch_out_time);
    
    echo "<h3>Test Case " . ($index + 1) . ": $description</h3>\n";
    echo "<ul>\n";
    echo "  <li>Shift End Time: " . convertTo12HourFormat($shift_end_time) . " ($shift_end_time)</li>\n";
    echo "  <li>Punch Out Time: " . convertTo12HourFormat($punch_out_time) . " ($punch_out_time)</li>\n";
    echo "  <li>Calculated Overtime Hours: $calculated_hours hours</li>\n";
    echo "</ul>\n";
    echo "<hr>\n";
}

echo "<h3>SQL Query Test</h3>\n";
echo "<p>Testing SQL query logic for filtering records:</p>\n";

// Test the SQL logic
$test_shift_end_time = '17:30:00'; // 17:30:00 in seconds = 63000
$test_punch_out_times = [
    '12:09:53', // Before shift end - should NOT be included
    '19:00:00', // 1.5 hours after shift end - should be included
    '19:30:00'  // 2 hours after shift end - should be included
];

$shift_end_seconds = timeToSeconds($test_shift_end_time);
$threshold_seconds = 5400; // 1.5 hours in seconds

echo "<ul>\n";
foreach ($test_punch_out_times as $punch_out_time) {
    $punch_out_seconds = timeToSeconds($punch_out_time);
    $should_include = $punch_out_seconds >= ($shift_end_seconds + $threshold_seconds);
    
    echo "  <li>Punch Out: " . convertTo12HourFormat($punch_out_time) . " ($punch_out_time) - ";
    echo "Should Include: " . ($should_include ? 'YES' : 'NO') . " ";
    echo "(Punch Out Seconds: $punch_out_seconds, Threshold: " . ($shift_end_seconds + $threshold_seconds) . ")</li>\n";
}
echo "</ul>\n";
?>