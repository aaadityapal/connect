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

try {
    // Get current date in Y-m-d format
    $today = date('Y-m-d');
    
    // Query to get site supervisors who are on approved leave today
    $query = "
        SELECT lr.id, lr.user_id, lr.leave_type, lr.start_date, lr.end_date, 
               lr.duration_type, lr.half_day_type, u.username
        FROM leave_request lr
        JOIN users u ON lr.user_id = u.id
        WHERE u.role = 'Site Supervisor'
        AND lr.status = 'approved'
        AND (
            (lr.start_date <= ? AND lr.end_date >= ?) OR
            (lr.start_date = ? AND lr.duration_type = 'half_day')
        )
        ORDER BY lr.start_date ASC, u.username ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $today, $today, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $supervisors_on_leave = [];
    while ($row = $result->fetch_assoc()) {
        // Format the leave duration information
        $leave_duration = "";
        if ($row['start_date'] == $row['end_date']) {
            if ($row['duration_type'] == 'half_day') {
                $leave_duration = "Half day (" . ucfirst($row['half_day_type']) . ")";
            } else {
                $leave_duration = "Full day";
            }
        } else {
            // Calculate days between
            $start = new DateTime($row['start_date']);
            $end = new DateTime($row['end_date']);
            $interval = $start->diff($end);
            $days = $interval->days + 1;
            
            $leave_duration = $days . " days";
            $leave_duration .= " (" . date('d M', strtotime($row['start_date'])) . " - " . date('d M', strtotime($row['end_date'])) . ")";
        }
        
        // No profile image in this table structure, use default
        $profile_image = 'assets/default-avatar.png';
        
        $supervisors_on_leave[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'name' => $row['username'],
            'leave_type' => $row['leave_type'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'duration' => $leave_duration,
            'profile_image' => $profile_image
        ];
    }
    
    // Get total number of site supervisors
    $total_query = "
        SELECT COUNT(DISTINCT u.id) as total
        FROM users u
        WHERE u.role = 'Site Supervisor'
    ";
    
    $total_stmt = $conn->prepare($total_query);
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
    $total_row = $total_result->fetch_assoc();
    $total_supervisors = $total_row['total'];
    
    // Create response data
    $response_data = [
        'success' => true,
        'supervisors_on_leave' => $supervisors_on_leave,
        'total_supervisors' => $total_supervisors,
        'count_on_leave' => count($supervisors_on_leave)
    ];
    
    // Return the data
    echo json_encode($response_data);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}