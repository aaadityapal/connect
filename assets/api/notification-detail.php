<?php
ini_set('display_errors', 0);
error_reporting(E_ERROR);
header('Content-Type: application/json');

session_start();
require_once '../../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check required parameters
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

$type = $_GET['type'];
$id = intval($_GET['id']);

// Add this debugging code at the beginning of your file to check upload paths
function getUploadPath() {
    $basePath = realpath(dirname(__FILE__) . '/../../');
    $circularsPath = $basePath . 'uploads/circulars';
    
    if (!file_exists($circularsPath)) {
        mkdir($circularsPath, 0755, true);
    }
    
    return [
        'base' => $basePath,
        'circulars' => $circularsPath,
        'exists' => file_exists($circularsPath)
    ];
}

// Debug check on paths
$paths = getUploadPath();

try {
    $notification = null;
    
    // Fetch detailed notification based on type
    switch ($type) {
        case 'announcement':
            $sql = "SELECT 
                   a.*, 
                   'announcement' as source_type, 
                   a.id as source_id,
                   CASE 
                       WHEN a.content IS NOT NULL AND a.content != '' THEN a.content 
                       WHEN a.message IS NOT NULL AND a.message != '' THEN CONCAT('<p>', a.message, '</p>') 
                       ELSE '<p>No additional content available.</p>' 
                   END as detailed_content
                   FROM announcements a
                   WHERE a.id = ?";
            break;
            
        case 'circular':
            $sql = "SELECT 
                   c.*, 
                   'circular' as source_type, 
                   c.id as source_id,
                   c.attachment_path as file_attachment,
                   CASE 
                       WHEN c.description IS NOT NULL AND c.description != '' THEN CONCAT('<p>', c.description, '</p>') 
                       ELSE '<p>No additional content available.</p>' 
                   END as detailed_content
                   FROM circulars c
                   WHERE c.id = ?";
            break;
            
        case 'event':
            $sql = "SELECT 
                   e.*, 
                   'event' as source_type, 
                   e.id as source_id,
                   CONCAT(
                       '<div class=\"event-details\">',
                       '<p>', COALESCE(e.description, 'No description available.'), '</p>',
                       '<div class=\"event-info\">',
                       '<p><i class=\"fas fa-clock\"></i> <strong>Start:</strong> ', 
                       DATE_FORMAT(e.start_date, '%a, %b %D, %Y at %h:%i %p'), '</p>',
                       '<p><i class=\"fas fa-hourglass-end\"></i> <strong>End:</strong> ', 
                       DATE_FORMAT(e.end_date, '%a, %b %D, %Y at %h:%i %p'), '</p>',
                       CASE WHEN e.location IS NOT NULL AND e.location != '' 
                           THEN CONCAT('<p><i class=\"fas fa-map-marker-alt\"></i> <strong>Location:</strong> ', e.location, '</p>') 
                           ELSE '' 
                       END,
                       '</div>',
                       '</div>'
                   ) as detailed_content
                   FROM events e
                   WHERE e.id = ?";
            break;
            
        case 'holiday':
            $sql = "SELECT 
                   h.*, 
                   'holiday' as source_type, 
                   h.id as source_id,
                   CONCAT(
                       '<div class=\"holiday-details\">',
                       '<p>', COALESCE(h.description, 'No description available.'), '</p>',
                       '<p><i class=\"fas fa-calendar-day\"></i> <strong>Date:</strong> ', 
                       DATE_FORMAT(h.holiday_date, '%W, %M %D, %Y'), '</p>',
                       CASE 
                           WHEN h.holiday_type IS NOT NULL AND h.holiday_type != '' 
                           THEN CONCAT('<p><i class=\"fas fa-info-circle\"></i> <strong>Type:</strong> ', h.holiday_type, '</p>') 
                           ELSE '' 
                       END,
                       '</div>'
                   ) as detailed_content
                   FROM holidays h
                   WHERE h.id = ?";
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid notification type']);
            exit;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $notification = $result->fetch_assoc();
        echo json_encode(['status' => 'success', 'notification' => $notification]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Notification not found']);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while fetching notification details',
        'error' => $e->getMessage()
    ]);
}
?> 