<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

// Get parameters
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$type = isset($_GET['type']) ? $_GET['type'] : 'leave'; // 'leave' or 'late'

if ($user_id <= 0) {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

// Get the selected month's start and end dates
$month_start = date('Y-m-01', strtotime($month));
$month_end = date('Y-m-t', strtotime($month));

try {
    if ($type === 'leave') {
        // Fetch detailed leave information
        $leave_query = "SELECT 
                           lr.id,
                           lr.start_date,
                           lr.end_date,
                           lr.duration_type,
                           lr.reason,
                           lt.name as leave_type,
                           lt.color_code,
                           CASE 
                               WHEN lr.duration_type = 'half_day' THEN 0.5 
                               ELSE 
                                  LEAST(DATEDIFF(
                                      LEAST(lr.end_date, ?), 
                                      GREATEST(lr.start_date, ?)
                                  ) + 1, 
                                  DATEDIFF(?, ?) + 1)
                           END as days_count
                    FROM leave_request lr
                    LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                    WHERE lr.user_id = ? 
                    AND lr.status = 'approved'
                    AND (
                        (lr.start_date BETWEEN ? AND ?) 
                        OR 
                        (lr.end_date BETWEEN ? AND ?)
                        OR 
                        (lr.start_date <= ? AND lr.end_date >= ?)
                    )
                    ORDER BY lr.start_date";
        
        $leave_stmt = $pdo->prepare($leave_query);
        $leave_stmt->execute([
            $month_end, // For LEAST(lr.end_date, ?)
            $month_start, // For GREATEST(lr.start_date, ?)
            $month_end, // For DATEDIFF(?, ?)
            $month_start, // For DATEDIFF(?, ?)
            $user_id, 
            $month_start, $month_end, 
            $month_start, $month_end,
            $month_start, $month_end
        ]);
        
        $leave_details = $leave_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header("Content-Type: application/json");
        echo json_encode([
            'type' => 'leave',
            'user_id' => $user_id,
            'month' => $month,
            'details' => $leave_details
        ]);
    } else if ($type === 'late') {
        // Fetch user shift information to calculate grace time
        $shift_query = "SELECT s.start_time 
                       FROM users u
                       LEFT JOIN user_shifts us ON u.id = us.user_id AND 
                           (us.effective_to IS NULL OR us.effective_to >= CURDATE())
                       LEFT JOIN shifts s ON us.shift_id = s.id
                       WHERE u.id = ?";
        
        $shift_stmt = $pdo->prepare($shift_query);
        $shift_stmt->execute([$user_id]);
        $shift_result = $shift_stmt->fetch(PDO::FETCH_ASSOC);
        
        $shift_start = !empty($shift_result['start_time']) ? $shift_result['start_time'] : '09:00:00';
        $grace_time = date('H:i:s', strtotime($shift_start . ' +15 minutes'));
        $one_hour_late = date('H:i:s', strtotime($shift_start . ' +1 hour'));
        
        // Fetch detailed late information
        $late_query = "SELECT 
                          date,
                          punch_in,
                          TIME_TO_SEC(TIMEDIFF(TIME(punch_in), ?)) as seconds_late
                       FROM attendance 
                       WHERE user_id = ? 
                       AND DATE(date) BETWEEN ? AND ? 
                       AND status = 'present' 
                       AND TIME(punch_in) > ?
                       AND TIME(punch_in) <= ?
                       ORDER BY date";
        
        $late_stmt = $pdo->prepare($late_query);
        $late_stmt->execute([$shift_start, $user_id, $month_start, $month_end, $grace_time, $one_hour_late]);
        
        $late_details = $late_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert seconds to minutes for display
        foreach ($late_details as &$detail) {
            $detail['minutes_late'] = round($detail['seconds_late'] / 60);
        }
        
        header("Content-Type: application/json");
        echo json_encode([
            'type' => 'late',
            'user_id' => $user_id,
            'month' => $month,
            'shift_start' => $shift_start,
            'grace_time' => $grace_time,
            'details' => $late_details
        ]);
    } else if ($type === 'one_hour_late') {
        // Fetch user shift information to calculate 1 hour late time
        $shift_query = "SELECT s.start_time 
                       FROM users u
                       LEFT JOIN user_shifts us ON u.id = us.user_id AND 
                           (us.effective_to IS NULL OR us.effective_to >= CURDATE())
                       LEFT JOIN shifts s ON us.shift_id = s.id
                       WHERE u.id = ?";
        
        $shift_stmt = $pdo->prepare($shift_query);
        $shift_stmt->execute([$user_id]);
        $shift_result = $shift_stmt->fetch(PDO::FETCH_ASSOC);
        
        $shift_start = !empty($shift_result['start_time']) ? $shift_result['start_time'] : '09:00:00';
        $one_hour_late_time = date('H:i:s', strtotime($shift_start . ' +1 hour'));
        
        // Fetch detailed 1+ hour late information
        $one_hour_late_query = "SELECT 
                          date,
                          punch_in,
                          TIME_TO_SEC(TIMEDIFF(TIME(punch_in), ?)) as seconds_late
                       FROM attendance 
                       WHERE user_id = ? 
                       AND DATE(date) BETWEEN ? AND ? 
                       AND status = 'present' 
                       AND TIME(punch_in) > ?
                       ORDER BY date";
        
        $one_hour_late_stmt = $pdo->prepare($one_hour_late_query);
        $one_hour_late_stmt->execute([$shift_start, $user_id, $month_start, $month_end, $one_hour_late_time]);
        
        $one_hour_late_details = $one_hour_late_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert seconds to minutes for display
        foreach ($one_hour_late_details as &$detail) {
            $detail['minutes_late'] = round($detail['seconds_late'] / 60);
        }
        
        header("Content-Type: application/json");
        echo json_encode([
            'type' => 'one_hour_late',
            'user_id' => $user_id,
            'month' => $month,
            'shift_start' => $shift_start,
            'one_hour_late_time' => $one_hour_late_time,
            'details' => $one_hour_late_details
        ]);
    } else if ($type === 'present_days') {
        // Fetch detailed present days information
        $present_days_query = "SELECT 
                          date,
                          punch_in,
                          punch_out,
                          status
                       FROM attendance 
                       WHERE user_id = ? 
                       AND DATE(date) BETWEEN ? AND ? 
                       AND status = 'present'
                       ORDER BY date";
        
        $present_days_stmt = $pdo->prepare($present_days_query);
        $present_days_stmt->execute([$user_id, $month_start, $month_end]);
        
        $present_days_details = $present_days_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header("Content-Type: application/json");
        echo json_encode([
            'type' => 'present_days',
            'user_id' => $user_id,
            'month' => $month,
            'details' => $present_days_details
        ]);
    } else if ($type === 'short_leave') {
        // Fetch short leave information
        $short_leave_query = "SELECT 
                           lr.id,
                           lr.start_date,
                           lr.end_date,
                           lr.duration_type,
                           lr.reason,
                           lt.name as leave_type,
                           lt.color_code,
                           CASE 
                               WHEN lr.duration_type = 'half_day' THEN 0.5 
                               ELSE 
                                  LEAST(DATEDIFF(
                                      LEAST(lr.end_date, ?), 
                                      GREATEST(lr.start_date, ?)
                                  ) + 1, 
                                  DATEDIFF(?, ?) + 1)
                           END as days_count
                    FROM leave_request lr
                    LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                    WHERE lr.user_id = ? 
                    AND lr.status = 'approved'
                    AND lt.name = 'Short Leave'
                    AND (
                        (lr.start_date BETWEEN ? AND ?) 
                        OR 
                        (lr.end_date BETWEEN ? AND ?)
                        OR 
                        (lr.start_date <= ? AND lr.end_date >= ?)
                    )
                    ORDER BY lr.start_date";
        
        $short_leave_stmt = $pdo->prepare($short_leave_query);
        $short_leave_stmt->execute([
            $month_end, // For LEAST(lr.end_date, ?)
            $month_start, // For GREATEST(lr.start_date, ?)
            $month_end, // For DATEDIFF(?, ?)
            $month_start, // For DATEDIFF(?, ?)
            $user_id, 
            $month_start, $month_end, 
            $month_start, $month_end,
            $month_start, $month_end
        ]);
        
        $short_leave_details = $short_leave_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header("Content-Type: application/json");
        echo json_encode([
            'type' => 'short_leave',
            'user_id' => $user_id,
            'month' => $month,
            'details' => $short_leave_details
        ]);
    } else {
        header("Content-Type: application/json");
        echo json_encode(['error' => 'Invalid type parameter']);
    }
} catch (PDOException $e) {
    error_log("Error fetching details: " . $e->getMessage());
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Database error occurred']);
}