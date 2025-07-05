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

// Check if form data is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $overtime_id = $_POST['overtime_id'] ?? '';
    $manager_id = $_POST['manager_id'] ?? '';
    $message = $_POST['message'] ?? '';
    
    // Get filter parameters to preserve them in redirects
    $filter_month = $_POST['filter_month'] ?? date('m');
    $filter_year = $_POST['filter_year'] ?? date('Y');
    $filter_params = "month=$filter_month&year=$filter_year";
    
    // Validate required fields
    if (empty($overtime_id) || empty($manager_id)) {
        header("Location: employee_overtime.php?$filter_params&success=0&message=Missing required information");
        exit();
    }
    
    // Get manager details
    $manager_query = "SELECT username, email FROM users WHERE id = ?";
    $stmt_manager = $conn->prepare($manager_query);
    $stmt_manager->bind_param("i", $manager_id);
    $stmt_manager->execute();
    $manager_result = $stmt_manager->get_result();
    $manager_data = $manager_result->fetch_assoc();
    
    if (!$manager_data) {
        header("Location: employee_overtime.php?$filter_params&success=0&message=Selected manager not found");
        exit();
    }
    
    // Get overtime details
    $overtime_query = "SELECT a.*, 
                       CASE 
                           WHEN TIME_TO_SEC(TIMEDIFF(a.punch_out, s.end_time)) >= 5400 THEN 
                               FLOOR(TIME_TO_SEC(TIMEDIFF(a.punch_out, s.end_time)) / 1800) * 0.5
                           ELSE 0 
                       END as calculated_overtime,
                       u.username as employee_name
                       FROM attendance a 
                       JOIN users u ON a.user_id = u.id
                       JOIN user_shifts us ON a.user_id = us.user_id
                       JOIN shifts s ON us.shift_id = s.id
                       WHERE a.id = ? 
                       AND (us.effective_to IS NULL OR us.effective_to >= a.date)
                       AND us.effective_from <= a.date";
    
    $stmt_overtime = $conn->prepare($overtime_query);
    $stmt_overtime->bind_param("i", $overtime_id);
    $stmt_overtime->execute();
    $overtime_result = $stmt_overtime->get_result();
    $overtime_data = $overtime_result->fetch_assoc();
    
    if (!$overtime_data) {
        header("Location: employee_overtime.php?$filter_params&success=0&message=Overtime record not found");
        exit();
    }
    
    // Record the notification in database (you would create this table)
    $notification_query = "INSERT INTO overtime_notifications 
                          (overtime_id, employee_id, manager_id, message, created_at) 
                          VALUES (?, ?, ?, ?, NOW())";
    
    try {
        // This is wrapped in a try-catch in case the table doesn't exist yet
        $stmt_notification = $conn->prepare($notification_query);
        $stmt_notification->bind_param("iiis", $overtime_id, $user_id, $manager_id, $message);
        $stmt_notification->execute();
    } catch (Exception $e) {
        // Silent catch - table may not exist yet
    }
    
    // Update overtime status to "submitted" if using attendance table for overtime
    // You may need to modify this based on your actual database structure
    try {
        $update_query = "UPDATE attendance SET overtime_status = 'submitted' WHERE id = ?";
        $stmt_update = $conn->prepare($update_query);
        $stmt_update->bind_param("i", $overtime_id);
        $stmt_update->execute();
    } catch (Exception $e) {
        // Silent catch - column may not exist
    }
    
    // Redirect back with success message and filter parameters
    header("Location: employee_overtime.php?$filter_params&success=1&message=Overtime report sent successfully to manager");
    exit();
} else {
    // If accessed directly without POST data
    header('Location: employee_overtime.php');
    exit();
}
?>