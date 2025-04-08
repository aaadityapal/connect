<?php
// Add these at the very beginning of the file to prevent HTML errors from being included
ini_set('display_errors', 0);
error_reporting(E_ERROR);
header('Content-Type: application/json');

session_start();
require_once '../../config/db_connect.php';

// Debug user ID
error_log("Current user ID from session: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set'));

// Check database connection
if (!$conn) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'error' => mysqli_connect_error()
    ]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = [];

try {
    // Get combined notification count if requested
    if (isset($_GET['count_only']) && $_GET['count_only'] == 1) {
        $count = getUnreadNotificationCount($conn, $user_id);
        
        // Add detailed count for debugging
        $debug_counts = [
            'announcements' => 0,
            'circulars' => 0,
            'events' => 0,
            'holidays' => 0
        ];

        // Get individual counts for each type
        $types = ['announcement', 'circular', 'event', 'holiday'];
        foreach ($types as $type) {
            $sql = "SELECT COUNT(*) as type_count FROM " . 
                   ($type . "s") . " t WHERE NOT EXISTS (
                        SELECT 1 FROM notification_read_status nrs 
                        WHERE nrs.source_id = t.id 
                        AND nrs.notification_type = ? 
                        AND nrs.user_id = ?
                    )";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $type, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $debug_counts[$type . "s"] = (int)$result->fetch_assoc()['type_count'];
        }

        echo json_encode([
            'status' => 'success',
            'count' => $count,
            'debug_counts' => $debug_counts
        ]);
        exit;
    }

    // In the main section of the file before calling getCombinedNotifications
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    $notifications = getCombinedNotifications($conn, $user_id, $limit, $offset, $filter);

    if (!empty($notifications)) {
        $response['notifications'] = $notifications;
        $response['status'] = 'success';
    } else {
        $response['notifications'] = [];
        $response['status'] = 'empty';
    }

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while fetching notifications',
        'error' => $e->getMessage()
    ]);
}

/**
 * Get unread notification count from all sources
 */
