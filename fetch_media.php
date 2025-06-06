<?php
/**
 * Fetch Media File
 * 
 * This script retrieves media files (images, videos, PDFs) from the database
 * and serves them to the user with proper content type headers.
 * 
 * Usage: fetch_media.php?id=123&type=work_progress
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
    echo json_encode(['error' => 'Authentication required']);
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
    echo json_encode(['error' => 'Invalid media ID']);
    exit;
}

$mediaId = (int)$_GET['id'];
$mediaType = isset($_GET['type']) ? $_GET['type'] : 'work_progress';

// Validate media type
if (!in_array($mediaType, ['work_progress', 'inventory'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid media type']);
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
    echo json_encode(['error' => 'Media not found']);
    exit;
}

// Get media data
$media = $result->fetch_assoc();
$stmt->close();

debug_log('Media record from database', $media);

// Check if file_content exists in the database (some systems store the actual file in the DB)
$hasFileContent = false;
if (isset($media['file_content']) && !empty($media['file_content'])) {
    $hasFileContent = true;
    debug_log('File content found in database');
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

// Check if file exists
$originalFilePath = $media['file_path'];
$filePath = normalizePath($originalFilePath);
$fileName = basename($filePath);
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$fileFound = file_exists($filePath);

debug_log('Initial file check', [
    'originalFilePath' => $originalFilePath,
    'normalizedPath' => $filePath,
    'fileName' => $fileName,
    'fileExtension' => $fileExtension,
    'fileExists' => $fileFound
]);

if (!$fileFound) {
    // Try alternate paths if file doesn't exist at the stored path
    $alternativePaths = [];
    $triedPaths = [];
    
    if ($mediaType === 'work_progress') {
        $workId = $media['work_id'];
        $alternativePaths = [
            "uploads/calendar_events/work_progress_media/work_{$workId}/{$fileName}",
            "uploads/calendar_events/work_progress_media/{$fileName}",
            "uploads/work_progress_media/{$fileName}",
            "uploads/work_progress/{$fileName}",
            // Production path variations
            "uploads/work_progress/work_{$workId}/{$fileName}",
            "../uploads/work_progress/work_{$workId}/{$fileName}",
            "../uploads/calendar_events/work_progress_media/work_{$workId}/{$fileName}",
            "../uploads/work_progress_media/{$fileName}"
        ];
    } else {
        $inventoryId = $media['inventory_id'];
        
        // Build a comprehensive list of possible paths
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
            
            // Media type specific paths
            "uploads/inventory_images/{$fileName}",
            "uploads/inventory_videos/{$fileName}",
            "uploads/inventory_bills/{$fileName}",
            "uploads/inventory_docs/{$fileName}",
            
            // PDF specific paths
            "uploads/bills/{$fileName}",
            "uploads/pdfs/{$fileName}",
            "uploads/documents/{$fileName}",
            
            // Production paths
            "../uploads/inventory_media/inventory_{$inventoryId}/{$fileName}",
            "../uploads/inventory_media/{$fileName}",
            "../uploads/inventory/{$fileName}"
        ];
        
        // For PDF files, add more specific bill paths
        if ($fileExtension === 'pdf') {
            $additionalPaths = [
                "uploads/inventory_bills/{$fileName}",
                "uploads/bills/{$fileName}",
                "uploads/invoices/{$fileName}",
                "uploads/receipts/{$fileName}",
                
                // Try different date-based subdirectories
                "uploads/inventory_bills/" . date('Y') . "/{$fileName}",
                "uploads/inventory_bills/" . date('Y/m') . "/{$fileName}",
                "uploads/bills/" . date('Y') . "/{$fileName}",
                "uploads/bills/" . date('Y/m') . "/{$fileName}",
                
                // Try with inventory ID prefix paths
                "uploads/inventory_bills/inventory_{$inventoryId}/{$fileName}",
                "uploads/bills/inventory_{$inventoryId}/{$fileName}",
                
                // Production paths
                "../uploads/inventory_bills/{$fileName}",
                "../uploads/bills/{$fileName}"
            ];
            $alternativePaths = array_merge($additionalPaths, $alternativePaths);
        }
    }
    
    // Normalize all paths for the current environment
    foreach ($alternativePaths as $i => $path) {
        $alternativePaths[$i] = normalizePath($path);
    }
    
    debug_log('Checking alternative paths', $alternativePaths);
    
    // Check alternative paths
    foreach ($alternativePaths as $path) {
        $triedPaths[] = $path;
        if (file_exists($path)) {
            $filePath = $path;
            $fileFound = true;
            debug_log("File found at alternative path: $path");
            break;
        }
    }
    
    // If still not found and it's a PDF, try recursive search in key directories
    if (!$fileFound && $fileExtension === 'pdf') {
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
    
    if (!$fileFound) {
        // For PDF files specifically, try to find any PDF file with a similar name
        if ($fileExtension === 'pdf') {
            $baseName = pathinfo($fileName, PATHINFO_FILENAME);
            $possibleDirectories = [
                "uploads/inventory_bills/",
                "uploads/bills/",
                "uploads/invoices/",
                "uploads/pdfs/",
                "uploads/documents/",
                "uploads/"
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
        
        // If we're looking for images/videos, try a more aggressive search
        if (!$fileFound && in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'ogg'])) {
            // Search in common media directories recursively
            $mediaSearchDirs = [
                'uploads',
                '../uploads',
                'uploads/calendar_events',
                '../uploads/calendar_events'
            ];
            
            // Normalize directories
            foreach ($mediaSearchDirs as $i => $dir) {
                $mediaSearchDirs[$i] = normalizePath($dir);
            }
            
            debug_log('Deep search for media files', $mediaSearchDirs);
            
            // Try to find the exact file
            foreach ($mediaSearchDirs as $dir) {
                $foundPath = findFileRecursive($dir, $fileName, 4);
                if ($foundPath) {
                    $filePath = $foundPath;
                    $fileFound = true;
                    debug_log("Media file found with deep search: $foundPath");
                    break;
                }
            }
            
            // If exact file not found, try to find a file with a similar name pattern
            // This is useful for files with timestamps that might be slightly different
            if (!$fileFound) {
                $baseName = pathinfo($fileName, PATHINFO_FILENAME);
                // Remove any timestamp patterns for fuzzy matching
                $baseNameNoTimestamp = preg_replace('/(_|\-)[0-9]{10,}/', '', $baseName);
                
                debug_log("Searching for similar media files with pattern: $baseNameNoTimestamp");
                
                foreach ($mediaSearchDirs as $dir) {
                    if (!is_dir($dir)) continue;
                    
                    $foundPath = null;
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
                    );
                    
                    foreach ($iterator as $file) {
                        if (!$file->isFile()) continue;
                        
                        $currentFileName = $file->getFilename();
                        $currentFileExt = strtolower(pathinfo($currentFileName, PATHINFO_EXTENSION));
                        
                        // Check if extension matches what we're looking for
                        if ($currentFileExt === $fileExtension) {
                            // Check if filename contains our base pattern (ignoring timestamps)
                            if (stripos($currentFileName, $baseNameNoTimestamp) !== false) {
                                $foundPath = $file->getPathname();
                                debug_log("Found similar media file: $foundPath");
                                break 2;
                            }
                        }
                    }
                    
                    if ($foundPath) {
                        $filePath = $foundPath;
                        $fileFound = true;
                        break;
                    }
                }
            }
        }
        
        // Log the error for debugging if still not found
        if (!$fileFound) {
            debug_log("File not found after all attempts", [
                "Original path" => $originalFilePath,
                "Normalized path" => $filePath,
                "File name" => $fileName,
                "Tried paths" => $triedPaths
            ]);
            
            // If we have file content in the database and file wasn't found on disk
            if (isset($media['file_content']) && !empty($media['file_content'])) {
                debug_log("Serving file from database content");
                // Output file with appropriate headers from database content
                header('Content-Type: ' . getContentType($fileExtension));
                header('Content-Length: ' . strlen($media['file_content']));
                header('Content-Disposition: inline; filename="' . $fileName . '"');
                header('Cache-Control: public, max-age=86400');
                echo $media['file_content'];
                exit;
            }
            
            // Return error if file not found anywhere
            if ($debugMode) {
                echo "<h2>File Not Found</h2>";
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
                    'tried_paths' => $triedPaths
                ]);
            }
            exit;
        }
    }
}

// Helper function to get content type
function getContentType($fileExtension) {
    switch ($fileExtension) {
        case 'jpg':
        case 'jpeg':
            return 'image/jpeg';
        case 'png':
            return 'image/png';
        case 'gif':
            return 'image/gif';
        case 'mp4':
            return 'video/mp4';
        case 'webm':
            return 'video/webm';
        case 'ogg':
            return 'video/ogg';
        case 'pdf':
            return 'application/pdf';
        default:
            return 'application/octet-stream';
    }
}

// Log the final path before serving
debug_log("Serving file", [
    "Final path" => $filePath,
    "Content type" => getContentType($fileExtension)
]);

// Set content type based on file extension
$contentType = getContentType($fileExtension);

// Output file from disk with appropriate headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Cache-Control: public, max-age=86400');
readfile($filePath);
exit; 