<?php
// Start output buffering
ob_start();

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable displaying errors directly

// Clear any existing output
ob_clean();

// Set JSON headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Adjust the path based on your directory structure
require_once __DIR__ . '/../../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Verify database connection
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Query to get forwarded tasks with project, stage, and substage details
    $query = "SELECT 
                ft.id,
                ft.project_id,
                ft.stage_id,
                ft.substage_id,
                ft.type,
                ft.status as forward_status,
                ft.created_at as forwarded_at,
                ps.stage_number,
                ps.status as stage_status,
                pss.substage_number,
                pss.title as substage_title,
                pss.status as substage_status,
                u.username as forwarded_by,
                p.title as project_title
              FROM forward_tasks ft
              LEFT JOIN project_stages ps ON ft.stage_id = ps.id
              LEFT JOIN project_substages pss ON ft.substage_id = pss.id
              LEFT JOIN users u ON ft.forwarded_by = u.id
              LEFT JOIN projects p ON ft.project_id = p.id
              WHERE ft.forwarded_to = ?
              ORDER BY ft.created_at DESC";
              
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $tasks = [];
    
    while ($row = $result->fetch_assoc()) {
        // Format the task data
        $taskData = [
            'id' => $row['id'],
            'project_id' => $row['project_id'],
            'project_title' => $row['project_title'] ?? 'Untitled Project',
            'type' => $row['type'] ?? 'Unknown',
            'status' => $row['forward_status'] ?? 'Pending',
            'forwarded_by' => $row['forwarded_by'] ?? 'Unknown User',
            'forwarded_at' => $row['forwarded_at'] ? 
                date('M d, Y h:i A', strtotime($row['forwarded_at'])) : 
                'Unknown Date',
            'details' => ''
        ];

        // Build the details based on type (stage or substage)
        if ($row['substage_id']) {
            $taskData['details'] = sprintf(
                "Substage %s.%s: %s",
                $row['stage_number'] ?? '?',
                $row['substage_number'] ?? '?',
                $row['substage_title'] ?? 'Untitled'
            );
            $taskData['task_status'] = $row['substage_status'] ?? 'Unknown';
        } else if ($row['stage_id']) {
            $taskData['details'] = sprintf(
                "Stage %s",
                $row['stage_number'] ?? '?'
            );
            $taskData['task_status'] = $row['stage_status'] ?? 'Unknown';
        }

        $tasks[] = $taskData;
    }
    
    // Clear any output and send JSON response
    ob_clean();
    echo json_encode(['success' => true, 'data' => $tasks], JSON_THROW_ON_ERROR);
    
} catch (Exception $e) {
    // Log the error
    error_log("Forwarded tasks error: " . $e->getMessage());
    
    // Clear any output and send error response
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Internal server error',
        'debug_message' => $e->getMessage() // Remove this in production
    ], JSON_THROW_ON_ERROR);
} finally {
    // End output buffering
    ob_end_flush();
}

// Close database connection
$conn->close(); 