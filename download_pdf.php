<?php
/**
 * Alternative PDF Download Script
 * 
 * This script provides an alternative method to download PDF files
 * It forces the file to be downloaded rather than displayed inline
 * and includes additional path checking logic
 * 
 * Usage: download_pdf.php?id=123&type=inventory
 * Parameters:
 *   - id: The ID of the media file
 *   - type: The type of media (work_progress, inventory)
 *   - debug: Set to 1 to view debug information (optional)
 */

// Start session for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Authentication required";
    exit;
}

// Include database connection
require_once('includes/db_connect.php');

// Determine if we're in debug mode
$debugMode = isset($_GET['debug']) && $_GET['debug'] == 1;

// Log function for debugging
function debug_log($message, $data = null) {
    global $debugMode;
    if ($debugMode) {
        echo "<p><strong>DEBUG:</strong> $message</p>";
        if ($data !== null) {
            echo "<pre>" . print_r($data, true) . "</pre>";
        }
    } else {
        error_log($message . ($data !== null ? ': ' . json_encode($data) : ''));
    }
}

// Detect server environment
$isProduction = false;
$serverName = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
$serverAddr = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '';

if (strpos($serverName, 'localhost') === false && 
    strpos($serverName, '127.0.0.1') === false && 
    strpos($serverAddr, '127.0.0.1') === false) {
    $isProduction = true;
}

debug_log('Server Environment', [
    'isProduction' => $isProduction,
    'serverName' => $serverName,
    'documentRoot' => $documentRoot
]);

// Validate parameters
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo "Invalid media ID";
    exit;
}

$mediaId = (int)$_GET['id'];
$mediaType = isset($_GET['type']) ? $_GET['type'] : 'inventory';

// Validate media type
if (!in_array($mediaType, ['work_progress', 'inventory'])) {
    http_response_code(400);
    echo "Invalid media type";
    exit;
}

// Prepare query based on media type
if ($mediaType === 'work_progress') {
    $table = 'sv_work_progress_media';
} else {
    $table = 'sv_inventory_media';
}

// Query to get media details
$stmt = $conn->prepare("SELECT * FROM $table WHERE media_id = ?");
$stmt->bind_param('i', $mediaId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo "Media not found";
    exit;
}

// Get media data
$media = $result->fetch_assoc();
$stmt->close();

debug_log('Media record from database', $media);

// Check if we have file content in the database
$hasFileContent = false;
if (isset($media['file_content']) && !empty($media['file_content'])) {
    $hasFileContent = true;
    debug_log('File content found in database');
}

// Get filename and check if it's a PDF
$fileName = $media['file_name'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if ($fileExtension !== 'pdf') {
    http_response_code(400);
    echo "Not a PDF file";
    exit;
}

// Function to recursively search for a file in a directory
function findFileRecursive($baseDir, $targetFile, $maxDepth = 3, $currentDepth = 0) {
    global $debugMode;
    
    if ($currentDepth > $maxDepth) return null;
    
    if (!is_dir($baseDir)) {
        if ($debugMode) debug_log("Not a directory: $baseDir");
        return null;
    }
    
    $items = scandir($baseDir);
    if (!$items) {
        if ($debugMode) debug_log("Could not scan directory: $baseDir");
        return null;
    }
    
    if ($debugMode && $currentDepth == 0) {
        debug_log("Scanning directory: $baseDir", $items);
    }
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $baseDir . '/' . $item;
        
        // Check if this is the file we're looking for
        if (is_file($path) && $item === $targetFile) {
            debug_log("Found file: $path");
            return $path;
        }
        
        // If it's a directory, search recursively
        if (is_dir($path)) {
            $result = findFileRecursive($path, $targetFile, $maxDepth, $currentDepth + 1);
            if ($result) return $result;
        }
    }
    
    return null;
}

// Function to normalize file paths for the current environment
function normalizePath($path) {
    global $isProduction, $documentRoot;
    
    // Remove any leading '../' for production
    if ($isProduction) {
        $path = ltrim($path, './');
        
        // If this is a relative path and we have a document root, make it absolute
        if (strpos($path, '/') !== 0 && !empty($documentRoot)) {
            // Make sure we don't have double slashes
            if (substr($documentRoot, -1) === '/') {
                $path = $documentRoot . $path;
            } else {
                $path = $documentRoot . '/' . $path;
            }
        }
    }
    
    return $path;
}

// Check if file exists at the stored path
$originalFilePath = $media['file_path'];
$filePath = normalizePath($originalFilePath);
$fileFound = file_exists($filePath);

debug_log('Initial file check', [
    'originalFilePath' => $originalFilePath,
    'normalizedPath' => $filePath,
    'fileExists' => $fileFound
]);

