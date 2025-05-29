<?php
// Include database connection
require_once '../config/db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Get current date in Y-m-d format
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

try {
    // Query to get all site supervisors who have punched in today
    $query = "
        SELECT 
            u.id,
            u.username AS name,
            u.department,
            u.profile_picture,
            u.designation,
            u.phone,
            u.email,
            u.role,
            a.punch_in AS check_in_time,
            a.address AS location,
            a.status,
            e.title AS site_name
        FROM 
            users u
        JOIN 
            attendance a ON u.id = a.user_id
        LEFT JOIN 
            sv_calendar_events e ON e.created_by = u.id
        WHERE 
            u.role = 'Site Supervisor' 
            AND a.date = :today
            AND a.punch_in IS NOT NULL
        GROUP BY 
            u.id
        ORDER BY 
            a.punch_in ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':today' => $today]);
    
    $supervisors = [];
    
    while ($row = $stmt->fetch()) {
        // Determine status based on attendance status
        $status = 'active';
        if ($row['status'] == 'on_break') {
            $status = 'break';
        } elseif ($row['status'] == 'in_meeting') {
            $status = 'meeting';
        } elseif ($row['status'] == 'out') {
            $status = 'out';
        }
        
        // Generate avatar text from name
        $nameParts = explode(' ', $row['name']);
        $avatar = '';
        if (count($nameParts) >= 2) {
            $avatar = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
        } else {
            $avatar = strtoupper(substr($row['name'], 0, 2));
        }
        
        // Generate random color based on user ID for consistency
        $colors = ['4CAF50', '2196F3', 'FF9800', '9C27B0', 'E91E63', '795548', '009688', '673AB7'];
        $colorIndex = $row['id'] % count($colors);
        $color = $colors[$colorIndex];
        
        // Format check-in time
        $checkInTime = date('h:i A', strtotime($row['check_in_time']));
        
        // Add to supervisors array
        $supervisors[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'department' => $row['department'] ?? $row['role'],
            'designation' => $row['designation'] ?? 'Site Supervisor',
            'present' => true,
            'avatar' => $avatar,
            'color' => $color,
            'phone' => $row['phone'] ?? 'N/A',
            'email' => $row['email'] ?? 'N/A',
            'site' => $row['site_name'] ?? 'Assigned Site',
            'checkInTime' => $checkInTime,
            'status' => $status,
            'location' => $row['location'] ?? 'On Site',
            'role' => $row['role']
        ];
    }
    
    // Count of total supervisors
    $totalQuery = "SELECT COUNT(*) as total FROM users WHERE role = 'Site Supervisor'";
    $totalStmt = $pdo->query($totalQuery);
    $totalSupervisors = $totalStmt->fetch()['total'];
    
    // Count of supervisors present yesterday
    $yesterdayQuery = "
        SELECT COUNT(DISTINCT u.id) as total 
        FROM users u 
        JOIN attendance a ON u.id = a.user_id 
        WHERE u.role = 'Site Supervisor' 
        AND a.date = :yesterday 
        AND a.punch_in IS NOT NULL
    ";
    $yesterdayStmt = $pdo->prepare($yesterdayQuery);
    $yesterdayStmt->execute([':yesterday' => $yesterday]);
    $yesterdaySupervisors = $yesterdayStmt->fetch()['total'];
    
    // Calculate trend
    $trend = count($supervisors) - $yesterdaySupervisors;
    
    // Return response
    echo json_encode([
        'status' => 'success',
        'total_supervisors' => (int)$totalSupervisors,
        'present_supervisors' => count($supervisors),
        'yesterday_supervisors' => (int)$yesterdaySupervisors,
        'trend' => $trend,
        'supervisors' => $supervisors
    ]);
    
} catch (PDOException $e) {
    // Log error
    error_log("Error fetching supervisors: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch supervisors',
        'error' => $e->getMessage()
    ]);
}
?> 