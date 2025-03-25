<?php
// Add these at the very beginning of the file
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
$response = ['status' => 'error'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data['action'] === 'mark_all_read') {
        try {
            // Get all unread notifications first
            $sources = ['announcement', 'circular', 'event', 'holiday'];
            $current_time = date('Y-m-d H:i:s');
            
            foreach ($sources as $source_type) {
                // Get all IDs that haven't been read yet
                $sql = "";
                switch ($source_type) {
                    case 'announcement':
                        $sql = "SELECT id FROM announcements WHERE id NOT IN 
                               (SELECT source_id FROM notification_read_status 
                                WHERE user_id = ? AND notification_type = 'announcement')";
                        break;
                    case 'circular':
                        $sql = "SELECT id FROM circulars WHERE id NOT IN 
                               (SELECT source_id FROM notification_read_status 
                                WHERE user_id = ? AND notification_type = 'circular')";
                        break;
                    case 'event':
                        $sql = "SELECT id FROM events WHERE id NOT IN 
                               (SELECT source_id FROM notification_read_status 
                                WHERE user_id = ? AND notification_type = 'event')";
                        break;
                    case 'holiday':
                        $sql = "SELECT id FROM holidays WHERE id NOT IN 
                               (SELECT source_id FROM notification_read_status 
                                WHERE user_id = ? AND notification_type = 'holiday')";
                        break;
                }
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    // Insert read status for each unread item
                    $insert_sql = "INSERT INTO notification_read_status 
                                 (user_id, notification_type, source_id, read_at) 
                                 VALUES (?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param('isss', 
                        $_SESSION['user_id'], 
                        $source_type, 
                        $row['id'], 
                        $current_time
                    );
                    $insert_stmt->execute();
                }
            }
            
            echo json_encode(['status' => 'success', 'message' => 'All notifications marked as read']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // Mark a single notification as read
    if (isset($data['notification_id'])) {
        $notification_id = $data['notification_id'];
        
        // Parse the notification ID to get type and source ID
        // Format: type_sourceId (e.g., announcement_5, event_12)
        $parts = explode('_', $notification_id, 2);
        
        if (count($parts) === 2) {
            $notification_type = $parts[0];
            $source_id = intval($parts[1]);
            
            // Check if this notification is already marked as read
            $check_query = "SELECT id FROM notification_read_status 
                          WHERE user_id = ? AND notification_type = ? AND source_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("isi", $user_id, $notification_type, $source_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            // If not already marked as read, insert a new record
            if ($check_result->num_rows === 0) {
                $insert_query = "INSERT INTO notification_read_status 
                                (user_id, notification_type, source_id) 
                                VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("isi", $user_id, $notification_type, $source_id);
                
                if ($insert_stmt->execute()) {
                    $response['status'] = 'success';
                    $response['message'] = 'Notification marked as read';
                }
            } else {
                // Already marked as read
                $response['status'] = 'success';
                $response['message'] = 'Notification already read';
            }
        }
    }

    // Add this at the beginning of the file after session_start()
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    // Inside your mark_read action handler
    if ($data['action'] === 'mark_read') {
        try {
            $notification_type = $data['notification_type'];
            $source_id = $data['source_id'];
            $current_time = date('Y-m-d H:i:s');
            
            // First check if already marked as read
            $check_sql = "SELECT id FROM notification_read_status 
                         WHERE user_id = ? AND notification_type = ? AND source_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param('isi', $_SESSION['user_id'], $notification_type, $source_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Only insert if not already marked as read
                $sql = "INSERT INTO notification_read_status 
                       (user_id, notification_type, source_id, read_at) 
                       VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('isis', $_SESSION['user_id'], $notification_type, $source_id, $current_time);
                $stmt->execute();
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Notification marked as read'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// Add this temporarily at the beginning of your file after database connection
$debug_sql = "SELECT * FROM notification_read_status WHERE user_id = ?";
$debug_stmt = $conn->prepare($debug_sql);
$debug_stmt->bind_param('i', $_SESSION['user_id']);
$debug_stmt->execute();
$debug_result = $debug_stmt->get_result();
error_log("Current read status entries for user " . $_SESSION['user_id'] . ": " . json_encode($debug_result->fetch_all(MYSQLI_ASSOC)));

echo json_encode($response);

// Include the function to get combined notifications
// Note: This is a simplified version just for getting notification IDs
function getCombinedNotifications($conn, $user_id, $limit, $offset) {
    // Get all notifications without filtering by read status
    // This function would need to be adapted from combined-notifications.php
    // For simplicity, we're returning an empty array here
    return [];
}
?> 