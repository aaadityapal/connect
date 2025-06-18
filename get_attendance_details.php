<?php
session_start();

// Add authentication check
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

// Get parameters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Validate inputs
if ($user_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

// Define the date range for the month
$month_start = $month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

try {
    // Get all days in the month with leave type information
    $days_in_month = date('t', strtotime($month_start));
    $all_days = [];

         // Generate all days in the month
     for ($day = 0; $day < $days_in_month; $day++) {
         $current_date = date('Y-m-d', strtotime($month_start . ' + ' . $day . ' days'));
         $all_days[$current_date] = [
             'date' => $current_date,
                'status' => 'not recorded',
                'punch_in' => null,
                'punch_out' => null,
             'punch_in_time' => null,
             'punch_out_time' => null,
                'working_hours' => null,
             'is_weekly_off' => 0,
             'leave_type_name' => null,
             'leave_type_id' => null,
             'leave_color' => null
         ];
        }

    // Get attendance records
    $attendance_query = "SELECT 
                        date,
                    status, 
                        punch_in,
                        punch_out,
                    TIME(punch_in) as punch_in_time,
                    TIME(punch_out) as punch_out_time,
                    working_hours,
                    is_weekly_off
                  FROM attendance 
                  WHERE user_id = ? 
                    AND date BETWEEN ? AND ?";
        
    $attendance_stmt = $pdo->prepare($attendance_query);
    $attendance_stmt->execute([$user_id, $month_start, $month_end]);
    $attendance_records = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    // Merge attendance records into all days
    foreach ($attendance_records as $record) {
        $date = $record['date'];
        if (isset($all_days[$date])) {
            $all_days[$date] = array_merge($all_days[$date], $record);
        }
    }

    // Get leave records
    $leave_query = "SELECT 
                    lr.start_date, 
                    lr.end_date, 
                    lr.leave_type as leave_type_id,
                    lt.name as leave_type_name,
                    lt.color_code as leave_color
                FROM leave_request lr
                JOIN leave_types lt ON lr.leave_type = lt.id
                WHERE lr.user_id = ?
                AND lr.status = 'approved'
                AND (
                    (lr.start_date BETWEEN ? AND ?) 
                    OR 
                    (lr.end_date BETWEEN ? AND ?)
                    OR 
                    (lr.start_date <= ? AND lr.end_date >= ?)
                )";

    $leave_stmt = $pdo->prepare($leave_query);
    $leave_stmt->execute([
        $user_id, 
        $month_start, $month_end, 
        $month_start, $month_end,
        $month_start, $month_end
    ]);
    $leave_records = $leave_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Apply leave information to days
    foreach ($leave_records as $leave) {
        $start_date = max($leave['start_date'], $month_start);
        $end_date = min($leave['end_date'], $month_end);
        
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        while ($current <= $end) {
            $date_key = $current->format('Y-m-d');
            if (isset($all_days[$date_key])) {
                $all_days[$date_key]['status'] = 'leave';
                $all_days[$date_key]['leave_type_id'] = $leave['leave_type_id'];
                $all_days[$date_key]['leave_type_name'] = $leave['leave_type_name'];
                $all_days[$date_key]['leave_color'] = $leave['leave_color'];
            }
            $current->modify('+1 day');
        }
        }
        
    // Convert to indexed array and format times
    $records = array_values($all_days);
    foreach ($records as &$record) {
        // Format punch times
        if (!empty($record['punch_in_time'])) {
            $punch_in_parts = explode(':', $record['punch_in_time']);
            if (count($punch_in_parts) >= 2) {
                $hours = intval($punch_in_parts[0]);
                $minutes = $punch_in_parts[1];
                $ampm = $hours >= 12 ? 'PM' : 'AM';
                $hours12 = $hours % 12 || 12;
                $record['punch_in_formatted'] = sprintf('%d:%s %s', $hours12, $minutes, $ampm);
        }
        }
        
        if (!empty($record['punch_out_time'])) {
            $punch_out_parts = explode(':', $record['punch_out_time']);
            if (count($punch_out_parts) >= 2) {
                $hours = intval($punch_out_parts[0]);
                $minutes = $punch_out_parts[1];
                $ampm = $hours >= 12 ? 'PM' : 'AM';
                $hours12 = $hours % 12 || 12;
                $record['punch_out_formatted'] = sprintf('%d:%s %s', $hours12, $minutes, $ampm);
            }
        }
    }
    unset($record);
    
    // Log for debugging
    error_log("Returning " . count($records) . " attendance records for user " . $user_id . " in month " . $month);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($records);
    
} catch (PDOException $e) {
    error_log("Error fetching attendance details: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error occurred: ' . $e->getMessage()]);
}
?>
