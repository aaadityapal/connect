<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid inventory ID']);
    exit();
}

$inventory_id = intval($_GET['id']);
$view_mode = isset($_GET['view']) && $_GET['view'] === 'true';

try {
    // Get inventory item details
    $query = "SELECT i.*, e.title as site_name, e.event_date 
              FROM sv_inventory_items i
              JOIN sv_calendar_events e ON i.event_id = e.event_id
              WHERE i.inventory_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$inventory_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Inventory item not found');
    }
    
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get media files for this item
    $media_query = "SELECT * FROM sv_inventory_media WHERE inventory_id = ? ORDER BY sequence_number, created_at";
    $media_stmt = $pdo->prepare($media_query);
    $media_stmt->execute([$inventory_id]);
    $media = $media_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get events list for edit mode
    $events = [];
    if (!$view_mode) {
        $events_query = "SELECT event_id, title, event_date FROM sv_calendar_events ORDER BY event_date DESC";
        $events_stmt = $pdo->prepare($events_query);
        $events_stmt->execute();
        $events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format event dates for display
        foreach ($events as &$event) {
            $event['formatted_date'] = date('d M Y', strtotime($event['event_date']));
        }
    }
    
    // Format numbers and dates for consistency
    $item['quantity'] = number_format($item['quantity'], 2, '.', '');
    $item['formatted_date'] = date('d M Y', strtotime($item['event_date']));
    $item['created_formatted'] = date('d M Y h:i A', strtotime($item['created_at']));
    
    // Format media file information
    foreach ($media as &$file) {
        $file_extension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
        $file['is_image'] = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);
        $file['is_pdf'] = ($file_extension === 'pdf');
        $file['icon_class'] = $file['is_pdf'] ? 'fa-file-pdf' : ($file['is_image'] ? 'fa-image' : 'fa-file');
        $file['formatted_size'] = formatFileSize($file['file_size']);
        $file['formatted_date'] = date('d M Y h:i A', strtotime($file['created_at']));
    }
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'item' => $item,
        'media' => $media,
        'events' => $events
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Format file size in bytes to human-readable format
 */
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?> 