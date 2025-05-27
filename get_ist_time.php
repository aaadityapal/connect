<?php
// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Format time with leading zeros for consistent display
$time = date('h:i A'); // 02:58 PM format (leading zero for hour)
$date = date('l, d F Y'); // Tuesday, 27 May 2025 format (leading zero for day)

// Also provide individual date components to prevent client-side parsing issues
$date_parts = [
    'weekday' => date('l'),  // Tuesday
    'day' => date('d'),      // 27 (with leading zero)
    'month' => date('F'),    // May
    'year' => date('Y')      // 2025
];

// Return as JSON
header('Content-Type: application/json');
echo json_encode([
    'time' => $time,
    'date' => $date,
    'date_parts' => $date_parts
]);
?> 