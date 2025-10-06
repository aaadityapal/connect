<?php
/**
 * Submit Missing Punch Out Handler
 * This script handles the submission of missing punch-out data
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db_connect.php'; // Adjust path as needed

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get POST data
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $work_report = $_POST['work_report'] ?? '';
    $confirmed = $_POST['confirmed'] ?? false;
    
    // Validate inputs
    if (empty($date) || empty($time) || empty($reason) || empty($work_report)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    // Validate date format
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }
    
    // Validate time format
    $timeObj = DateTime::createFromFormat('H:i', $time);
    if (!$timeObj || $timeObj->format('H:i') !== $time) {
        echo json_encode(['success' => false, 'message' => 'Invalid time format']);
        exit;
    }
    
    // Validate reason length (min 15 words)
    $reasonWordCount = str_word_count($reason);
    if ($reasonWordCount < 15) {
        echo json_encode(['success' => false, 'message' => 'Reason must be at least 15 words']);
        exit;
    }
    
    // Validate work report length (min 20 words)
    $workReportWordCount = str_word_count($work_report);
    if ($workReportWordCount < 20) {
        echo json_encode(['success' => false, 'message' => 'Work report must be at least 20 words']);
        exit;
    }
    
    // Check if confirmation is provided
    if (!$confirmed) {
        echo json_encode(['success' => false, 'message' => 'Please confirm the information is accurate']);
        exit;
    }
    
    // Use the existing database connection from db_connect.php
    global $conn; // This should be available from db_connect.php
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert into missing_punch_out table with pending status
        $insertMissingPunchQuery = "INSERT INTO missing_punch_out (user_id, date, punch_out_time, reason, work_report, confirmed, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $insertMissingPunchStmt = $conn->prepare($insertMissingPunchQuery);
        $insertMissingPunchStmt->bind_param("issssi", $user_id, $date, $time, $reason, $work_report, $confirmed);
        $insertMissingPunchStmt->execute();
        $missingPunchId = $conn->insert_id;
        
        // DO NOT update attendance table here - only update when approved
        // The attendance table will be updated separately when the request is approved
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Missing punch-out submitted successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error processing request: ' . $e->getMessage()]);
}
?>