// If file not found at the stored path, try alternative paths
if (!$fileFound) {
    $inventoryId = isset($media['inventory_id']) ? $media['inventory_id'] : '';
    
    $alternativePaths = [
        // Path from original error (exact path pattern)
        "../uploads/calendar_events/inventory_bills/inventory_{$inventoryId}/{$fileName}",
        "uploads/calendar_events/inventory_bills/inventory_{$inventoryId}/{$fileName}",
        "../uploads/calendar_events/inventory_bills/{$fileName}",
        "uploads/calendar_events/inventory_bills/{$fileName}",
        
        // Standard inventory paths
        "uploads/calendar_events/inventory_media/inventory_{$inventoryId}/{$fileName}",
        "uploads/calendar_events/inventory_media/{$fileName}",
        "uploads/inventory_media/{$fileName}",
        "uploads/inventory/{$fileName}",
        
        // PDF specific paths
        "uploads/inventory_bills/{$fileName}",
        "uploads/bills/{$fileName}",
        "uploads/invoices/{$fileName}",
        "uploads/receipts/{$fileName}",
        "uploads/pdfs/{$fileName}",
        "uploads/documents/{$fileName}",
        
        // Date-based subdirectories
        "uploads/inventory_bills/" . date('Y') . "/{$fileName}",
        "uploads/inventory_bills/" . date('Y/m') . "/{$fileName}",
        "uploads/bills/" . date('Y') . "/{$fileName}",
        "uploads/bills/" . date('Y/m') . "/{$fileName}",
        
        // Try with inventory ID prefix paths
        "uploads/inventory_bills/inventory_{$inventoryId}/{$fileName}",
        "uploads/bills/inventory_{$inventoryId}/{$fileName}",
        
        // Production paths
        "../uploads/inventory_bills/{$fileName}",
        "../uploads/bills/{$fileName}",
        "../uploads/inventory_media/{$fileName}",
        "../uploads/inventory_media/inventory_{$inventoryId}/{$fileName}"
    ];
    
    // Normalize all paths for the current environment
    foreach ($alternativePaths as $i => $path) {
        $alternativePaths[$i] = normalizePath($path);
    }
    
    debug_log('Checking alternative paths', $alternativePaths);
    $triedPaths = [];
    
    foreach ($alternativePaths as $path) {
        $triedPaths[] = $path;
        if (file_exists($path)) {
            $filePath = $path;
            $fileFound = true;
            debug_log("File found at alternative path: $path");
            break;
        }
    }
    
    // If still not found, try to find a PDF with a similar name
    if (!$fileFound) {
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $possibleDirectories = [
            "uploads/inventory_bills/",
            "uploads/bills/",
            "uploads/invoices/",
            "uploads/pdfs/",
            "uploads/documents/",
            "uploads/",
            "../uploads/inventory_bills/",
            "../uploads/bills/",
            "../uploads/pdfs/",
            "../uploads/"
        ];
        
        // Normalize directories
        foreach ($possibleDirectories as $i => $dir) {
            $possibleDirectories[$i] = normalizePath($dir);
        }
        
        debug_log('Checking for similar files in directories', $possibleDirectories);
        
        foreach ($possibleDirectories as $dir) {
            if (is_dir($dir)) {
                $files = scandir($dir);
                foreach ($files as $file) {
                    if (stripos($file, $baseName) !== false && 
                        strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf') {
                        $filePath = $dir . $file;
                        $fileFound = true;
                        debug_log("Found similar file: " . $filePath);
                        break 2;
                    }
                }
            }
        }
    }
}

// If we have file content in the database and file wasn't found on disk
if (!$fileFound && $hasFileContent) {
    debug_log("Serving file from database content");
    // Output file with appropriate headers from database content
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($media['file_content']));
    header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
    header('Cache-Control: no-cache');
    echo $media['file_content'];
    exit;
}

// If file was found on disk
if ($fileFound) {
    debug_log("Serving file from disk: $filePath");
    // Output file with appropriate headers
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Cache-Control: no-cache');
    readfile($filePath);
    exit;
}

// If still not found, try recursive search in key directories
if (!$fileFound) {
    $searchDirs = [
        'uploads',
        '../uploads',
        'uploads/calendar_events',
        '../uploads/calendar_events',
        'uploads/inventory_bills',
        '../uploads/inventory_bills'
    ];
    
    // Normalize search directories
    foreach ($searchDirs as $i => $dir) {
        $searchDirs[$i] = normalizePath($dir);
    }
    
    debug_log('Recursive search in directories', $searchDirs);
    
    foreach ($searchDirs as $dir) {
        $foundPath = findFileRecursive($dir, $fileName, 3);
        if ($foundPath) {
            $filePath = $foundPath;
            $fileFound = true;
            debug_log("File found with recursive search: $foundPath");
            break;
        }
    }
}

// If file was found after recursive search
if ($fileFound) {
    debug_log("Serving file from disk after recursive search: $filePath");
    // Output file with appropriate headers
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Cache-Control: no-cache');
    readfile($filePath);
    exit;
}

// If we get here, the file was not found after all attempts
if ($debugMode) {
    echo "<h2>PDF File Not Found</h2>";
    echo "<p>Filename: $fileName</p>";
    echo "<p>Original path: $originalFilePath</p>";
    echo "<p>Normalized path: $filePath</p>";
    echo "<h3>Tried Paths:</h3>";
    echo "<ul>";
    foreach ($triedPaths as $path) {
        echo "<li>$path</li>";
    }
    echo "</ul>";
} else {
    http_response_code(404);
    echo json_encode([
        'error' => 'File not found',
        'file_name' => $fileName,
        'original_path' => $originalFilePath,
        'tried_paths' => $alternativePaths
    ]);
}
exit;