function getUnreadNotificationCount($conn, $user_id) {
    try {
        $current_date = date('Y-m-d H:i:s');
        
        $sql = "SELECT COUNT(*) as total_unread FROM (
            -- Announcements (not expired)
            SELECT a.id, 'announcement' as type
            FROM announcements a 
            WHERE (a.display_until IS NULL OR a.display_until >= ?)
            AND NOT EXISTS (
                SELECT 1 FROM notification_read_status nrs 
                WHERE nrs.source_id = a.id 
                AND nrs.notification_type = 'announcement' 
                AND nrs.user_id = ?
            )
            UNION ALL
            -- Circulars (not expired)
            SELECT c.id, 'circular' as type
            FROM circulars c 
            WHERE (c.valid_until IS NULL OR c.valid_until >= ?)
            AND NOT EXISTS (
                SELECT 1 FROM notification_read_status nrs 
                WHERE nrs.source_id = c.id 
                AND nrs.notification_type = 'circular' 
                AND nrs.user_id = ?
            )
            UNION ALL
            -- Events (not ended)
            SELECT e.id, 'event' as type
            FROM events e 
            WHERE (e.end_date IS NULL OR e.end_date >= ?)
            AND NOT EXISTS (
                SELECT 1 FROM notification_read_status nrs 
                WHERE nrs.source_id = e.id 
                AND nrs.notification_type = 'event' 
                AND nrs.user_id = ?
            )
            UNION ALL
            -- Holidays (not passed)
            SELECT h.id, 'holiday' as type
            FROM holidays h 
            WHERE (h.holiday_date IS NULL OR h.holiday_date >= CURRENT_DATE())
            AND NOT EXISTS (
                SELECT 1 FROM notification_read_status nrs 
                WHERE nrs.source_id = h.id 
                AND nrs.notification_type = 'holiday' 
                AND nrs.user_id = ?
            )
        ) as unread_notifications";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssssss', 
            $current_date, $user_id,
            $current_date, $user_id,
            $current_date, $user_id,
            $user_id
        );
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return (int)$row['total_unread'];
    } catch (Exception $e) {
        error_log("Error in getUnreadNotificationCount: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get combined notifications from all sources with read status check
 */
function getCombinedNotifications($conn, $user_id, $limit = 20, $offset = 0, $filter = 'all') {
    $current_date = date('Y-m-d H:i:s');
    $current_day = date('Y-m-d');
    $notifications = [];

    try {
        // Add read status condition based on filter
        $read_condition = $filter === 'unread' ? 'AND nrs.id IS NULL' : '';
        
        // If assignments filter is selected, only get assignment notifications
        if ($filter === 'assignments') {
            error_log("DEBUG: Processing assignments filter for user ID: " . $user_id);
            
            // Project assignments
            $sql = "SELECT 
                    'project' as source_type,
                    p.id as source_id,
                    CONCAT('Project Assignment: ', p.title) as title,
                    CONCAT('You have been assigned to project: ', p.title) as message,
                    l.created_at as created_at,
                    NULL as expiration_date,
                    'fas fa-user-plus' as icon,
                    'success' as type,
                    CONCAT('view_project.php?id=', p.id) as action_url,
                    CASE WHEN nrs.id IS NOT NULL THEN 1 ELSE 0 END as read_status
                FROM assignment_status_logs l
                JOIN projects p ON p.id = l.entity_id AND l.entity_type = 'project'
                LEFT JOIN notification_read_status nrs ON 
                    nrs.source_id = p.id AND 
                    nrs.notification_type = 'project' AND 
                    nrs.user_id = ?
                WHERE l.new_status = 'assigned'
                AND l.entity_type = 'project'
                AND l.assigned_to = ?
                $read_condition
                ORDER BY l.created_at DESC";
            
            error_log("DEBUG: Project assignments SQL: " . str_replace('?', $user_id, $sql));
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $user_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $project_count = $result->num_rows;
            error_log("DEBUG: Project assignments query found " . $project_count . " results for user " . $user_id);
            
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            
            // Stage assignments
            $sql = "SELECT 
                    'stage' as source_type,
                    s.id as source_id,
                    CONCAT('Stage Assignment: Stage #', s.stage_number) as title,
                    CONCAT('You have been assigned to stage #', s.stage_number, ' in project: ', p.title) as message,
                    l.created_at as created_at,
                    NULL as expiration_date,
                    'fas fa-user-tag' as icon,
                    'success' as type,
                    CONCAT('view_project.php?id=', p.id, '&stage_id=', s.id) as action_url,
                    CASE WHEN nrs.id IS NOT NULL THEN 1 ELSE 0 END as read_status
                FROM assignment_status_logs l
                JOIN project_stages s ON s.id = l.entity_id AND l.entity_type = 'stage'
                JOIN projects p ON p.id = s.project_id
                LEFT JOIN notification_read_status nrs ON 
                    nrs.source_id = s.id AND 
                    nrs.notification_type = 'stage' AND 
                    nrs.user_id = ?
                WHERE l.new_status = 'assigned'
                AND l.entity_type = 'stage'
                AND l.assigned_to = ?
                $read_condition
                ORDER BY l.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $user_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stage_count = $result->num_rows;
            error_log("DEBUG: Stage assignments query found " . $stage_count . " results");
            
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            
            // Substage assignments
            $sql = "SELECT 
                    'substage' as source_type,
                    ss.id as source_id,
                    CONCAT('Task Assignment: ', ss.title) as title,
                    CONCAT('You have been assigned to task: ', ss.title, ' in stage #', s.stage_number, ' of project: ', p.title) as message,
                    l.created_at as created_at,
                    NULL as expiration_date,
                    'fas fa-user-check' as icon,
                    'success' as type,
                    CONCAT('view_project.php?id=', p.id, '&stage_id=', s.id, '&substage_id=', ss.id) as action_url,
                    CASE WHEN nrs.id IS NOT NULL THEN 1 ELSE 0 END as read_status
                FROM assignment_status_logs l
                JOIN project_substages ss ON ss.id = l.entity_id AND l.entity_type = 'substage'
                JOIN project_stages s ON s.id = ss.stage_id
                JOIN projects p ON p.id = s.project_id
                LEFT JOIN notification_read_status nrs ON 
                    nrs.source_id = ss.id AND 
                    nrs.notification_type = 'substage' AND 
                    nrs.user_id = ?
                WHERE l.new_status = 'assigned'
                AND l.entity_type = 'substage'
                AND l.assigned_to = ?
                $read_condition
                ORDER BY l.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $user_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $substage_count = $result->num_rows;
            error_log("DEBUG: Substage assignments query found " . $substage_count . " results");
            
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            
            error_log("DEBUG: Total assignment notifications for user " . $user_id . ": " . count($notifications));
        } else {
            // Regular notifications if not filtering by assignments
            // Announcements
            $sql = "SELECT 
                    'announcement' as source_type,
                    a.id as source_id,
                    a.title,
                    a.message,
                    a.created_at,
                    a.display_until as expiration_date,
                    'fas fa-bullhorn' as icon,
                    'info' as type,
                    CONCAT('view_announcement.php?id=', a.id) as action_url,
                    CASE WHEN nrs.id IS NOT NULL THEN 1 ELSE 0 END as read_status
                FROM announcements a
                LEFT JOIN notification_read_status nrs ON 
                    nrs.source_id = a.id AND 
                    nrs.notification_type = 'announcement' AND 
                    nrs.user_id = ?
                WHERE (a.display_until IS NULL OR a.display_until >= ?)
                $read_condition
                ORDER BY a.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('is', $user_id, $current_date);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
    
            // Circulars
            $sql = "SELECT 
                    'circular' as source_type,
                    c.id as source_id,
                    c.title,
                    c.description as message,
                    c.created_at,
                    c.valid_until as expiration_date,
                    'fas fa-file' as icon,
                    'info' as type,
                    CONCAT('view_circular.php?id=', c.id) as action_url,
                    CASE WHEN nrs.id IS NOT NULL THEN 1 ELSE 0 END as read_status
                FROM circulars c
                LEFT JOIN notification_read_status nrs ON 
                    nrs.source_id = c.id AND 
                    nrs.notification_type = 'circular' AND 
                    nrs.user_id = ?
                WHERE (c.valid_until IS NULL OR c.valid_until >= ?)
                $read_condition
                ORDER BY c.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('is', $user_id, $current_date);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
    
            // Events
            $sql = "SELECT 
                    'event' as source_type,
                    e.id as source_id,
                    e.title,
                    e.description as message,
                    e.created_at,
                    e.end_date as expiration_date,
                    'fas fa-calendar' as icon,
                    'info' as type,
                    CONCAT('view_event.php?id=', e.id) as action_url,
                    CASE WHEN nrs.id IS NOT NULL THEN 1 ELSE 0 END as read_status
                FROM events e
                LEFT JOIN notification_read_status nrs ON 
                    nrs.source_id = e.id AND 
                    nrs.notification_type = 'event' AND 
                    nrs.user_id = ?
                WHERE (e.end_date IS NULL OR e.end_date >= ?)
                $read_condition
                ORDER BY e.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('is', $user_id, $current_date);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
    
            // Holidays
            $sql = "SELECT 
                    'holiday' as source_type,
                    h.id as source_id,
                    h.title,
                    h.description as message,
                    h.created_at,
                    h.holiday_date as expiration_date,
                    'fas fa-calendar' as icon,
                    'info' as type,
                    CONCAT('view_holiday.php?id=', h.id) as action_url,
                    CASE WHEN nrs.id IS NOT NULL THEN 1 ELSE 0 END as read_status
                FROM holidays h
                LEFT JOIN notification_read_status nrs ON 
                    nrs.source_id = h.id AND 
                    nrs.notification_type = 'holiday' AND 
                    nrs.user_id = ?
                WHERE (h.holiday_date IS NULL OR h.holiday_date >= ?)
                $read_condition
                ORDER BY h.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('is', $user_id, $current_day);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }

            // Add assignment notifications in the ALL tab
            // Project assignments
            $sql = "SELECT 
                    'project' as source_type,
                    p.id as source_id,
                    CONCAT('Project Assignment: ', p.title) as title,
                    CONCAT('You have been assigned to project: ', p.title) as message,
                    l.created_at as created_at,
                    NULL as expiration_date,
                    'fas fa-user-plus' as icon,
                    'success' as type,
                    CONCAT('view_project.php?id=', p.id) as action_url,
                    CASE WHEN nrs.id IS NOT NULL THEN 1 ELSE 0 END as read_status
                FROM assignment_status_logs l
                JOIN projects p ON p.id = l.entity_id AND l.entity_type = 'project'
                LEFT JOIN notification_read_status nrs ON 
                    nrs.source_id = p.id AND 
                    nrs.notification_type = 'project' AND 
                    nrs.user_id = ?
                WHERE l.new_status = 'assigned'
                AND l.entity_type = 'project'
                AND l.assigned_to = ?
                $read_condition
                ORDER BY l.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $user_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            
            // Stage assignments
            $sql = "SELECT 
                    'stage' as source_type,
                    s.id as source_id,
                    CONCAT('Stage Assignment: Stage #', s.stage_number) as title,
                    CONCAT('You have been assigned to stage #', s.stage_number, ' in project: ', p.title) as message,
                    l.created_at as created_at,
                    NULL as expiration_date,
                    'fas fa-user-tag' as icon,
                    'success' as type,
                    CONCAT('view_project.php?id=', p.id, '&stage_id=', s.id) as action_url,
                    CASE WHEN nrs.id IS NOT NULL THEN 1 ELSE 0 END as read_status
                FROM assignment_status_logs l
                JOIN project_stages s ON s.id = l.entity_id AND l.entity_type = 'stage'
                JOIN projects p ON p.id = s.project_id
                LEFT JOIN notification_read_status nrs ON 
                    nrs.source_id = s.id AND 
                    nrs.notification_type = 'stage' AND 
                    nrs.user_id = ?
                WHERE l.new_status = 'assigned'
                AND l.entity_type = 'stage'
                AND l.assigned_to = ?
                $read_condition
                ORDER BY l.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $user_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            
            // Substage assignments
            $sql = "SELECT 
                    'substage' as source_type,
                    ss.id as source_id,
                    CONCAT('Task Assignment: ', ss.title) as title,
                    CONCAT('You have been assigned to task: ', ss.title, ' in stage #', s.stage_number, ' of project: ', p.title) as message,
                    l.created_at as created_at,
                    NULL as expiration_date,
                    'fas fa-user-check' as icon,
                    'success' as type,
                    CONCAT('view_project.php?id=', p.id, '&stage_id=', s.id, '&substage_id=', ss.id) as action_url,
                    CASE WHEN nrs.id IS NOT NULL THEN 1 ELSE 0 END as read_status
                FROM assignment_status_logs l
                JOIN project_substages ss ON ss.id = l.entity_id AND l.entity_type = 'substage'
                JOIN project_stages s ON s.id = ss.stage_id
                JOIN projects p ON p.id = s.project_id
                LEFT JOIN notification_read_status nrs ON 
                    nrs.source_id = ss.id AND 
                    nrs.notification_type = 'substage' AND 
                    nrs.user_id = ?
                WHERE l.new_status = 'assigned'
                AND l.entity_type = 'substage'
                AND l.assigned_to = ?
                $read_condition
                ORDER BY l.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $user_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
        }

        // Sort all notifications by created_at
        usort($notifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        // Apply limit and offset
        return array_slice($notifications, $offset, $limit);

    } catch (Exception $e) {
        error_log("Error in getCombinedNotifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Check which notifications have been read by the user
 */
function checkReadStatus($conn, $user_id, $notifications) {
    if (empty($notifications)) {
        return $notifications;
    }
    
    // Extract all notification IDs and types
    $notification_map = [];
    foreach ($notifications as $index => $notification) {
        $parts = explode('_', $notification['id'], 2);
        if (count($parts) === 2) {
            $notification_type = $parts[0];
            $source_id = intval($parts[1]);
            $notification_map[$notification_type . '_' . $source_id] = $index;
        }
    }
    
    if (empty($notification_map)) {
        return $notifications;
    }
    
    // Query for all read notifications for this user
    $placeholders = implode(',', array_fill(0, count($notification_map), '(? , ?)'));
    $query = "SELECT notification_type, source_id 
              FROM notification_read_status 
              WHERE user_id = ? 
              AND (notification_type, source_id) IN ($placeholders)";
    
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        // Build parameters array starting with user_id
        $params = [$user_id];
        $types = "i";
        
        // Add each notification type and source id
        foreach ($notification_map as $key => $index) {
            $parts = explode('_', $key, 2);
            $notification_type = $parts[0];
            $source_id = intval($parts[1]);
            
            $params[] = $notification_type;
            $params[] = $source_id;
            $types .= "si"; // string, integer
        }
        
        // Add the types string to the beginning of the params array
        array_unshift($params, $types);
        
        // Call bind_param with dynamic arguments
        call_user_func_array([$stmt, 'bind_param'], $params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Mark notifications as read
        while ($row = $result->fetch_assoc()) {
            $key = $row['notification_type'] . '_' . $row['source_id'];
            if (isset($notification_map[$key])) {
                $index = $notification_map[$key];
                $notifications[$index]['read_status'] = 1;
            }
        }
    }
    
    return $notifications;
}

/**
 * Format the time for display
 */
function addTimeDisplay($notifications) {
    foreach ($notifications as &$notification) {
        $created_time = strtotime($notification['created_at']);
        $current_time = time();
        $time_diff = $current_time - $created_time;
        
        if ($time_diff < 60) {
            $notification['time_display'] = "Just now";
        } elseif ($time_diff < 3600) {
            $minutes = floor($time_diff / 60);
            $notification['time_display'] = $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
        } elseif ($time_diff < 86400) {
            $hours = floor($time_diff / 3600);
            $notification['time_display'] = $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
        } elseif ($time_diff < 604800) {
            $days = floor($time_diff / 86400);
            $notification['time_display'] = $days . " day" . ($days > 1 ? "s" : "") . " ago";
        } else {
            $notification['time_display'] = date("M j, Y", $created_time);
        }
    }
    
    return $notifications;
}

/**
 * Fetch announcements
 */
function fetchAnnouncements($conn) {
    $notifications = [];
    
    // Get active announcements that haven't expired
    $query = "SELECT 
                id,
                title,
                message,
                priority,
                created_at,
                'announcement' as type
              FROM 
                announcements
              WHERE 
                status = 'active' 
                AND (display_until IS NULL OR display_until >= CURDATE())
              ORDER BY 
                created_at DESC";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Set icon and notification type based on priority
            switch (strtolower($row['priority'])) {
                case 'high':
                    $row['icon'] = 'fas fa-exclamation-circle';
                    $row['notification_type'] = 'danger';
                    break;
                case 'medium':
                    $row['icon'] = 'fas fa-info-circle';
                    $row['notification_type'] = 'warning';
                    break;
                default:
                    $row['icon'] = 'fas fa-bullhorn';
                    $row['notification_type'] = 'info';
            }
            
            // Create notification object
            $notification = [
                'id' => 'announcement_' . $row['id'],
                'title' => $row['title'],
                'message' => substr(strip_tags($row['message']), 0, 100) . (strlen(strip_tags($row['message'])) > 100 ? '...' : ''),
                'icon' => $row['icon'],
                'type' => $row['notification_type'],
                'source_type' => 'announcement',
                'source_id' => $row['id'],
                'created_at' => $row['created_at'],
                'action_url' => 'view_announcement.php?id=' . $row['id'],
                'read_status' => 0 // By default, consider all as unread
            ];
            
            $notifications[] = $notification;
        }
    }
    
    return $notifications;
}

/**
 * Fetch circulars
 */
function fetchCirculars($conn) {
    $notifications = [];
    
    // Get active circulars that haven't expired
    $query = "SELECT 
                id,
                title,
                description,
                attachment_path,
                created_at,
                'circular' as type
              FROM 
                circulars
              WHERE 
                status = 'active' 
                AND (valid_until IS NULL OR valid_until >= CURDATE())
              ORDER BY 
                created_at DESC";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Create notification object
            $notification = [
                'id' => 'circular_' . $row['id'],
                'title' => $row['title'],
                'message' => substr(strip_tags($row['description']), 0, 100) . (strlen(strip_tags($row['description'])) > 100 ? '...' : ''),
                'icon' => 'fas fa-file-alt',
                'type' => 'info',
                'source_type' => 'circular',
                'source_id' => $row['id'],
                'created_at' => $row['created_at'],
                'action_url' => 'view_circular.php?id=' . $row['id'],
                'read_status' => 0 // By default, consider all as unread
            ];
            
            // If there's an attachment, show that in the message
            if (!empty($row['attachment_path'])) {
                $notification['message'] .= ' (Includes attachment)';
            }
            
            $notifications[] = $notification;
        }
    }
    
    return $notifications;
}

/**
 * Fetch events
 */
function fetchEvents($conn) {
    $notifications = [];
    
    // Get upcoming events (within next 7 days)
    $query = "SELECT 
                id,
                title,
                description,
                event_date,
                start_date,
                end_date,
                start_time,
                end_time,
                location,
                event_type,
                created_at,
                'event' as type
              FROM 
                events
              WHERE 
                status = 'active' 
                AND (
                    (event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))
                    OR 
                    (start_date IS NOT NULL AND end_date IS NOT NULL AND end_date >= CURDATE() AND start_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))
                )
              ORDER BY 
                COALESCE(event_date, start_date) ASC";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Format date information
            $date_info = "";
            
            if (!empty($row['event_date'])) {
                $event_date = new DateTime($row['event_date']);
                $now = new DateTime();
                $date_diff = $now->diff($event_date);
                
                if ($date_diff->days == 0) {
                    $date_info = "Today";
                } elseif ($date_diff->days == 1) {
                    $date_info = "Tomorrow";
                } else {
                    $date_info = "In " . $date_diff->days . " days";
                }
                
                if (!empty($row['start_time']) && !empty($row['end_time'])) {
                    $date_info .= " (" . date("h:i A", strtotime($row['start_time'])) . " - " . date("h:i A", strtotime($row['end_time'])) . ")";
                }
            } elseif (!empty($row['start_date']) && !empty($row['end_date'])) {
                $start_date = new DateTime($row['start_date']);
                $end_date = new DateTime($row['end_date']);
                $now = new DateTime();
                
                if ($start_date <= $now && $end_date >= $now) {
                    $date_info = "Ongoing until " . $end_date->format("M j");
                } else {
                    $date_info = "From " . $start_date->format("M j") . " to " . $end_date->format("M j");
                }
            }
            
            // Set icon based on event type
            $icon = 'fas fa-calendar-day';
            $notification_type = 'info';
            
            if (!empty($row['event_type'])) {
                switch (strtolower($row['event_type'])) {
                    case 'meeting':
                        $icon = 'fas fa-users';
                        break;
                    case 'deadline':
                        $icon = 'fas fa-hourglass-end';
                        $notification_type = 'warning';
                        break;
                    case 'training':
                        $icon = 'fas fa-chalkboard-teacher';
                        break;
                    case 'celebration':
                        $icon = 'fas fa-glass-cheers';
                        $notification_type = 'success';
                        break;
                    default:
                        $icon = 'fas fa-calendar-day';
                }
            }
            
            // Create event message with location if available
            $message = substr(strip_tags($row['description']), 0, 80) . (strlen(strip_tags($row['description'])) > 80 ? '...' : '');
            
            if (!empty($date_info)) {
                $message = $date_info . '. ' . $message;
            }
            
            if (!empty($row['location'])) {
                $message .= ' at ' . $row['location'];
            }
            
            // Create notification object
            $notification = [
                'id' => 'event_' . $row['id'],
                'title' => $row['title'],
                'message' => $message,
                'icon' => $icon,
                'type' => $notification_type,
                'source_type' => 'event',
                'source_id' => $row['id'],
                'created_at' => $row['created_at'],
                'action_url' => 'view_event.php?id=' . $row['id'],
                'read_status' => 0 // By default, consider all as unread
            ];
            
            $notifications[] = $notification;
        }
    }
    
    return $notifications;
}

