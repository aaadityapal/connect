<?php
session_start();
require_once 'config/db_connect.php';
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json');

// Function to get user's IP address
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Function to get device info
function getDeviceInfo() {
    return $_SERVER['HTTP_USER_AGENT'];
}

// Function to calculate working hours and overtime
function calculateWorkingHours($punch_in, $punch_out, $shift_end_time) {
    $in_time = strtotime($punch_in);
    $out_time = strtotime($punch_out);
    
    // Get today's date
    $today = date('Y-m-d');
    
    // Convert shift end time to full datetime for today
    $shift_end = strtotime($today . ' ' . $shift_end_time);
    
    // Calculate total working duration
    $total_seconds = max(0, $out_time - $in_time);
    
    // Check if punch in was after shift end
    if ($in_time > $shift_end) {
        // All time is overtime when punched in after shift end
        $regular_seconds = 0;
        $overtime_seconds = $total_seconds;
    } else {
        // Calculate regular hours (capped at shift end)
        $regular_seconds = min($total_seconds, max(0, $shift_end - $in_time));
        // Calculate overtime only if punched out after shift end
        $overtime_seconds = ($out_time > $shift_end) ? ($out_time - max($shift_end, $in_time)) : 0;
    }
    
    // Format regular hours
    $regular_hours = floor($regular_seconds / 3600);
    $regular_minutes = floor(($regular_seconds % 3600) / 60);
    $regular_seconds = $regular_seconds % 60;
    
    // Format overtime
    $overtime_hours = floor($overtime_seconds / 3600);
    $overtime_minutes = floor(($overtime_seconds % 3600) / 60);
    $overtime_seconds = $overtime_seconds % 60;
    
    return [
        'total_time' => sprintf("%02d:%02d:%02d", 
            floor($total_seconds / 3600),
            floor(($total_seconds % 3600) / 60),
            $total_seconds % 60
        ),
        'regular_time' => sprintf("%02d:%02d:%02d", 
            $regular_hours, 
            $regular_minutes, 
            $regular_seconds
        ),
        'overtime' => sprintf("%02d:%02d:%02d", 
            $overtime_hours, 
            $overtime_minutes, 
            $overtime_seconds
        ),
        'has_overtime' => $overtime_seconds > 0
    ];
}

