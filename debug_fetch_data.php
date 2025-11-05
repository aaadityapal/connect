<?php
header('Content-Type: application/json');

// Simulate the same logic as fetch_employee_overtime_data.php
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n') - 1; // 0-11
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Calculate the first and last day of the selected month
$first_day = sprintf('%04d-%02d-01', $filter_year, $filter_month + 1);
$last_day = date('Y-m-t', strtotime($first_day));

echo json_encode([
    'filter_month' => $filter_month,
    'filter_year' => $filter_year,
    'first_day' => $first_day,
    'last_day' => $last_day,
    'current_date' => date('Y-m-d'),
    'current_month' => date('n') - 1,
    'current_year' => date('Y')
]);
?>