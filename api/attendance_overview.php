<?php
session_start();
require_once '../config/db_connect.php';
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$min_overtime = isset($_GET['min_overtime']) ? intval($_GET['min_overtime']) : 0;

// Calculate first and last day of selected month
$start_date = date('Y-m-01', strtotime("$year-$month-01"));
$end_date = date('Y-m-t', strtotime("$year-$month-01"));

// Fetch attendance data
$query = "SELECT 
            date,
            working_hours,
            overtime_hours,
            status
          FROM attendance 
          WHERE user_id = ? 
          AND date BETWEEN ? AND ?
          ORDER BY date ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $user_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$dates = [];
$workingHours = [];
$totalRegularHours = 0;
$totalOvertimeHours = 0;
$presentDays = 0;
$totalDays = 0;

while ($row = $result->fetch_assoc()) {
    $dates[] = date('d M', strtotime($row['date']));
    
    // Convert HH:MM:SS to hours
    list($hours, $minutes, $seconds) = explode(':', $row['working_hours']);
    $workingHours[] = round($hours + ($minutes/60), 2);
    
    // Calculate totals
    $totalRegularHours += $hours + ($minutes/60);
    
    if ($row['overtime_hours']) {
        list($ot_hours, $ot_minutes, $ot_seconds) = explode(':', $row['overtime_hours']);
        $total_minutes = ($ot_hours * 60) + $ot_minutes;
        
        if ($total_minutes > $min_overtime) {
            $totalOvertimeHours += $total_minutes;
        }
    }
    
    if ($row['status'] == 'present') {
        $presentDays++;
    }
    $totalDays++;
}

$attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0;

// Convert back to hours:minutes format for the response
$overtime_hours = floor($totalOvertimeHours / 60);
$overtime_minutes = $totalOvertimeHours % 60;
$formatted_overtime = sprintf("%02d:%02d", $overtime_hours, $overtime_minutes);

echo json_encode([
    'stats' => [
        'presentDays' => $presentDays,
        'totalHours' => round($totalRegularHours, 1),
        'overtimeHours' => $formatted_overtime,
        'attendanceRate' => $attendanceRate
    ],
    'chartData' => [
        'dates' => $dates,
        'workingHours' => $workingHours,
        'regularHours' => round($totalRegularHours, 1),
        'overtimeHours' => round($totalOvertimeHours, 1)
    ]
]);
?>