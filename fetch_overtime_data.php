<?php
session_start();
require_once 'config/db_connect.php';
require_once 'includes/functions/shift_functions.php';

header('Content-Type: application/json');

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// Get filter parameters
$filter_user = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n') - 1; // 0-11
$filter_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$filter_location = isset($_GET['location']) ? $_GET['location'] : 'studio'; // Default to studio

try {
    // Fetch overtime data
    $overtime_data = getOvertimeData($pdo, $filter_user, $filter_status, $filter_month, $filter_year, $filter_location);
    
    // Calculate statistics
    $statistics = calculateOvertimeStatistics($overtime_data, $pdo, $filter_user, $filter_status, $filter_month, $filter_year, $filter_location);
    
    echo json_encode([
        'success' => true,
        'data' => $overtime_data,
        'statistics' => $statistics
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to fetch data: ' . $e->getMessage()]);
}

/**
 * Calculate overtime statistics
 */
function calculateOvertimeStatistics($overtime_data, $pdo, $filter_user, $filter_status, $month, $year, $location) {
    try {
        // Calculate the first and last day of the selected month
        $first_day = sprintf('%04d-%02d-01', $year, $month + 1);
        $last_day = date('Y-m-t', strtotime($first_day));
        
        // Build query based on filters
        $where_conditions = [];
        $params = [];
        
        // Add date range filter
        $where_conditions[] = "a.date BETWEEN ? AND ?";
        $params[] = $first_day;
        $params[] = $last_day;
        
        // Add user filter if specified
        if ($filter_user > 0) {
            $where_conditions[] = "a.user_id = ?";
            $params[] = $filter_user;
        }
        
        // Add status filter if specified
        // Special handling for "expired" status since it's computed, not stored
        if (!empty($filter_status)) {
            if (strtolower($filter_status) === 'expired') {
                // For expired status, we need to filter for pending records that are 15+ days old
                $where_conditions[] = "a.overtime_status = 'pending' AND DATEDIFF(NOW(), a.date) >= 15";
            } else {
                $where_conditions[] = "a.overtime_status = ?";
                $params[] = $filter_status;
            }
        }
        
        // Add location filter based on roles
        if ($location === 'studio') {
            // For studio, exclude specific roles
            $where_conditions[] = "u.role NOT IN ('Site Supervisor', 'Site Coordinator', 'Sales', 'Graphic Designer', 'Social Media Marketing', 'Purchase Manager')";
        } else if ($location === 'site') {
            // For site, only include specific roles
            $where_conditions[] = "u.role IN ('Site Supervisor', 'Site Coordinator', 'Sales', 'Graphic Designer', 'Social Media Marketing', 'Purchase Manager')";
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        // Query to fetch statistics
        // For records after October 2025, use overtime_requests table for all status counts
        // For records before October 2025, use attendance table
        $query = "SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE 
                        WHEN a.date >= '2025-10-01' AND oreq.status = 'submitted' THEN 1
                        WHEN a.date < '2025-10-01' AND a.overtime_status = 'submitted' THEN 1
                        ELSE 0
                    END) as pending_count,
                    SUM(CASE 
                        WHEN a.date >= '2025-10-01' AND oreq.status = 'approved' THEN oreq.overtime_hours
                        WHEN a.date < '2025-10-01' AND a.overtime_status = 'approved' THEN a.overtime_hours
                        ELSE 0
                    END) as approved_hours,
                    SUM(CASE 
                        WHEN a.date >= '2025-10-01' AND oreq.status = 'approved' THEN 1
                        WHEN a.date < '2025-10-01' AND a.overtime_status = 'approved' THEN 1
                        ELSE 0
                    END) as approved_count,
                    SUM(CASE 
                        WHEN a.date >= '2025-10-01' AND oreq.status = 'rejected' THEN 1
                        WHEN a.date < '2025-10-01' AND a.overtime_status = 'rejected' THEN 1
                        ELSE 0
                    END) as rejected_count,
                    SUM(CASE 
                        WHEN a.date >= '2025-10-01' AND oreq.status = 'pending' AND DATEDIFF(NOW(), a.date) >= 15 THEN 1
                        WHEN a.date < '2025-10-01' AND a.overtime_status = 'pending' AND DATEDIFF(NOW(), a.date) >= 15 THEN 1
                        ELSE 0
                    END) as expired_count
                  FROM attendance a
                  JOIN users u ON a.user_id = u.id
                  LEFT JOIN overtime_requests oreq ON a.id = oreq.attendance_id
                  $where_clause";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $pending_requests = $stats['pending_count'] ?? 0;
        $approved_hours = floatval($stats['approved_hours'] ?? 0);
        $approved_requests = $stats['approved_count'] ?? 0;
        $rejected_requests = $stats['rejected_count'] ?? 0;
        $expired_requests = $stats['expired_count'] ?? 0;
        
        // Calculate estimated cost based on approved hours (assuming $15/hour rate)
        $hourly_rate = 15;
        $estimated_cost = $approved_hours * $hourly_rate;
        
        return [
            'pending_requests' => $pending_requests,
            'approved_hours' => round($approved_hours, 1),
            'rejected_requests' => $rejected_requests,
            'approved_count' => $approved_requests,
            'expired_requests' => $expired_requests,
            'estimated_cost' => round($estimated_cost, 2)
        ];
    } catch (Exception $e) {
        error_log("Error calculating overtime statistics: " . $e->getMessage());
        return [
            'pending_requests' => 0,
            'approved_hours' => 0,
            'rejected_requests' => 0,
            'approved_count' => 0,
            'expired_requests' => 0,
            'estimated_cost' => 0
        ];
    }
}

/**
 * Get overtime data for a user based on month/year filters
 */
function getOvertimeData($pdo, $filter_user, $filter_status, $month, $year, $location) {
    try {
        // Calculate the first and last day of the selected month
        $first_day = sprintf('%04d-%02d-01', $year, $month + 1);
        $last_day = date('Y-m-t', strtotime($first_day));
        
        // Build query based on filters
        $where_conditions = [];
        $params = [];
        
        // Add date range filter
        $where_conditions[] = "a.date BETWEEN ? AND ?";
        $params[] = $first_day;
        $params[] = $last_day;
        
        // Add user filter if specified
        if ($filter_user > 0) {
            $where_conditions[] = "a.user_id = ?";
            $params[] = $filter_user;
        }
        
        // Add status filter if specified
        // Special handling for "expired" status since it's computed, not stored
        if (!empty($filter_status)) {
            if (strtolower($filter_status) === 'expired') {
                // For expired status, we need to filter for pending records that are 15+ days old
                $where_conditions[] = "a.overtime_status = 'pending' AND DATEDIFF(NOW(), a.date) >= 15";
            } else {
                $where_conditions[] = "a.overtime_status = ?";
                $params[] = $filter_status;
            }
        }
        
        // Add location filter based on roles
        if ($location === 'studio') {
            // For studio, exclude specific roles
            $where_conditions[] = "u.role NOT IN ('Site Supervisor', 'Site Coordinator', 'Sales', 'Graphic Designer', 'Social Media Marketing', 'Purchase Manager')";
        } else if ($location === 'site') {
            // For site, only include specific roles
            $where_conditions[] = "u.role IN ('Site Supervisor', 'Site Coordinator', 'Sales', 'Graphic Designer', 'Social Media Marketing', 'Purchase Manager')";
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        // Query to fetch overtime data with user information
        // Calculate overtime in 30-minute increments, only showing records with at least 1.5 hours
        // Also fetch overtime report based on date condition:
        // - Before November 2025: from overtime_notifications.message
        // - From November 2025 onwards: from overtime_requests.overtime_description
        $query = "SELECT 
                    a.id as attendance_id,
                    u.username,
                    u.role,
                    a.date,
                    a.punch_out,
                    a.overtime_hours,
                    a.work_report,
                    a.overtime_status,
                    s.end_time as shift_end_time,
                    CASE 
                        WHEN a.punch_out IS NULL OR s.end_time IS NULL THEN 0
                        WHEN TIME(a.punch_out) <= TIME(s.end_time) THEN 0
                        ELSE TIMESTAMPDIFF(SECOND, 
                            STR_TO_DATE(CONCAT(a.date, ' ', s.end_time), '%Y-%m-%d %H:%i:%s'),
                            STR_TO_DATE(CONCAT(a.date, ' ', a.punch_out), '%Y-%m-%d %H:%i:%s')
                        )
                    END as overtime_seconds,
                    CASE 
                        WHEN a.date < '2025-10-01' THEN 
                            COALESCE(onot.message, 'System deployment and testing')
                        ELSE 
                            COALESCE(oreq.overtime_description, 'Generated automatically')
                    END as overtime_report,
                    oreq.overtime_hours as submitted_ot_hours
                  FROM attendance a
                  JOIN users u ON a.user_id = u.id
                  LEFT JOIN user_shifts us ON u.id = us.user_id AND a.date BETWEEN us.effective_from AND COALESCE(us.effective_to, '9999-12-31')
                  LEFT JOIN shifts s ON us.shift_id = s.id
                  LEFT JOIN overtime_requests oreq ON a.id = oreq.attendance_id
                  LEFT JOIN overtime_notifications onot ON a.id = onot.overtime_id
                  $where_clause
                  HAVING overtime_seconds >= 5400 /* Only include records with at least 1.5 hours of overtime */
                  ORDER BY a.date DESC
                  LIMIT 50"; /* Limit to 50 records for performance */
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        $data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Calculate overtime hours using proper rounding logic
            $overtime_seconds = intval($row['overtime_seconds']);
            $overtime_minutes = $overtime_seconds / 60;
            
            // Apply proper rounding: minimum 1.5 hours for positive overtime
            // If overtime is 0 or negative, return 0
            if ($overtime_seconds <= 0) {
                $overtime_hours = 0;
            } else {
                $overtime_hours = roundOvertimeHours($overtime_minutes);
            }
            
            // Determine the correct status to display
            $status = ucfirst($row['overtime_status'] ?? 'pending');
            $date = new DateTime($row['date']);
            $oct2025 = new DateTime('2025-10-01');
            
            // For records from October 2025 and later, check if there's a corresponding request
            // and use its status if it exists
            if ($date >= $oct2025 && !empty($row['submitted_ot_hours'])) {
                // Check if there's a corresponding record in overtime_requests table
                $check_request_query = "SELECT status FROM overtime_requests WHERE attendance_id = ? LIMIT 1";
                $check_stmt = $pdo->prepare($check_request_query);
                $check_stmt->execute([$row['attendance_id']]);
                $request_result = $check_stmt->fetch();
                
                if ($request_result) {
                    // Use the status from overtime_requests table
                    $status = ucfirst($request_result['status']);
                } else {
                    // If no request found but submitted_ot_hours exists, it's submitted
                    $status = 'Submitted';
                }
            } else if ($date >= $oct2025 && empty($row['submitted_ot_hours'])) {
                // For records from October 2025 onwards with no submitted hours, it's pending
                $status = 'Pending';
            }
            
            // Check if the record should be marked as expired
            // Only pending records that are 15 days old or more should be marked as expired
            // But only if we're not specifically filtering for expired records
            // (because when filtering for expired, we've already filtered the data)
            if (strtolower($filter_status) !== 'expired' && strtolower($status) === 'pending') {
                $record_date = new DateTime($row['date']);
                $current_date = new DateTime();
                $interval = $current_date->diff($record_date);
                $days_old = $interval->days;
                
                // If the record is 15 days old or more, mark it as expired
                if ($days_old >= 15) {
                    $status = 'Expired';
                }
            }
            
            // If we're filtering for expired records, ensure status is set to Expired
            if (strtolower($filter_status) === 'expired') {
                $status = 'Expired';
            }
            
            // Format the data for the response
            $data[] = [
                'attendance_id' => $row['attendance_id'],
                'username' => $row['username'],
                'role' => $row['role'],
                'date' => $row['date'],
                'shift_end_time' => formatTime($row['shift_end_time']),
                'punch_out_time' => formatTime($row['punch_out']),
                'ot_hours' => number_format($overtime_hours, 1),
                'submitted_ot_hours' => !empty($row['submitted_ot_hours']) ? number_format(floatval($row['submitted_ot_hours']), 1) : 'N/A',
                'work_report' => !empty($row['work_report']) && trim($row['work_report']) !== '' ? $row['work_report'] : 'No work report submitted for this date',
                'overtime_report' => !empty($row['overtime_report']) ? $row['overtime_report'] : 'System deployment and testing',
                'status' => $status
            ];
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("Error fetching overtime data: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculate overtime hours based on shift end time and punch out time
 */
function calculateOvertimeHours($shiftEndTime, $punchOutTime) {
    if (!$shiftEndTime || !$punchOutTime) {
        return '0.0';
    }
    
    // Convert times to seconds
    $shiftEndSeconds = timeToSeconds($shiftEndTime);
    $punchOutSeconds = timeToSeconds($punchOutTime);
    
    // Calculate overtime in seconds
    $overtimeSeconds = 0;
    
    // Only calculate overtime if punch out time is after shift end time
    if ($punchOutSeconds > $shiftEndSeconds) {
        // Same day punch out - calculate overtime as difference
        $overtimeSeconds = $punchOutSeconds - $shiftEndSeconds;
    }
    // If punchOutSeconds <= shiftEndSeconds, overtimeSeconds remains 0
    
    // If no overtime, return 0
    if ($overtimeSeconds <= 0) {
        return '0.0';
    }
    
    // Convert seconds to minutes
    $overtimeMinutes = $overtimeSeconds / 60;
    
    // Apply rounding logic:
    // - If less than 90 minutes (1.5 hours), return 1.5 (minimum threshold)
    // - Otherwise, round down to nearest 30-minute increment
    $roundedHours = roundOvertimeHours($overtimeMinutes);
    
    return number_format($roundedHours, 1, '.', '');
}

/**
 * Round overtime hours according to the specified rules:
 * - If 0 minutes, return 0 (no overtime)
 * - Minimum 1.5 hours for positive overtime
 * - Round down to nearest 30-minute increment
 */
function roundOvertimeHours($minutes) {
    // If no overtime, return 0
    if ($minutes <= 0) {
        return 0;
    }
    
    // If less than 1.5 hours (90 minutes), return 1.5 (minimum threshold)
    if ($minutes < 90) {
        return 1.5;
    }
    
    // For 1.5 hours and above:
    // Round down to the nearest 30-minute increment
    // First, subtract 1.5 hours (90 minutes) from the total
    $adjustedMinutes = $minutes - 90;
    
    // Then round down to nearest 30-minute increment
    $roundedAdjusted = floor($adjustedMinutes / 30) * 30;
    
    // Add back the 1.5 hours base
    $finalMinutes = 90 + $roundedAdjusted;
    
    // Convert back to hours
    $finalHours = $finalMinutes / 60;
    
    return $finalHours;
}

/**
 * Format time for display (HH:MM format)
 */
function formatTime($time) {
    if (!$time) return 'N/A';
    
    // Parse the time
    $timeParts = explode(':', $time);
    if (count($timeParts) < 2) {
        return $time;
    }
    
    $hours = (int)$timeParts[0];
    $minutes = $timeParts[1];
    
    // Determine AM/PM
    $period = ($hours >= 12) ? 'PM' : 'AM';
    
    // Convert hours to 12-hour format
    if ($hours == 0) {
        $hours = 12;
    } else if ($hours > 12) {
        $hours = $hours - 12;
    }
    
    return sprintf('%d:%s %s', $hours, $minutes, $period);
}
?>