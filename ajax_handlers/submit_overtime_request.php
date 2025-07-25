<?php
/**
 * Ajax handler to submit overtime requests
 * Used by the recent_time_widget.php when users punch out with overtime
 */

// Include database connection
require_once '../config/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Start session with looser restrictions to ensure session is read properly
session_start();

// Check if user is logged in - either from session or from POST parameter
$user_id = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
    // Allow explicit user_id from POST as fallback for testing
    $user_id = intval($_POST['user_id']);
}

if ($user_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit;
}

// Get parameters from POST
// $user_id is already defined above
$date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
$overtime_hours = isset($_POST['overtime_hours']) ? $_POST['overtime_hours'] : '';
$shift_end_time = isset($_POST['shift_end_time']) ? $_POST['shift_end_time'] : '';
$overtime_reason = isset($_POST['overtime_reason']) ? $_POST['overtime_reason'] : '';

// Validate parameters
if ($user_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user ID'
    ]);
    exit;
}

if (empty($overtime_hours) || empty($shift_end_time)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid date format'
    ]);
    exit;
}

// Get user information
$user_query = "SELECT username, email FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_query);
if (!$user_stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'User not found'
    ]);
    exit;
}

$user_data = $user_result->fetch_assoc();
$username = $user_data['username'];
$email = $user_data['email'];

// Get manager information
$manager_query = "SELECT m.id, m.username, m.email 
                 FROM users m 
                 JOIN user_managers um ON m.id = um.manager_id 
                 WHERE um.user_id = ? 
                 AND ? BETWEEN um.effective_from AND IFNULL(um.effective_to, '9999-12-31')
                 LIMIT 1";

$manager_stmt = $conn->prepare($manager_query);
if (!$manager_stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

$manager_stmt->bind_param("is", $user_id, $date);
$manager_stmt->execute();
$manager_result = $manager_stmt->get_result();

$manager_id = null;
$manager_email = null;

if ($manager_result->num_rows > 0) {
    $manager_data = $manager_result->fetch_assoc();
    $manager_id = $manager_data['id'];
    $manager_email = $manager_data['email'];
} else {
    // If no direct manager found, get HR or admin
    $admin_query = "SELECT id, username, email FROM users WHERE role IN ('hr', 'admin') LIMIT 1";
    $admin_result = $conn->query($admin_query);
    
    if ($admin_result && $admin_result->num_rows > 0) {
        $admin_data = $admin_result->fetch_assoc();
        $manager_id = $admin_data['id'];
        $manager_email = $admin_data['email'];
    }
}

// If still no manager found, use a default
if (!$manager_id) {
    $manager_id = 1; // Assuming ID 1 is an admin or system account
}

// Get the attendance record ID
$attendance_query = "SELECT id FROM attendance WHERE user_id = ? AND date = ? LIMIT 1";
$attendance_stmt = $conn->prepare($attendance_query);
if (!$attendance_stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

$attendance_stmt->bind_param("is", $user_id, $date);
$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result();

if ($attendance_result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No attendance record found for this date'
    ]);
    exit;
}

$attendance_row = $attendance_result->fetch_assoc();
$attendance_id = $attendance_row['id'];

// Update the attendance record with overtime status
$update_attendance_query = "UPDATE attendance 
                           SET overtime_status = 'submitted', 
                               overtime_reason = ?,
                               overtime_hours = ?,
                               updated_at = NOW() 
                           WHERE id = ?";
$update_attendance_stmt = $conn->prepare($update_attendance_query);
if (!$update_attendance_stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

$update_attendance_stmt->bind_param("ssi", $overtime_reason, $overtime_hours, $attendance_id);
$update_attendance_stmt->execute();

// Create or update overtime notification
try {
    // Check if notification already exists for this attendance
    $check_query = "SELECT id FROM overtime_notifications 
                   WHERE employee_id = ? AND DATE(created_at) = ?";
    $check_stmt = $conn->prepare($check_query);
    if (!$check_stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $check_stmt->bind_param("is", $user_id, $date);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    $message = "Overtime request: $overtime_hours beyond shift end time ($shift_end_time) on $date";
    
    if ($check_result->num_rows > 0) {
        // Update existing notification
        $row = $check_result->fetch_assoc();
        $notification_id = $row['id'];
        
        $update_query = "UPDATE overtime_notifications 
                        SET message = ?, 
                            status = 'pending', 
                            manager_response = NULL, 
                            created_at = NOW(), 
                            read_at = NULL, 
                            actioned_at = NULL 
                        WHERE id = ?";
        
        $update_stmt = $conn->prepare($update_query);
        if (!$update_stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $update_stmt->bind_param("si", $message, $notification_id);
        $update_stmt->execute();
        
        if ($update_stmt->affected_rows <= 0) {
            throw new Exception('Failed to update overtime notification');
        }
    } else {
        // Create new notification
        $insert_query = "INSERT INTO overtime_notifications 
                        (employee_id, manager_id, message, status, created_at) 
                        VALUES (?, ?, ?, 'pending', NOW())";
        
        $insert_stmt = $conn->prepare($insert_query);
        if (!$insert_stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $insert_stmt->bind_param("iis", $user_id, $manager_id, $message);
        $insert_stmt->execute();
        
        if ($insert_stmt->affected_rows <= 0) {
            throw new Exception('Failed to insert overtime notification');
        }
        
        $notification_id = $insert_stmt->insert_id;
    }
    
    // Send email to manager if email is available
    if ($manager_email) {
        $subject = "Overtime Request from $username";
        $message = "
            <html>
            <head>
                <title>Overtime Request</title>
            </head>
            <body>
                <p>Hello,</p>
                <p>An overtime request has been submitted with the following details:</p>
                <ul>
                    <li><strong>Employee:</strong> $username</li>
                    <li><strong>Date:</strong> $date</li>
                    <li><strong>Overtime Hours:</strong> $overtime_hours</li>
                    <li><strong>Shift End Time:</strong> $shift_end_time</li>
                </ul>
                <p>Please review this request in the HR system.</p>
                <p>Thank you.</p>
            </body>
            </html>
        ";
        
        // Headers for HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: HR System <noreply@example.com>" . "\r\n";
        
        // Attempt to send email, but don't fail if it doesn't work
        @mail($manager_email, $subject, $message, $headers);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Overtime request submitted successfully',
        'notification_id' => $notification_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error submitting overtime request: ' . $e->getMessage()
    ]);
} 