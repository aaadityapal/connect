<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Include config file
require_once 'config/db_connect.php';

// This file handles AJAX requests to fetch weekly expenses
header('Content-Type: application/json');

$month = isset($_POST['month']) ? (int)$_POST['month'] : date('n');
$year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');
$week = isset($_POST['week']) ? (int)$_POST['week'] : null;
$userId = isset($_POST['user_id']) ? $_POST['user_id'] : null;

try {
    // Start building the query
    $query = "SELECT te.*, u.username 
              FROM travel_expenses te
              JOIN users u ON te.user_id = u.id
              WHERE (te.manager_status = 'Approved' OR te.status = 'Approved')";
    
    $params = [];
    
    // Apply user filter
    if (!empty($userId)) {
        $query .= " AND te.user_id = ?";
        $params[] = $userId;
    }
    
    // Apply month filter
    if ($month) {
        $query .= " AND MONTH(te.travel_date) = ?";
        $params[] = $month;
    }
    
    // Apply year filter
    if ($year) {
        $query .= " AND YEAR(te.travel_date) = ?";
        $params[] = $year;
    }
    
    // Apply week filter
    if ($week) {
        // Calculate date range for the selected week
        $firstDayOfMonth = new DateTime("$year-$month-01");
        $dayOfWeek = (int)$firstDayOfMonth->format('w');
        $daysInMonth = (int)$firstDayOfMonth->format('t');
        
        // Find the date range for the selected week
        $weekStartDay = 1;
        $weekEndDay = 1;
        
        for ($currentWeek = 1; $currentWeek <= 6; $currentWeek++) {
            $weekStartDay = ($currentWeek == 1) ? 1 : $weekEndDay + 1;
            
            // If we've gone beyond the end of month, break
            if ($weekStartDay > $daysInMonth) break;
            
            // Calculate start date of this week
            $startDate = new DateTime("$year-$month-$weekStartDay");
            $startDayOfWeek = (int)$startDate->format('w');
            
            // Calculate end day (either the next Sunday or end of month)
            if ($startDayOfWeek > 0) {
                $daysUntilSunday = 7 - $startDayOfWeek;
                $weekEndDay = min($weekStartDay + $daysUntilSunday, $daysInMonth);
            } else {
                $weekEndDay = $weekStartDay; // If Sunday, just this day
            }
            
            // Special case for last days of month
            if ($currentWeek == 6 || $weekEndDay == $daysInMonth) {
                $weekEndDay = $daysInMonth;
            }
            
            // If this is our target week
            if ($currentWeek == $week) {
                $weekStartDate = "$year-$month-$weekStartDay";
                $weekEndDate = "$year-$month-$weekEndDay";
                
                $query .= " AND te.travel_date BETWEEN ? AND ?";
                $params[] = $weekStartDate;
                $params[] = $weekEndDate;
                break;
            }
            
            // If this is the last day of the month, we're done
            if ($weekEndDay >= $daysInMonth) break;
        }
    }
    
    // Order by date and username
    $query .= " ORDER BY te.travel_date DESC, u.username";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    $expenses = [];
    while ($expense = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $expenses[] = $expense;
    }
    
    echo json_encode([
        'success' => true,
        'expenses' => $expenses
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 