/**
 * Fetch holidays
 */
function fetchHolidays($conn) {
    $notifications = [];
    
    // Get upcoming holidays (within next 14 days)
    $query = "SELECT 
                id,
                title,
                holiday_date,
                holiday_type,
                description,
                created_at,
                'holiday' as type
              FROM 
                holidays
              WHERE 
                status = 'active' 
                AND holiday_date >= CURDATE()
                AND holiday_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
              ORDER BY 
                holiday_date ASC";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Format date information
            $holiday_date = new DateTime($row['holiday_date']);
            $now = new DateTime();
            $date_diff = $now->diff($holiday_date);
            
            $date_info = "";
            if ($date_diff->days == 0) {
                $date_info = "Today";
            } elseif ($date_diff->days == 1) {
                $date_info = "Tomorrow";
            } else {
                $date_info = "In " . $date_diff->days . " days";
            }
            
            $date_info .= " (" . $holiday_date->format("D, M j") . ")";
            
            // Set icon based on holiday type
            $icon = 'fas fa-calendar-check';
            
            if (!empty($row['holiday_type'])) {
                switch (strtolower($row['holiday_type'])) {
                    case 'national':
                        $icon = 'fas fa-flag';
                        break;
                    case 'religious':
                        $icon = 'fas fa-pray';
                        break;
                    case 'company':
                        $icon = 'fas fa-building';
                        break;
                    default:
                        $icon = 'fas fa-calendar-check';
                }
            }
            
            // Create holiday message
            $message = "Holiday: " . $date_info;
            
            if (!empty($row['description'])) {
                $message .= ". " . substr(strip_tags($row['description']), 0, 80) . 
                           (strlen(strip_tags($row['description'])) > 80 ? '...' : '');
            }
            
            // Create notification object
            $notification = [
                'id' => 'holiday_' . $row['id'],
                'title' => $row['title'],
                'message' => $message,
                'icon' => $icon,
                'type' => 'success',
                'source_type' => 'holiday',
                'source_id' => $row['id'],
                'created_at' => $row['created_at'],
                'action_url' => 'view_holidays.php',
                'read_status' => 0 // By default, consider all as unread
            ];
            
            $notifications[] = $notification;
        }
    }
    
    return $notifications;
} 
?> 