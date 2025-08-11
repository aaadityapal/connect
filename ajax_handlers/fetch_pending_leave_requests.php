<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../config/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if the request is authorized
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Check if user has Senior Manager (Site) role
$user_id = $_SESSION['user_id'];
$role_query = "SELECT role FROM users WHERE id = ?";
$role_stmt = $conn->prepare($role_query);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result();

if ($role_result && $row = $role_result->fetch_assoc()) {
    $user_role = $row['role'];
    if ($user_role !== 'Senior Manager (Site)') {
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized access. Only Senior Manager (Site) can view this information.'
        ]);
        exit;
    }
} else {
    echo json_encode(['error' => 'Failed to verify user role']);
    exit;
}

try {
    // Get status filter from request
    $status = isset($_GET['status']) ? $_GET['status'] : 'pending';
    
    // Get month and year filters if provided
    $month = isset($_GET['month']) ? intval($_GET['month']) : null;
    $year = isset($_GET['year']) ? intval($_GET['year']) : null;
    
    // Build WHERE clause based on filters
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    // Status condition
    if ($status !== 'all') {
        $where_conditions[] = "lr.status = ?";
        $params[] = $status;
        $param_types .= 's';
    } else {
        $where_conditions[] = "lr.status IN ('pending', 'approved', 'rejected')";
    }
    
    // Month and year condition
    if ($month && $year) {
        // Filter by month and year for start_date or end_date
        $where_conditions[] = "(MONTH(lr.start_date) = ? OR MONTH(lr.end_date) = ?) AND (YEAR(lr.start_date) = ? OR YEAR(lr.end_date) = ?)";
        $params[] = $month;
        $params[] = $month;
        $params[] = $year;
        $params[] = $year;
        $param_types .= 'iiii';
    }
    
    // Combine conditions
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    // Query to get leave requests based on status filter
    $query = "
        SELECT lr.id, lr.user_id, lr.leave_type, lr.start_date, lr.end_date, 
               lr.duration_type, lr.half_day_type, lr.reason, lr.status,
               lr.created_at, lr.time_from, lr.time_to, lr.comp_off_source_date,
               u.username, lt.name as leave_type_name, lt.color_code
        FROM leave_request lr
        JOIN users u ON lr.user_id = u.id
        LEFT JOIN leave_types lt ON lr.leave_type = lt.id
        $where_clause
        ORDER BY lr.created_at DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($query);
    
    // Bind parameters if needed
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pending_requests = [];
    while ($row = $result->fetch_assoc()) {
        // Format the leave duration information
        $leave_duration = "";
        if ($row['start_date'] == $row['end_date']) {
            // Get weekday name for single day
            $weekday = date('D', strtotime($row['start_date']));
            
            if ($row['duration_type'] == 'half_day') {
                $leave_duration = "Half day (" . ucfirst($row['half_day_type']) . ") - " . $weekday . ", " . date('d M Y', strtotime($row['start_date']));
            } elseif ($row['leave_type'] == '11' || strtolower($row['leave_type_name']) == 'short') { // Short leave (ID 11 based on the image)
                // For short leaves, include the time information
                $time_from = !empty($row['time_from']) ? date('h:i A', strtotime($row['time_from'])) : 'N/A';
                $time_to = !empty($row['time_to']) ? date('h:i A', strtotime($row['time_to'])) : 'N/A';
                $leave_duration = "Short leave (" . $time_from . " - " . $time_to . ") - " . $weekday . ", " . date('d M Y', strtotime($row['start_date']));
            } else {
                $leave_duration = "Full day - " . $weekday . ", " . date('d M Y', strtotime($row['start_date']));
            }
        } else {
            // Calculate days between
            $start = new DateTime($row['start_date']);
            $end = new DateTime($row['end_date']);
            $interval = $start->diff($end);
            $days = $interval->days + 1;
            
            // Get weekday names
            $start_weekday = date('D', strtotime($row['start_date']));
            $end_weekday = date('D', strtotime($row['end_date']));
            
            $leave_duration = $days . " days";
            $leave_duration .= " (" . $start_weekday . ", " . date('d M', strtotime($row['start_date'])) . " - " . $end_weekday . ", " . date('d M', strtotime($row['end_date'])) . ")";
        }
        
        // Format created date with weekday name
        $created_date = date('D, d M Y, h:i A', strtotime($row['created_at']));
        
        // Format time values if they exist
        $time_from = !empty($row['time_from']) ? date('h:i A', strtotime($row['time_from'])) : null;
        $time_to = !empty($row['time_to']) ? date('h:i A', strtotime($row['time_to'])) : null;
        
        // Format comp_off_source_date if exists
        $comp_off_date = null;
        if (!empty($row['comp_off_source_date'])) {
            $comp_off_date = date('D, d M Y', strtotime($row['comp_off_source_date'])); // Add weekday name
        }
        
        // Format start and end dates with weekday names
        $formatted_start_date = date('D, d M Y', strtotime($row['start_date']));
        $formatted_end_date = date('D, d M Y', strtotime($row['end_date']));
        
        $pending_requests[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'name' => $row['username'],
            'leave_type' => $row['leave_type'],
            'leave_type_name' => $row['leave_type_name'] ?? ucfirst($row['leave_type']),
            'color_code' => $row['color_code'] ?? '#607D8B', // Default to blue-grey if no color code
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'formatted_start_date' => $formatted_start_date,
            'formatted_end_date' => $formatted_end_date,
            'duration' => $leave_duration,
            'reason' => $row['reason'],
            'created_at' => $created_date,
            'time_from' => $time_from,
            'time_to' => $time_to,
            'is_short_leave' => ($row['leave_type'] == '11' || strtolower($row['leave_type_name'] ?? '') == 'short'),
            'duration_type' => $row['duration_type'],
            'half_day_type' => $row['half_day_type'],
            'is_half_day' => ($row['duration_type'] == 'half_day'),
            'has_half_day_info' => !empty($row['half_day_type']),
            'comp_off_source_date' => $comp_off_date,
            'is_compensate_leave' => ($row['leave_type'] == '12' || strtolower($row['leave_type_name'] ?? '') == 'compensate'),
            'status' => $row['status']
        ];
    }
    
    // Get total count of requests based on all filters
    // Convert the main WHERE clause to work with the count query
    $count_where_clause = str_replace("lr.", "", $where_clause);
    
    $count_query = "
        SELECT COUNT(*) as total
        FROM leave_request
        $count_where_clause
    ";
    
    $count_stmt = $conn->prepare($count_query);
    
    // Bind parameters if needed
    if (!empty($params)) {
        $count_stmt->bind_param($param_types, ...$params);
    }
    
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_requests = 0;
    
    if ($count_result && $count_row = $count_result->fetch_assoc()) {
        $total_requests = $count_row['total'];
    }
    
    // Return the data
    echo json_encode([
        'success' => true,
        'leave_requests' => $pending_requests, // Renamed for clarity since it can be any status now
        'total_requests' => $total_requests,
        'status' => $status,
        'month' => $month,
        'year' => $year
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
