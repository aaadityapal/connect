<?php
/**
 * Attendance Notification System
 * 
 * Handles notifications for managers about pending attendance approvals
 */

// Use __DIR__ for a reliable path resolution
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/ensure_notifications_table.php';

/**
 * Notify manager about pending attendance approval
 * 
 * @param int $manager_id Manager's user ID
 * @param int $employee_id Employee's user ID
 * @param int $attendance_id Attendance record ID
 * @param string $type Type of attendance (punch_in or punch_out)
 * @return bool Success status
 */
function notify_manager($manager_id, $employee_id, $attendance_id, $type) {
    global $conn;
    
    if (empty($manager_id) || empty($employee_id) || empty($attendance_id)) {
        error_log("Missing required parameters for manager notification");
        return false;
    }
    
    try {
        // Ensure notifications table exists
        if (!ensure_notifications_table($conn)) {
            error_log("Failed to ensure notifications table exists");
            return false;
        }
        
        // Get employee name and details
        $employee_query = "SELECT username, designation, employee_id, department, profile_picture FROM users WHERE id = ?";
        $stmt = $conn->prepare($employee_query);
        $stmt->bind_param('i', $employee_id);
        $stmt->execute();
        $employee_result = $stmt->get_result();
        
        if ($employee_result->num_rows == 0) {
            error_log("Employee not found for ID: $employee_id");
            return false;
        }
        
        $employee_data = $employee_result->fetch_assoc();
        // Use username and designation instead of first_name and last_name
        $employee_name = !empty($employee_data['username']) ? $employee_data['username'] : "Employee #$employee_id";
        if (!empty($employee_data['designation'])) {
            $employee_name .= " (" . $employee_data['designation'] . ")";
        }
        
        // Get attendance details
        $attendance_query = "SELECT date, punch_in, punch_out, within_geofence, distance_from_geofence, 
                            address, punch_in_outside_reason, punch_out_outside_reason
                            FROM attendance WHERE id = ?";
        $stmt = $conn->prepare($attendance_query);
        $stmt->bind_param('i', $attendance_id);
        $stmt->execute();
        $attendance_result = $stmt->get_result();
        
        if ($attendance_result->num_rows == 0) {
            error_log("Attendance record not found for ID: $attendance_id");
            return false;
        }
        
        $attendance_data = $attendance_result->fetch_assoc();
        
        // Create notification with detailed information
        $action_text = ($type == 'punch_in') ? 'punched in' : 'punched out';
        $notification_title = "Attendance approval required";
        
        // Format date and time for better readability
        $attendance_date = date('d M Y', strtotime($attendance_data['date']));
        $punch_time = date('h:i A', strtotime($type == 'punch_in' ? $attendance_data['punch_in'] : $attendance_data['punch_out']));
        
        // Create detailed content
        $notification_content = "{$employee_name} has {$action_text} from outside the office perimeter on {$attendance_date} at {$punch_time}. ";
        
        // Add employee ID if available
        if (!empty($employee_data['employee_id'])) {
            $notification_content = "{$employee_name} (ID: {$employee_data['employee_id']}) has {$action_text} from outside the office perimeter on {$attendance_date} at {$punch_time}. ";
        }
        
        $notification_content .= "Distance: " . round($attendance_data['distance_from_geofence']) . " meters from allowed area. ";
        
        // Add reason if available
        $reason_field = $type == 'punch_in' ? 'punch_in_outside_reason' : 'punch_out_outside_reason';
        if (!empty($attendance_data[$reason_field])) {
            $notification_content .= "Reason: " . $attendance_data[$reason_field];
        }
        
        // Generate approval link
        $approval_link = "attendance_approval.php?id={$attendance_id}";
        
        // Save to notifications table - ensure this happens
        $insert_query = "INSERT INTO notifications (user_id, title, content, link, type, is_read, created_at) 
                        VALUES (?, ?, ?, ?, 'attendance_approval', 0, NOW())";
        
        $stmt = $conn->prepare($insert_query);
        if (!$stmt) {
            error_log("Error preparing notification insert: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param('isss', $manager_id, $notification_title, $notification_content, $approval_link);
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("Error inserting notification: " . $stmt->error);
            return false;
        }
        
        // Log successful notification
        error_log("Attendance notification created successfully for attendance ID: $attendance_id, manager ID: $manager_id");
        
        // Check if email notifications are enabled
        // This is optional and depends on your system's setup
        $email_query = "SELECT email, notification_preferences FROM users WHERE id = ?";
        $stmt = $conn->prepare($email_query);
        $stmt->bind_param('i', $manager_id);
        $stmt->execute();
        $email_result = $stmt->get_result();
        
        if ($email_result->num_rows > 0) {
            $manager_data = $email_result->fetch_assoc();
            $manager_email = $manager_data['email'];
            
            // Check if email notifications are enabled
            $preferences = json_decode($manager_data['notification_preferences'], true);
            $send_email = isset($preferences['email_attendance_approval']) ? 
                $preferences['email_attendance_approval'] : true;
            
            if ($send_email && !empty($manager_email)) {
                // Send email notification - implement your email sending logic here
                // sendEmail($manager_email, $notification_title, $notification_content);
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error sending manager notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Count pending attendance approvals for a manager
 *
 * @param int $manager_id Manager's user ID
 * @return int Number of pending approvals
 */
function count_pending_approvals($manager_id) {
    global $conn;
    
    if (empty($manager_id)) {
        return 0;
    }
    
    $query = "SELECT COUNT(*) AS pending_count 
              FROM attendance 
              WHERE manager_id = ? AND approval_status = 'pending'";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $manager_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['pending_count'];
    }
    
    return 0;
}
?> 