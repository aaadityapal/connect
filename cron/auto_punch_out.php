<?php
require_once '../config/db_connect.php';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

function autoPunchOut($conn) {
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');

    // Get all users who haven't punched out and whose shift has ended
    $query = "SELECT 
                a.id, 
                a.user_id, 
                a.punch_in,
                s.end_time,
                TIMEDIFF(s.end_time, a.punch_in) as working_hours
            FROM attendance a
            JOIN user_shifts us ON a.user_id = us.user_id
            JOIN shifts s ON us.shift_id = s.id
            WHERE a.date = ?
            AND a.punch_out IS NULL
            AND TIME(?) > s.end_time";

    try {
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $current_date, $current_time);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            // Update attendance with auto punch out
            $update_query = "UPDATE attendance 
                           SET punch_out = ?,
                               working_hours = ?,
                               auto_punch_out = 1,
                               modified_at = NOW(),
                               modified_by = 'system'
                           WHERE id = ?";
            
            $update_stmt = $conn->prepare($update_query);
            $shift_end_time = $row['end_time'];
            $working_hours = $row['working_hours'];
            
            $update_stmt->bind_param('ssi', 
                $shift_end_time,    // Punch out at shift end time
                $working_hours,     // Working hours without overtime
                $row['id']
            );
            
            if ($update_stmt->execute()) {
                // Log the auto punch out
                $log_query = "INSERT INTO attendance_logs 
                             (attendance_id, action, details, created_at)
                             VALUES (?, 'auto_punch_out', 'Auto punch out at shift end time', NOW())";
                
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param('i', $row['id']);
                $log_stmt->execute();
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Auto punch out error: " . $e->getMessage());
        return false;
    }
}

// Execute auto punch out
autoPunchOut($conn); 