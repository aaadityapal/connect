<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../config/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get month and year from request
$month = isset($_GET['month']) ? $_GET['month'] : '';
$year = isset($_GET['year']) ? $_GET['year'] : '';

// Validate inputs
if (empty($month) || empty($year)) {
    echo json_encode(['success' => false, 'message' => 'Month and year are required']);
    exit;
}

// Month names
$months = [
    'January' => 1, 'February' => 2, 'March' => 3, 'April' => 4,
    'May' => 5, 'June' => 6, 'July' => 7, 'August' => 8,
    'September' => 9, 'October' => 10, 'November' => 11, 'December' => 12
];

// Convert month name to number
$monthNum = isset($months[$month]) ? $months[$month] : null;

if (!$monthNum || !is_numeric($year)) {
    echo json_encode(['success' => false, 'message' => 'Invalid month or year']);
    exit;
}

// Get current date information
$currentYear = date('Y');
$currentMonth = date('F'); // Full month name
$currentMonthNum = date('n'); // 1-12
$dayOfMonth = date('j'); // Day of the month (1-31)

// Calculate weeks for the selected month and year
try {
    // Get first and last day of selected month
    $firstDay = new DateTime("$year-$monthNum-01");
    $lastDay = new DateTime($firstDay->format('Y-m-t')); // t = last day of month
    
    // Get day of week for first day (0 = Sunday, 6 = Saturday)
    $firstDayOfWeek = (int)$firstDay->format('w');
    $lastDate = (int)$lastDay->format('j');
    
    // Calculate weeks based on the actual calendar
    $weeks = [];
    $weekStart = 1;
    $weekNum = 1;
    
    // If month doesn't start on Sunday, adjust first week
    if ($firstDayOfWeek > 0) {
        $endOfFirstWeek = 7 - $firstDayOfWeek;
        $weeks["Week $weekNum"] = "Week $weekNum (" . $weekStart . "-" . ($weekStart + $endOfFirstWeek) . ")";
        $weekStart += $endOfFirstWeek + 1;
        $weekNum++;
    }
    
    // Process remaining weeks
    while ($weekStart <= $lastDate) {
        $weekEnd = min($weekStart + 6, $lastDate);
        $weeks["Week $weekNum"] = "Week $weekNum (" . $weekStart . "-" . $weekEnd . ")";
        $weekStart = $weekEnd + 1;
        $weekNum++;
    }
    
    // Determine current week
    $currentWeek = '';
    if ($month === $currentMonth && $year == $currentYear) {
        foreach ($weeks as $key => $weekLabel) {
            if (preg_match('/\((\d+)-(\d+)\)/', $weekLabel, $matches)) {
                $start = (int)$matches[1];
                $end = (int)$matches[2];
                if ($dayOfMonth >= $start && $dayOfMonth <= $end) {
                    $currentWeek = $key;
                    break;
                }
            }
        }
    }
    
    // Return success response with weeks data
    echo json_encode([
        'success' => true, 
        'weeks' => $weeks,
        'currentWeek' => $currentWeek,
        'currentMonth' => $currentMonth,
        'currentYear' => $currentYear
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error calculating weeks: ' . $e->getMessage()]);
}
?>