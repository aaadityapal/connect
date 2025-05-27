<?php
// Include database connection
require_once '../config/db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if supervisor ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Supervisor ID is required'
    ]);
    exit;
}

$supervisorId = intval($_GET['id']);
$today = date('Y-m-d');

try {
    // Query to get supervisor details
    $query = "
        SELECT 
            u.id,
            u.username AS name,
            u.department,
            u.profile_picture,
            u.designation,
            u.phone,
            u.email,
            u.address,
            u.joining_date,
            u.work_experience,
            u.role,
            a.punch_in AS check_in_time,
            a.address AS location,
            a.status,
            e.title AS site_name
        FROM 
            users u
        LEFT JOIN 
            attendance a ON u.id = a.user_id AND a.date = :today
        LEFT JOIN 
            sv_calendar_events e ON e.created_by = u.id
        WHERE 
            u.id = :id
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':id' => $supervisorId,
        ':today' => $today
    ]);
    
    $supervisor = $stmt->fetch();
    
    if (!$supervisor) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Supervisor not found'
        ]);
        exit;
    }
    
    // Determine status based on attendance status
    $status = 'active';
    if ($supervisor['status'] == 'on_break') {
        $status = 'break';
    } elseif ($supervisor['status'] == 'in_meeting') {
        $status = 'meeting';
    } elseif ($supervisor['status'] == 'out') {
        $status = 'out';
    }
    
    // Generate avatar text from name
    $nameParts = explode(' ', $supervisor['name']);
    $avatar = '';
    if (count($nameParts) >= 2) {
        $avatar = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
    } else {
        $avatar = strtoupper(substr($supervisor['name'], 0, 2));
    }
    
    // Generate color based on user ID for consistency
    $colors = ['4CAF50', '2196F3', 'FF9800', '9C27B0', 'E91E63', '795548', '009688', '673AB7'];
    $colorIndex = $supervisor['id'] % count($colors);
    $color = $colors[$colorIndex];
    
    // Format check-in time
    $checkInTime = $supervisor['check_in_time'] ? date('h:i A', strtotime($supervisor['check_in_time'])) : 'Not checked in';
    
    // Calculate experience in years
    $experience = 'N/A';
    if (!empty($supervisor['joining_date'])) {
        $joinDate = new DateTime($supervisor['joining_date']);
        $now = new DateTime();
        $interval = $joinDate->diff($now);
        $years = $interval->y;
        $months = $interval->m;
        
        if ($years > 0) {
            $experience = $years . ' year' . ($years > 1 ? 's' : '');
            if ($months > 0) {
                $experience .= ', ' . $months . ' month' . ($months > 1 ? 's' : '');
            }
        } elseif ($months > 0) {
            $experience = $months . ' month' . ($months > 1 ? 's' : '');
        } else {
            $experience = 'Less than a month';
        }
    } elseif (!empty($supervisor['work_experience'])) {
        $experience = $supervisor['work_experience'];
    }
    
    // Get team members (other users reporting to this supervisor)
    $teamQuery = "
        SELECT 
            u.id,
            u.username AS name,
            u.designation AS role,
            CASE 
                WHEN a.punch_in IS NOT NULL AND a.punch_out IS NULL THEN 'present'
                ELSE 'absent'
            END AS status
        FROM 
            users u
        LEFT JOIN 
            attendance a ON u.id = a.user_id AND a.date = :today
        WHERE 
            u.reporting_manager = :supervisor_id
        ORDER BY 
            u.username
    ";
    
    $teamStmt = $pdo->prepare($teamQuery);
    $teamStmt->execute([
        ':supervisor_id' => $supervisorId,
        ':today' => $today
    ]);
    
    $team = [];
    while ($member = $teamStmt->fetch()) {
        $team[] = [
            'id' => $member['id'],
            'name' => $member['name'],
            'role' => $member['role'] ?? 'Worker',
            'status' => $member['status']
        ];
    }
    
    // Get projects (events) created by this supervisor
    $projectsQuery = "
        SELECT 
            event_id AS id,
            title AS name,
            event_date,
            'Lead Supervisor' AS role,
            CASE 
                WHEN event_date < CURDATE() THEN 100
                WHEN event_date = CURDATE() THEN 50
                ELSE 25
            END AS progress
        FROM 
            sv_calendar_events
        WHERE 
            created_by = :supervisor_id
        ORDER BY 
            event_date DESC
        LIMIT 5
    ";
    
    $projectsStmt = $pdo->prepare($projectsQuery);
    $projectsStmt->execute([':supervisor_id' => $supervisorId]);
    
    $projects = [];
    while ($project = $projectsStmt->fetch()) {
        $projects[] = [
            'id' => $project['id'],
            'name' => $project['name'],
            'role' => $project['role'],
            'progress' => $project['progress']
        ];
    }
    
    // If no projects found, add dummy data
    if (empty($projects)) {
        $projects = [
            [
                'id' => 1,
                'name' => 'Assigned Project',
                'role' => 'Site Supervisor',
                'progress' => 50
            ]
        ];
    }
    
    // Get performance metrics based on attendance
    $performanceQuery = "
        SELECT 
            COUNT(*) AS total_days,
            SUM(CASE WHEN punch_in IS NOT NULL THEN 1 ELSE 0 END) AS present_days
        FROM 
            attendance
        WHERE 
            user_id = :supervisor_id
            AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ";
    
    $performanceStmt = $pdo->prepare($performanceQuery);
    $performanceStmt->execute([':supervisor_id' => $supervisorId]);
    $performanceData = $performanceStmt->fetch();
    
    $attendance = 0;
    if ($performanceData['total_days'] > 0) {
        $attendance = round(($performanceData['present_days'] / $performanceData['total_days']) * 100);
    }
    
    // Get recent activities from attendance
    $activityQuery = "
        SELECT 
            'attendance' AS type,
            CASE 
                WHEN punch_in IS NOT NULL AND punch_out IS NULL THEN 'Checked in'
                WHEN punch_out IS NOT NULL THEN 'Checked out'
                ELSE 'Activity recorded'
            END AS text,
            CASE 
                WHEN DATE(created_at) = CURDATE() THEN 
                    CONCAT(HOUR(created_at), ' hour', IF(HOUR(created_at)=1,'','s'), ' ago')
                WHEN DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 'Yesterday'
                ELSE DATE_FORMAT(created_at, '%b %d, %Y')
            END AS time
        FROM 
            attendance
        WHERE 
            user_id = :supervisor_id
        ORDER BY 
            created_at DESC
        LIMIT 5
    ";
    
    $activityStmt = $pdo->prepare($activityQuery);
    $activityStmt->execute([':supervisor_id' => $supervisorId]);
    
    $recentActivity = [];
    while ($activity = $activityStmt->fetch()) {
        $recentActivity[] = [
            'type' => $activity['type'],
            'text' => $activity['text'],
            'time' => $activity['time']
        ];
    }
    
    // If no activities found, add dummy data
    if (empty($recentActivity)) {
        $recentActivity = [
            [
                'type' => 'report',
                'text' => 'Submitted daily progress report',
                'time' => '1 hour ago'
            ],
            [
                'type' => 'attendance',
                'text' => 'Checked in for the day',
                'time' => 'Today'
            ]
        ];
    }
    
    // Prepare supervisor data
    $supervisorData = [
        'id' => $supervisor['id'],
        'name' => $supervisor['name'],
        'department' => $supervisor['department'] ?? $supervisor['role'],
        'designation' => $supervisor['designation'] ?? 'Site Supervisor',
        'present' => !empty($supervisor['check_in_time']),
        'avatar' => $avatar,
        'color' => $color,
        'phone' => $supervisor['phone'] ?? 'N/A',
        'email' => $supervisor['email'] ?? 'N/A',
        'site' => $supervisor['site_name'] ?? 'Assigned Site',
        'checkInTime' => $checkInTime,
        'status' => $status,
        'address' => $supervisor['address'] ?? 'Address not available',
        'experience' => $experience,
        'projects' => $projects,
        'team' => $team,
        'performance' => [
            'attendance' => $attendance,
            'productivity' => rand(80, 95), // Dummy data
            'quality' => rand(85, 98),      // Dummy data
            'safety' => rand(90, 100)       // Dummy data
        ],
        'recentActivity' => $recentActivity,
        'location' => $supervisor['location'] ?? 'On Site',
        'role' => $supervisor['role']
    ];
    
    // Return response
    echo json_encode([
        'status' => 'success',
        'supervisor' => $supervisorData
    ]);
    
} catch (PDOException $e) {
    // Log error
    error_log("Error fetching supervisor details: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch supervisor details',
        'error' => $e->getMessage()
    ]);
}
?> 