<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Check if user has the 'Site Supervisor' role
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Site Supervisor') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Check if event_id is provided
if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid event ID']);
    exit();
}

$event_id = intval($_GET['event_id']);
$response = ['success' => false];

try {
    // Function to fetch work progress data
    function fetchWorkProgress($conn, $event_id) {
        $progress = [];
        
        $query = "SELECT * FROM sv_work_progress WHERE event_id = ? ORDER BY sequence_number ASC";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($work = $result->fetch_assoc()) {
            // Fetch media files for this work progress
            $work['media'] = fetchWorkProgressMedia($conn, $work['work_id']);
            $progress[] = $work;
        }
        
        $stmt->close();
        return $progress;
    }

    // Function to fetch work progress media
    function fetchWorkProgressMedia($conn, $work_id) {
        $media = [];
        
        $query = "SELECT * FROM sv_work_progress_media WHERE work_id = ? ORDER BY sequence_number ASC";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $work_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($file = $result->fetch_assoc()) {
            // Make sure we have the correct file path for each media file
            if (!empty($file['file_name'])) {
                if (empty($file['file_path'])) {
                    // Construct the full path if it's not already stored
                    $baseDir = 'uploads/work_progress/';
                    
                    // Check if file is in a subdirectory
                    if (!empty($file['work_id'])) {
                        // If organized by work_id
                        if (is_dir($baseDir . $file['work_id'])) {
                            $file['file_path'] = $baseDir . $file['work_id'] . '/' . $file['file_name'];
                        } else {
                            $file['file_path'] = $baseDir . $file['file_name'];
                        }
                    } else {
                        $file['file_path'] = $baseDir . $file['file_name'];
                    }
                }
            }
            
            // Check if file exists (for debugging)
            $file['file_exists'] = false;
            if (!empty($file['file_path'])) {
                $file['file_exists'] = file_exists($file['file_path']);
            } elseif (!empty($file['file_name'])) {
                $defaultPath = 'uploads/work_progress/' . $file['file_name'];
                $file['file_exists'] = file_exists($defaultPath);
                $file['default_path_checked'] = $defaultPath;
            }
            
            $media[] = $file;
        }
        
        $stmt->close();
        return $media;
    }

    // Fetch work progress data
    $work_progress = fetchWorkProgress($conn, $event_id);
    
    // List upload directories and check if they exist
    $directories = [
        'uploads/work_progress/',
        'uploads/work_images/',
        'uploads/inventory_images/',
        'uploads/inventory_bills/',
        'uploads/inventory_videos/',
        'uploads/inventory/'
    ];
    
    $dirInfo = [];
    foreach ($directories as $dir) {
        $dirInfo[$dir] = [
            'exists' => is_dir($dir),
            'readable' => is_readable($dir),
            'writable' => is_writable($dir)
        ];
        
        if (is_dir($dir)) {
            $files = scandir($dir);
            $dirInfo[$dir]['files'] = array_slice($files, 0, 10); // Show first 10 files for brevity
            $dirInfo[$dir]['file_count'] = count($files) - 2; // Subtracting . and ..
        }
    }
    
    $response = [
        'success' => true,
        'work_progress' => $work_progress,
        'directory_info' => $dirInfo
    ];
    
} catch (Exception $e) {
    error_log("Error debugging media files: " . $e->getMessage());
    $response = [
        'success' => false,
        'error' => 'An error occurred: ' . $e->getMessage()
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response, JSON_PRETTY_PRINT);
exit(); 