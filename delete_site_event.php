<?php
// Include necessary files
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: site_supervision.php");
    exit();
}

// Get event ID from POST data
$event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

// Validate event ID
if ($event_id <= 0) {
    $_SESSION['error'] = "Invalid event ID.";
    header("Location: site_supervision.php");
    exit();
}

try {
    // Check if event exists
    $query = "SELECT id FROM site_events WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$event_id]);
    
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "Event not found.";
        header("Location: site_supervision.php");
        exit();
    }
    
    // Delete the event
    $query = "DELETE FROM site_events WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$event_id]);
    
    // Success message
    $_SESSION['success'] = "Event deleted successfully.";
    
    // Redirect back to calendar
    header("Location: site_supervision.php");
    exit();
    
} catch (PDOException $e) {
    error_log('Error deleting event: ' . $e->getMessage());
    $_SESSION['error'] = "Failed to delete event. Please try again.";
    header("Location: view_site_event.php?id=" . $event_id);
    exit();
}
?>