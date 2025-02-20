<?php
// Prevent any output before headers
ob_start();

// Correct the path to db_connect.php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_validate.php';

// Clear any previous output
ob_clean();

// Set proper JSON headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

try {
    // Validate input parameters
    if (!isset($_GET['task_id']) || !isset($_GET['task_type'])) {
        throw new Exception('Missing required parameters');
    }

    $taskId = filter_var($_GET['task_id'], FILTER_SANITIZE_NUMBER_INT);
    $taskType = filter_var($_GET['task_type'], FILTER_SANITIZE_STRING);
    
    // Use the global $conn variable from db_connect.php
    global $conn;
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get project details using mysqli
    $projectQuery = "SELECT p.*, u.username as assigned_to 
                    FROM projects p 
                    LEFT JOIN users u ON p.assigned_to = u.id 
                    WHERE p.id = ?";
    
    $stmt = $conn->prepare($projectQuery);
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $result = $stmt->get_result();
    $projectData = $result->fetch_assoc();
    
    if (!$projectData) {
        throw new Exception('Project not found');
    }

    // Initialize substage data
    $substage = null;

    // If task type is substage, get substage details
    if ($taskType === 'substage') {
        $substageQuery = "SELECT * FROM substages WHERE id = ?";
        $stmt = $conn->prepare($substageQuery);
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        $result = $stmt->get_result();
        $substage = $result->fetch_assoc();
    }

    // Format the response
    $response = [
        'success' => true,
        'project' => [
            'title' => htmlspecialchars_decode(strip_tags($projectData['title'])),
            'status' => htmlspecialchars_decode(strip_tags($projectData['status'])),
            'start_date' => $projectData['start_date'],
            'end_date' => $projectData['end_date'],
            'assigned_to' => htmlspecialchars_decode(strip_tags($projectData['assigned_to']))
        ]
    ];

    // Add substage data if available
    if ($substage) {
        $response['substage'] = [
            'title' => htmlspecialchars_decode(strip_tags($substage['title'])),
            'substage_number' => (int)$substage['substage_number'],
            'status' => htmlspecialchars_decode(strip_tags($substage['status'])),
            'start_date' => $substage['start_date'],
            'end_date' => $substage['end_date']
        ];
    }

    // Ensure clean JSON output
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    // Log the error
    error_log('Task Details Error: ' . $e->getMessage());
    
    // Send error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Flush output buffer
ob_end_flush();
?> 