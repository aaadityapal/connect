<?php
// Database connection
require_once 'config/db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $date = $_POST['date'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $work_report = $_POST['work_report'] ?? '';
    
    // Get filter parameters to preserve them in redirects
    $filter_month = $_POST['filter_month'] ?? date('m');
    $filter_year = $_POST['filter_year'] ?? date('Y');
    $filter_params = "month=$filter_month&year=$filter_year";
    
    // Validate required fields
    if (empty($date) || empty($end_time) || empty($work_report)) {
        header("Location: employee_overtime.php?$filter_params&success=0&message=Please fill all required fields");
        exit();
    }
    
    // Get user's shift end time from the database
    $shift_query = "SELECT s.start_time, s.end_time, us.weekly_offs 
                    FROM shifts s 
                    JOIN user_shifts us ON s.id = us.shift_id 
                    WHERE us.user_id = ? 
                    AND (us.effective_to IS NULL OR us.effective_to >= ?)
                    AND us.effective_from <= ?
                    ORDER BY us.effective_from DESC 
                    LIMIT 1";

    $shift_stmt = $conn->prepare($shift_query);
    $shift_stmt->bind_param("iss", $user_id, $date, $date);
    $shift_stmt->execute();
    $shift_result = $shift_stmt->get_result();
    $shift_data = $shift_result->fetch_assoc();

    // Default shift end time if no shift data found
    $shift_end_time = $shift_data ? $shift_data['end_time'] : '18:00:00'; // 6:00 PM default
    
    // Calculate overtime - check if end time is at least 1.5 hours after shift end
    $punch_out_time = $date . ' ' . $end_time . ':00';
    $shift_end_full = $date . ' ' . $shift_end_time;
    
    $punch_out_timestamp = strtotime($punch_out_time);
    $shift_end_timestamp = strtotime($shift_end_full);
    
    // Calculate time difference in seconds
    $time_diff_seconds = $punch_out_timestamp - $shift_end_timestamp;
    
    // Check if overtime is at least 1.5 hours (5400 seconds)
    if ($time_diff_seconds < 5400) {
        header("Location: employee_overtime.php?$filter_params&success=0&message=Overtime must be at least 1.5 hours after your shift end time");
        exit();
    }
    
    // Calculate rounded down overtime hours (to nearest 30 minutes)
    $overtime_hours = floor($time_diff_seconds / 1800) * 0.5;
    
    // Check if record already exists for this date
    $check_query = "SELECT id FROM attendance WHERE user_id = ? AND date = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("is", $user_id, $date);
    $check_stmt->execute();
    $existing_record = $check_stmt->get_result();
    
    if ($existing_record->num_rows > 0) {
        // Update existing record
        $row = $existing_record->fetch_assoc();
        $update_query = "UPDATE attendance SET punch_out = ?, work_report = ?, status = 'pending' WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ssi", $end_time, $work_report, $row['id']);
        
        if ($update_stmt->execute()) {
            header("Location: employee_overtime.php?$filter_params&success=1&message=Overtime request updated successfully");
            exit();
        } else {
            header("Location: employee_overtime.php?$filter_params&success=0&message=Error updating overtime request");
            exit();
        }
    } else {
        // Insert new record
        $insert_query = "INSERT INTO attendance (user_id, date, punch_out, work_report, status) VALUES (?, ?, ?, ?, 'pending')";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("isss", $user_id, $date, $end_time, $work_report);
        
        if ($insert_stmt->execute()) {
            header("Location: employee_overtime.php?$filter_params&success=1&message=Overtime request submitted successfully");
            exit();
        } else {
            header("Location: employee_overtime.php?$filter_params&success=0&message=Error submitting overtime request");
            exit();
        }
    }
} else {
    // Not a POST request, redirect to the overtime page
    header('Location: employee_overtime.php');
    exit();
} 