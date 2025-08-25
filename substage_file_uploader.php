<?php
/**
 * API Endpoint for uploading files to project substages
 * This file handles the file upload process for substages
 */

session_start();
require_once 'config/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting to catch all issues
ini_set('display_errors', 1);
error_reporting(E_ALL);
$logFile = 'substage_upload_log.txt';

// Added debug headers to help identify server environment
header('X-Debug-PHP-Version: ' . PHP_VERSION);
header('X-Debug-Server-Software: ' . $_SERVER['SERVER_SOFTWARE']);
header('X-Debug-Max-Upload: ' . ini_get('upload_max_filesize'));
header('X-Debug-Post-Max: ' . ini_get('post_max_size'));
header('X-Debug-Memory-Limit: ' . ini_get('memory_limit'));

function logDebug($message) {
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

logDebug("=== New upload attempt ===");
logDebug("Server environment: " . PHP_OS . " / PHP " . PHP_VERSION);
logDebug("POST data: " . print_r($_POST, true));
logDebug("FILES data: " . print_r($_FILES, true));
logDebug("Upload max filesize: " . ini_get('upload_max_filesize'));
logDebug("Post max size: " . ini_get('post_max_size'));
logDebug("Memory limit: " . ini_get('memory_limit'));

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        logDebug("User not authenticated - session data: " . print_r($_SESSION, true));
        throw new Exception('User not authenticated');
    }

    // Validate substage_id
    if (!isset($_POST['substage_id'])) {
        logDebug("Missing substage_id - complete POST data: " . print_r($_POST, true));
        throw new Exception('Substage ID is required');
    }

    // Validate file name
    if (!isset($_POST['file_name']) || empty($_POST['file_name'])) {
        logDebug("Missing file_name - complete POST data: " . print_r($_POST, true));
        throw new Exception('File name is required');
    }

    // Check if file was uploaded
    if (!isset($_FILES['file'])) {
        logDebug("No file in request - FILES array: " . print_r($_FILES, true));
        throw new Exception('No file uploaded');
    }
    
    // Check for specific upload errors
    if (isset($_FILES['file']['error']) && $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = array(
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        );
        $errorMessage = isset($uploadErrors[$_FILES['file']['error']]) ? 
                        $uploadErrors[$_FILES['file']['error']] : 
                        'Unknown upload error code: ' . $_FILES['file']['error'];
        
        logDebug("File upload error: " . $errorMessage);
        throw new Exception($errorMessage);
    }

    $substageId = $_POST['substage_id'];
    $fileName = $_POST['file_name'];
    $uploadedBy = $_SESSION['user_id'];
    $file = $_FILES['file'];

    // Check if file has content (size > 0)
    if ($file['size'] <= 0) {
        logDebug("File size is zero or negative: {$file['size']} bytes");
        throw new Exception('The uploaded file is empty (0 bytes)');
    }

    logDebug("Substage ID: $substageId, File name: $fileName, User ID: $uploadedBy");
    logDebug("File details - Name: {$file['name']}, Type: {$file['type']}, Size: {$file['size']} bytes, Temp path: {$file['tmp_name']}");

    // Verify temp file exists and has content
    if (!file_exists($file['tmp_name'])) {
        logDebug("Temp file doesn't exist: {$file['tmp_name']}");
        throw new Exception('The uploaded file was not received properly (temp file missing)');
    }
    
    if (filesize($file['tmp_name']) <= 0) {
        logDebug("Temp file is empty: {$file['tmp_name']}");
        throw new Exception('The uploaded file has no content (temp file empty)');
    }
    
    logDebug("Temp file verified - exists and has size: " . filesize($file['tmp_name']) . " bytes");

    // Validate substage exists and get project_id and stage_id
    try {
        $stmt = $pdo->prepare("SELECT ps.id, ps.stage_id, pst.project_id 
                              FROM project_substages ps 
                              JOIN project_stages pst ON ps.stage_id = pst.id 
                              WHERE ps.id = ? AND ps.deleted_at IS NULL");
        $stmt->execute([$substageId]);
        $substageData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        logDebug("Substage query executed - data: " . print_r($substageData, true));
    } catch (PDOException $e) {
        logDebug("Database error getting substage data: " . $e->getMessage());
        logDebug("SQL State: " . $e->getCode());
        throw new Exception('Database error: ' . $e->getMessage());
    }
    
    if (!$substageData) {
        logDebug("Invalid substage ID: $substageId - no database record found");
        throw new Exception('Invalid substage');
    }

    // Get file information
    $fileType = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'dwg', 'dxf'];
    
    logDebug("File type: $fileType");
    
    // Validate file type
    if (!in_array(strtolower($fileType), $allowedTypes)) {
        logDebug("Invalid file type: $fileType. Allowed types: " . implode(', ', $allowedTypes));
        throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $allowedTypes));
    }

    // Create upload directory if it doesn't exist
    $uploadDir = 'uploads/substage_files/' . $substageId . '/';
    if (!file_exists($uploadDir)) {
        logDebug("Creating directory: $uploadDir");
        if (!mkdir($uploadDir, 0777, true)) {
            $error = error_get_last();
            logDebug("Failed to create directory: $uploadDir - Error: " . print_r($error, true));
            
            // Check directory permissions
            $parentDir = dirname($uploadDir);
            if (file_exists($parentDir)) {
                $perms = substr(sprintf('%o', fileperms($parentDir)), -4);
                logDebug("Parent directory exists with permissions: $perms");
            } else {
                logDebug("Parent directory doesn't exist: $parentDir");
            }
            
            throw new Exception('Failed to create upload directory. Please check server permissions.');
        }
        
        // Set directory permissions explicitly
        if (!chmod($uploadDir, 0777)) {
            logDebug("Warning: Could not chmod directory $uploadDir to 0777");
        }
    } else {
        $perms = substr(sprintf('%o', fileperms($uploadDir)), -4);
        logDebug("Directory already exists with permissions: $perms");
        
        // Test directory is writable
        if (!is_writable($uploadDir)) {
            logDebug("Directory is not writable: $uploadDir");
            throw new Exception('Upload directory is not writable. Please check server permissions.');
        }
    }

    // Generate a unique filename with current timestamp, random string and substage ID
    function generateUniqueFileName($originalName, $substageId) {
        $timestamp = time();
        $random = bin2hex(random_bytes(8)); // 16 characters of randomness
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $sanitizedName = preg_replace("/[^a-zA-Z0-9]/", "_", pathinfo($originalName, PATHINFO_FILENAME));
        $sanitizedName = substr($sanitizedName, 0, 30); // Limit length
        
        return sprintf(
            'substage_%d_%s_%s_%s.%s',
            $substageId,
            $timestamp,
            $random,
            $sanitizedName,
            $extension
        );
    }

    // Generate unique filename and path
    $uniqueFileName = generateUniqueFileName($file['name'], $substageId);
    $filePath = $uploadDir . $uniqueFileName;
    
    logDebug("Generated filename: $uniqueFileName");
    logDebug("File path: $filePath");

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        $moveError = error_get_last();
        logDebug("Failed to move uploaded file from {$file['tmp_name']} to $filePath");
        logDebug("Move error: " . print_r($moveError, true));
        
        // Try a fallback approach using copy then unlink
        if (copy($file['tmp_name'], $filePath)) {
            logDebug("Fallback: Successfully copied file using copy()");
            unlink($file['tmp_name']);
        } else {
            $copyError = error_get_last();
            logDebug("Fallback copy also failed: " . print_r($copyError, true));
            throw new Exception('Failed to save uploaded file. Server permissions issue.');
        }
    }
    
    // Verify the moved file exists and has content
    if (!file_exists($filePath)) {
        logDebug("Moved file doesn't exist: $filePath");
        throw new Exception('File was moved but is missing');
    }
    
    if (filesize($filePath) <= 0) {
        logDebug("Moved file is empty: $filePath");
        throw new Exception('File was moved but is empty');
    }
    
    logDebug("File moved successfully. File size: " . filesize($filePath) . " bytes");

    // Begin transaction
    $pdo->beginTransaction();
    logDebug("Database transaction started");

    try {
        // Log the database schema to debug
        try {
            $tableInfoStmt = $pdo->prepare("DESCRIBE substage_files");
            $tableInfoStmt->execute();
            $tableColumns = $tableInfoStmt->fetchAll(PDO::FETCH_ASSOC);
            logDebug("Table structure: " . print_r($tableColumns, true));
        } catch (PDOException $e) {
            logDebug("Notice: Could not get table structure: " . $e->getMessage());
        }
        
        // Insert file record into database
        $stmt = $pdo->prepare("
            INSERT INTO substage_files (
                substage_id, 
                file_name, 
                file_path, 
                type, 
                uploaded_by, 
                uploaded_at,
                status,
                created_at
            ) VALUES (
                :substage_id,
                :file_name,
                :file_path,
                :type,
                :uploaded_by,
                NOW(),
                'pending',
                NOW()
            )
        ");

        logDebug("Executing insert query with params: " . print_r([
            'substage_id' => $substageId,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'type' => $fileType,
            'uploaded_by' => $uploadedBy
        ], true));

        $stmt->execute([
            'substage_id' => $substageId,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'type' => $fileType,
            'uploaded_by' => $uploadedBy
        ]);

        // Get the inserted file ID
        $fileId = $pdo->lastInsertId();
        logDebug("Inserted file record with ID: $fileId");

        // Create file upload activity log using project_activity_log table
        $stmt = $pdo->prepare("
            INSERT INTO project_activity_log (
                project_id,
                stage_id,
                substage_id,
                activity_type,
                description,
                performed_by,
                performed_at
            ) VALUES (
                :project_id,
                :stage_id,
                :substage_id,
                'file_upload',
                :description,
                :performed_by,
                CURRENT_TIMESTAMP()
            )
        ");

        logDebug("Logging activity");
        $stmt->execute([
            'project_id' => $substageData['project_id'],
            'stage_id' => $substageData['stage_id'],
            'substage_id' => $substageId,
            'description' => "Uploaded file: {$fileName}",
            'performed_by' => $uploadedBy
        ]);

        // Commit transaction
        $pdo->commit();
        logDebug("Transaction committed successfully");

        // Return success response
        $response = [
            'success' => true,
            'message' => 'File uploaded successfully',
            'file' => [
                'id' => $fileId,
                'name' => $fileName,
                'path' => $filePath,
                'type' => $fileType
            ]
        ];
        
        logDebug("Sending success response: " . json_encode($response));
        echo json_encode($response);

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        logDebug("Transaction rolled back due to error: " . $e->getMessage());
        logDebug("Stack trace: " . $e->getTraceAsString());
        
        // Delete uploaded file if database insertion fails
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                logDebug("Deleted file: $filePath");
            } else {
                $unlinkError = error_get_last();
                logDebug("Failed to delete file: $filePath - Error: " . print_r($unlinkError, true));
            }
        }
        
        throw $e;
    }

} catch (Exception $e) {
    // Return error response
    logDebug("Error: " . $e->getMessage());
    logDebug("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'max_upload' => ini_get('upload_max_filesize'),
            'post_max' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'time' => date('Y-m-d H:i:s')
        ]
    ];
    logDebug("Sending error response: " . json_encode($errorResponse));
    echo json_encode($errorResponse);
}
?> 