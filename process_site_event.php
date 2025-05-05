<?php
/**
 * Process Site Event Form Submission
 * This script handles the AJAX form submission for site events
 */

// Include necessary files
require_once 'config/db_connect.php';
require_once 'includes/activity_logger.php';
require_once 'includes/file_upload.php';
require_once 'includes/process_event.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You must be logged in to perform this action.'
    ]);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
    exit;
}

// Get the current user ID
$userId = $_SESSION['user_id'];

// Process the form submission
$result = processSiteEventForm($_POST, $_FILES, $userId);

// Return the JSON response
header('Content-Type: application/json');
echo json_encode($result);
exit; 