// Function to get user's current shift and weekly offs
function getUserShiftDetails($conn, $user_id) {
    $current_date = date('Y-m-d');
    
    $query = "SELECT us.*, s.shift_name, s.start_time, s.end_time 
              FROM user_shifts us
              JOIN shifts s ON us.shift_id = s.id
              WHERE us.user_id = ? 
              AND us.effective_from <= ?
              AND (us.effective_to >= ? OR us.effective_to IS NULL)
              ORDER BY us.effective_from DESC 
              LIMIT 1";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $current_date, $current_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $shift_data = $result->fetch_assoc();
        return [
            'shift_name' => $shift_data['shift_name'],
            'start_time' => $shift_data['start_time'],
            'end_time' => $shift_data['end_time'],
            'weekly_offs' => $shift_data['weekly_offs']
        ];
    }
    
    // Return default shift if no specific shift is assigned
    return [
        'shift_name' => 'Default Shift',
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'weekly_offs' => 'Saturday,Sunday'
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $_SESSION['user_id'];
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    $ip_address = getUserIP();
    $device_info = getDeviceInfo();
    $status = 'present';
    $created_at = date('Y-m-d H:i:s');
    
    // Get user's shift details
    $shift_details = getUserShiftDetails($conn, $user_id);
    
    try {
        if ($data['action'] === 'punch_in') {
            // Check if it's a weekly off
            $current_day = date('l'); // Gets the current day name
            $weekly_offs = explode(',', $shift_details['weekly_offs']);
            
            // Set is_weekly_off flag
            $is_weekly_off = in_array($current_day, $weekly_offs) ? 1 : 0;
            
            // Check if already punched in today
            $check_query = "SELECT id FROM attendance WHERE user_id = ? AND date = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("is", $user_id, $current_date);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Already punched in for today']);
                exit;
            }

            // Insert new attendance record
            $query = "INSERT INTO attendance (
                user_id, 
                date, 
                punch_in, 
                location, 
                ip_address, 
                device_info, 
                status, 
                created_at, 
                shift_time, 
                weekly_offs, 
                auto_punch_out,
                is_weekly_off
            ) VALUES (
                ?, 
                ?, 
                TIME(?), 
                ?, 
                ?, 
                ?, 
                ?, 
                ?, 
                ?, 
                ?, 
                TIME(?),
                ?
            )";
            
            $stmt = $conn->prepare($query);
            $location = "";
            $shift_time = $shift_details['start_time'] . '-' . $shift_details['end_time'];
            $weekly_offs = $shift_details['weekly_offs'];
            $auto_punch_out = $shift_details['end_time'];
            
            $stmt->bind_param("issssssssssi", 
                $user_id, 
                $current_date, 
                $current_time,
                $location, 
                $ip_address, 
                $device_info, 
                $status, 
                $created_at,
                $shift_time,
                $weekly_offs,
                $auto_punch_out,
                $is_weekly_off
            );
            
            if ($stmt->execute()) {
                $message = 'Punched in successfully at ' . date('h:i A', strtotime($current_time));
                if ($is_weekly_off) {
                    $message .= ' (Working on Weekly Off)';
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => $message,
                    'shift_time' => "Shift: " . $shift_details['shift_name'] . " (" . 
                                  date('h:i A', strtotime($shift_details['start_time'])) . 
                                  " - " . date('h:i A', strtotime($shift_details['end_time'])) . ")"
                ]);
            } else {
                throw new Exception("Failed to punch in. Error: " . $stmt->error);
            }
        } elseif ($data['action'] === 'punch_out') {
            // Validate work report
            if (!isset($data['work_report']) || empty(trim($data['work_report']))) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Work report is required for punch out'
                ]);
                exit;
            }

            // First check if already auto punched out
            $check_auto = "SELECT id, punch_out, auto_punch_out 
                           FROM attendance 
                           WHERE user_id = ? AND date = ?";
            $stmt_check = $conn->prepare($check_auto);
            $stmt_check->bind_param("is", $user_id, $current_date);
            $stmt_check->execute();
            $auto_result = $stmt_check->get_result();
            $attendance = $auto_result->fetch_assoc();

            if ($attendance && $attendance['auto_punch_out'] == 1) {
                echo json_encode([
                    'success' => false,
                    'message' => 'System has already recorded your punch out at shift end time due to missing punch out.',
                    'auto_punch_out' => true,
                    'punch_out_time' => date('h:i A', strtotime($attendance['punch_out']))
                ]);
                exit;
            }

            // Get punch in time and shift details
            $get_details = "SELECT a.punch_in, s.end_time 
                            FROM attendance a 
                            JOIN user_shifts us ON a.user_id = us.user_id 
                            JOIN shifts s ON us.shift_id = s.id 
                            WHERE a.user_id = ? AND a.date = ? AND a.punch_out IS NULL";
            $stmt_details = $conn->prepare($get_details);
            $stmt_details->bind_param("is", $user_id, $current_date);
            $stmt_details->execute();
            $result = $stmt_details->get_result();
            $row = $result->fetch_assoc();
            
            if (!$row) {
                throw new Exception("No punch-in record found for today");
            }
            
            $punch_in_time = $row['punch_in'];
            $shift_end_time = $row['end_time'];
            
            // Calculate working hours and overtime
            $time_details = calculateWorkingHours($punch_in_time, $current_time, $shift_end_time);
            
            // Update attendance record with punch out time, working hours, overtime and work report
            $query = "UPDATE attendance SET 
                punch_out = TIME(?),
                working_hours = ?,
                overtime_hours = ?,
                work_report = ?,
                modified_at = ?,
                modified_by = ?
                WHERE user_id = ? AND date = ? AND punch_out IS NULL";
            
            $stmt = $conn->prepare($query);
            $modified_at = date('Y-m-d H:i:s');
            $work_report = trim($data['work_report']);
            
            $stmt->bind_param("ssssssss", 
                $current_time,
                $time_details['total_time'],
                $time_details['overtime'],
                $work_report,
                $modified_at,
                $user_id,
                $user_id, 
                $current_date
            );
            
            if ($stmt->execute()) {
                // Format response message
                $message = 'Punched out successfully at ' . date('h:i A', strtotime($current_time));
                $time_message = sprintf(
                    "Regular hours: %s\n%s",
                    $time_details['regular_time'],
                    $time_details['has_overtime'] ? "Overtime: " . $time_details['overtime'] : ""
                );
                
                echo json_encode([
                    'success' => true, 
                    'message' => $message,
                    'working_hours' => $time_message,
                    'has_overtime' => $time_details['has_overtime'],
                    'work_report' => $work_report
                ]);
            } else {
                throw new Exception("Failed to punch out. Error: " . $stmt->error);
            }
        }
        
    } catch (Exception $e) {
        error_log("Punch Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?> 