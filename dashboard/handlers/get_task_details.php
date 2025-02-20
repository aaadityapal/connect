<?php
// Prevent any output buffering
ob_clean();

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Disable error display
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Function to clean output
function cleanForJSON($value) {
    if (is_string($value)) {
        return trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value));
    }
    return $value;
}

try {
    require_once '../../config/db_connect.php';
    
    if (!isset($_GET['task_id'])) {
        throw new Exception('Task ID is required');
    }

    $taskId = intval($_GET['task_id']);
    
    if (!$taskId) {
        throw new Exception('Invalid task ID');
    }

    // Verify database connection
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Get project details with creator and assigned_to information
    $sql = "SELECT 
            p.id,
            p.title,
            p.description,
            p.project_type,
            p.start_date,
            p.end_date,
            p.status,
            creator.id as created_by_id,
            creator.username as created_by_username,
            assigned.id as assigned_to_id,
            assigned.username as assigned_to_username
        FROM projects p 
        LEFT JOIN users creator ON p.created_by = creator.id
        LEFT JOIN users assigned ON p.assigned_to = assigned.id
        WHERE p.id = ? AND p.deleted_at IS NULL";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param('i', $taskId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $task = $result->fetch_assoc();

    if (!$task) {
        throw new Exception('Project not found');
    }

    // After fetching the main project details, fetch stages
    $stagesQuery = "SELECT 
        s.*,
        u.username as assigned_to_username 
    FROM project_stages s
    LEFT JOIN users u ON s.assigned_to = u.id
    WHERE s.project_id = ? AND s.deleted_at IS NULL
    ORDER BY s.stage_number";

    $stagesStmt = $conn->prepare($stagesQuery);
    $stagesStmt->bind_param('i', $taskId);
    $stagesStmt->execute();
    $stagesResult = $stagesStmt->get_result();
    $stages = [];

    while ($stage = $stagesResult->fetch_assoc()) {
        // Fetch substages for each stage
        $substagesQuery = "SELECT 
            ss.*,
            u.username as assigned_to_username
        FROM project_substages ss
        LEFT JOIN users u ON ss.assigned_to = u.id
        WHERE ss.stage_id = ? AND ss.deleted_at IS NULL
        ORDER BY ss.substage_number";
        
        $substagesStmt = $conn->prepare($substagesQuery);
        $substagesStmt->bind_param('i', $stage['id']);
        $substagesStmt->execute();
        $substagesResult = $substagesStmt->get_result();
        
        $stage['substages'] = [];
        while ($substage = $substagesResult->fetch_assoc()) {
            $stage['substages'][] = $substage;
        }
        
        $stages[] = $stage;
    }

    // Add stages to the response
    $task['stages'] = $stages;

    // After fetching stages and substages, fetch files
    $filesQuery = "SELECT 
        f.*,
        u.username as uploaded_by_username
    FROM project_files f
    LEFT JOIN users u ON f.uploaded_by = u.id
    WHERE f.project_id = ? 
        AND f.deleted_at IS NULL
    ORDER BY f.created_at DESC";

    $filesStmt = $conn->prepare($filesQuery);
    $filesStmt->bind_param('i', $taskId);
    $filesStmt->execute();
    $filesResult = $filesStmt->get_result();

    // Group files by stage and substage
    $files = [];
    while ($file = $filesResult->fetch_assoc()) {
        $stageId = $file['stage_id'];
        $substageId = $file['substage_id'];
        
        if ($stageId) {
            if (!isset($files['stages'][$stageId])) {
                $files['stages'][$stageId] = [];
            }
            $files['stages'][$stageId][] = $file;
        } else if ($substageId) {
            if (!isset($files['substages'][$substageId])) {
                $files['substages'][$substageId] = [];
            }
            $files['substages'][$substageId][] = $file;
        } else {
            if (!isset($files['project'])) {
                $files['project'] = [];
            }
            $files['project'][] = $file;
        }
    }

    // Add files to the response
    $task['files'] = $files;

    // Clean the file data to ensure JSON-safe strings
    foreach ($task['files'] as &$file) {
        if (isset($file['file_name'])) {
            $file['file_name'] = mb_convert_encoding($file['file_name'], 'UTF-8', 'UTF-8');
            $file['file_name'] = htmlspecialchars_decode($file['file_name'], ENT_QUOTES);
        }
        
        // Format the upload date
        if (isset($file['uploaded_at'])) {
            $file['uploaded_at'] = date('Y-m-d H:i:s', strtotime($file['uploaded_at']));
        }
        
        // Add file location context
        $file['location'] = '';
        if ($file['stage_number']) {
            $file['location'] = "Stage " . $file['stage_number'];
            if ($file['substage_number']) {
                $file['location'] .= " / Substage " . $file['substage_number'] . 
                                   ($file['substage_title'] ? " - " . $file['substage_title'] : '');
            }
        } else {
            $file['location'] = "Project Level";
        }
    }

    // Clean the data to ensure JSON-safe strings
    array_walk_recursive($task, function(&$item) {
        $item = cleanForJSON($item);
    });

    // Ensure clean output
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Send JSON response
    echo json_encode([
        'status' => 'success',
        'task' => $task
    ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;

} catch (Exception $e) {
    // Clean any output before error response
    while (ob_get_level()) {
        ob_end_clean();
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => DEBUG_MODE ? [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ] : null
    ]);
    exit;
}

