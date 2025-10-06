<?php
/**
 * Get missing punches for the last 15 days including today
 * This script fetches attendance records where either punch_in or punch_out is missing
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db_connect.php'; // Adjust path as needed

header('Content-Type: application/json');

// Debug: Log session data
error_log("Session status: " . session_status());
error_log("Session ID: " . session_id());
error_log("User ID in session: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in', 'session_id' => session_id()]);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = $pdo; // Use PDO connection from db_connect.php

// Function to get user's weekly off days for a specific date
function getUserWeeklyOffs($conn, $user_id, $date) {
    try {
        $query = "
            SELECT us.weekly_offs 
            FROM user_shifts us
            JOIN shifts s ON us.shift_id = s.id
            WHERE us.user_id = ? 
            AND us.effective_from <= ?
            AND (us.effective_to IS NULL OR us.effective_to >= ?)
            ORDER BY us.effective_from DESC 
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id, $date, $date]);
        $result = $stmt->fetch();
        
        if ($result && !empty($result['weekly_offs'])) {
            return explode(',', $result['weekly_offs']);
        }
        
        // Return default weekly offs if none found
        return ['Saturday', 'Sunday'];
    } catch (Exception $e) {
        // Return default weekly offs in case of error
        return ['Saturday', 'Sunday'];
    }
}

// Function to check if a date is a weekly off day for the user
function isWeeklyOffDay($conn, $user_id, $date) {
    $weeklyOffs = getUserWeeklyOffs($conn, $user_id, $date);
    $dayOfWeek = date('l', strtotime($date)); // Get day name (e.g., "Monday")
    
    return in_array($dayOfWeek, $weeklyOffs);
}

// Function to check if a date is an office holiday
function isOfficeHoliday($conn, $date) {
    try {
        // First check if the office_holidays table exists
        $tableCheckQuery = "SHOW TABLES LIKE 'office_holidays'";
        $tableCheckStmt = $conn->prepare($tableCheckQuery);
        $tableCheckStmt->execute();
        
        // If table doesn't exist, no holidays
        if ($tableCheckStmt->rowCount() == 0) {
            return false;
        }
        
        // Check if the date is a holiday
        $query = "SELECT COUNT(*) as is_holiday FROM office_holidays WHERE holiday_date = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$date]);
        $result = $stmt->fetch();
        
        return $result && $result['is_holiday'] > 0;
    } catch (Exception $e) {
        // In case of any error, assume it's not a holiday
        error_log("Error checking office holiday: " . $e->getMessage());
        return false;
    }
}

// Function to check if a date is a leave day for the user
function isLeaveDay($conn, $user_id, $date) {
    try {
        // First check if the leave_request table exists
        $tableCheckQuery = "SHOW TABLES LIKE 'leave_request'";
        $tableCheckStmt = $conn->prepare($tableCheckQuery);
        $tableCheckStmt->execute();
        
        // If table doesn't exist, no leaves
        if ($tableCheckStmt->rowCount() == 0) {
            return false;
        }
        
        // Check if the date falls within any approved leave request for the user
        $query = "
            SELECT COUNT(*) as is_leave 
            FROM leave_request 
            WHERE user_id = ? 
            AND status = 'approved' 
            AND ? BETWEEN start_date AND end_date
        ";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user_id, $date]);
        $result = $stmt->fetch();
        
        return $result && $result['is_leave'] > 0;
    } catch (Exception $e) {
        // In case of any error, assume it's not a leave day
        error_log("Error checking leave day: " . $e->getMessage());
        return false;
    }
}

// Function to check if a date should be excluded (weekly off, office holiday, or leave day)
function shouldExcludeDate($conn, $user_id, $date) {
    return isWeeklyOffDay($conn, $user_id, $date) || 
           isOfficeHoliday($conn, $date) || 
           isLeaveDay($conn, $user_id, $date);
}

try {
    // Calculate date 15 days ago (including today)
    $date_15_days_ago = date('Y-m-d', strtotime('-15 days'));
    $today = date('Y-m-d');
    
    // Generate all dates in the last 15 days (excluding weekly offs, office holidays, and leave days)
    $dates = [];
    $current_date = new DateTime($date_15_days_ago);
    $end_date = new DateTime($today);
    
    while ($current_date <= $end_date) {
        $date_str = $current_date->format('Y-m-d');
        // Only add dates that are NOT excluded days
        if (!shouldExcludeDate($conn, $user_id, $date_str)) {
            $dates[] = $date_str;
        }
        $current_date->modify('+1 day');
    }
    
    // Fetch all attendance records for the last 15 days
    $query = "
        SELECT 
            id,
            user_id,
            date,
            punch_in,
            punch_out,
            approval_status,
            created_at
        FROM attendance 
        WHERE user_id = ? 
        AND date >= ?
        ORDER BY date DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id, $date_15_days_ago]);
    $attendance_records = $stmt->fetchAll();
    
    // Create a map of dates to attendance records (excluding non-working days)
    $attendance_map = [];
    foreach ($attendance_records as $record) {
        // Only include records for non-excluded days
        if (!shouldExcludeDate($conn, $user_id, $record['date'])) {
            $attendance_map[$record['date']] = $record;
        }
    }
    
    // Find missing or incomplete punches (only for non-excluded days)
    $missing_punches = [];
    
    foreach ($dates as $date) {
        if (isset($attendance_map[$date])) {
            // Record exists, check if it's incomplete
            $record = $attendance_map[$date];
            if ($record['punch_in'] === null || $record['punch_out'] === null) {
                $missing_punches[] = $record;
            }
        } else {
            // No record for this date, it's a completely missed day
            $missing_punches[] = [
                'id' => 0,
                'user_id' => $user_id,
                'date' => $date,
                'punch_in' => null,
                'punch_out' => null,
                'approval_status' => null,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    // NOTE: We no longer automatically mark notifications as read here
    // The read status will be managed separately to allow "submitted" status to show
    
    echo json_encode([
        'success' => true,
        'data' => $missing_punches,
        'count' => count($missing_punches)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching data: ' . $e->getMessage()]);
}
?>