<?php
require_once 'config/db_connect.php';
require_once 'includes/auth_check.php';
require_once 'includes/role_check.php';

// Check if user has required role
checkUserRole(['admin', 'manager', 'senior manager (site)', 'senior manager (studio)', 'hr']);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if attendance ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $response['message'] = 'Attendance ID is required';
    echo json_encode($response);
    exit;
}

$attendance_id = intval($_GET['id']);

try {
    // Prepare the SQL query to fetch attendance details with user and geofence information
    $sql = "SELECT 
                a.*,
                u.username, 
                u.unique_id,
                u.designation,
                u.role,
                u.reporting_manager,
                g.name as geofence_name,
                g.address as geofence_address,
                g.radius as geofence_radius,
                g.latitude as geofence_latitude,
                g.longitude as geofence_longitude
            FROM attendance a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN geofence_locations g ON a.geofence_id = g.id
            WHERE a.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$attendance_id]);
    
    if ($stmt->rowCount() > 0) {
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Format dates and times for better readability
        if (!empty($attendance['punch_in'])) {
            $attendance['formatted_punch_in'] = date('h:i A', strtotime($attendance['punch_in']));
        } else {
            $attendance['formatted_punch_in'] = 'N/A';
        }
        
        if (!empty($attendance['punch_out'])) {
            $attendance['formatted_punch_out'] = date('h:i A', strtotime($attendance['punch_out']));
        } else {
            $attendance['formatted_punch_out'] = 'N/A';
        }
        
        // Format date
        if (!empty($attendance['date'])) {
            $attendance['formatted_date'] = date('d M Y', strtotime($attendance['date']));
        } else {
            $attendance['formatted_date'] = 'N/A';
        }
        
        // Determine geofence status for punch in
        // For punch-in, we need to check if punch_in_outside_reason exists
        // If it exists, the user was outside geofence, otherwise they were inside
        if (!empty($attendance['punch_in_outside_reason'])) {
            $attendance['punch_in_geofence_status'] = 'Outside Geofence';
        } else {
            $attendance['punch_in_geofence_status'] = 'Within Geofence';
        }
            
        // Determine geofence status for punch out
        // For punch-out, we need to check if punch_out_outside_reason exists
        // If it exists, the user was outside geofence, otherwise they were inside
        if (!empty($attendance['punch_out_outside_reason'])) {
            $attendance['punch_out_geofence_status'] = 'Outside Geofence';
        } else {
            $attendance['punch_out_geofence_status'] = 'Within Geofence';
        }
        
        $response['success'] = true;
        $response['data'] = $attendance;
    } else {
        $response['message'] = 'Attendance record not found';
    }
} catch (Exception $e) {
    $response['message'] = 'Error fetching attendance details: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;