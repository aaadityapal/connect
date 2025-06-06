<?php
/**
 * Generic Video Streaming/Download Handler
 * Serves video files from various possible locations with support for streaming or downloading
 */

// Check if this is a download request
$isDownload = isset($_GET['download']) && $_GET['download'] == 1;

// Get the requested file name from URL parameter
$requestedFile = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($requestedFile)) {
    header("HTTP/1.0 400 Bad Request");
    echo "Error: No file specified";
    exit;
}

// Log request for debugging
error_log("Generic video request: " . $requestedFile . ($isDownload ? " (Download)" : " (Stream)"));

// Define common video paths in the system
$possiblePaths = [];

// Known video directories
$videoDirs = [
    "uploads/videos/",
    "uploads/work_progress/",
    "uploads/inventory_videos/",
    "uploads/site_media/",
    "uploads/calendar_events/",
    "uploads/"
];

// Search each directory
foreach ($videoDirs as $dir) {
    $possiblePaths[] = $dir . $requestedFile;
}

// Add calendar event specific paths - for work progress
for ($i = 1; $i <= 20; $i++) {
    $possiblePaths[] = "uploads/calendar_events/work_progress_media/work_{$i}/{$requestedFile}";
}

// Add calendar event specific paths - for inventory
for ($i = 1; $i <= 10; $i++) {
    $possiblePaths[] = "uploads/calendar_events/inventory_media/inventory_{$i}/{$requestedFile}";
}

// Log path search
error_log("Searching for video in paths: " . implode(", ", $possiblePaths));

// Try to find the video in one of the possible paths
$videoFound = false;
foreach ($possiblePaths as $videoPath) {
    if (file_exists($videoPath)) {
        // Double check if it's a valid file
        if (is_file($videoPath) && filesize($videoPath) > 0) {
            $filesize = filesize($videoPath);
            $file_extension = pathinfo($videoPath, PATHINFO_EXTENSION);
            
            error_log("Found valid video at: $videoPath");
            error_log("File size: $filesize bytes");
            error_log("File extension: $file_extension");
            
            // Set appropriate headers based on whether this is a download or stream
            if ($isDownload) {
                // Headers for download
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $requestedFile . '"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
            } else {
                // Headers for streaming - use appropriate content type based on extension
                $contentType = 'video/mp4'; // Default to MP4
                
                if ($file_extension == 'webm') {
                    $contentType = 'video/webm';
                } elseif ($file_extension == 'ogg' || $file_extension == 'ogv') {
                    $contentType = 'video/ogg';
                } elseif ($file_extension == 'mov') {
                    $contentType = 'video/quicktime';
                } elseif ($file_extension == 'avi') {
                    $contentType = 'video/x-msvideo';
                } elseif ($file_extension == 'wmv') {
                    $contentType = 'video/x-ms-wmv';
                } elseif ($file_extension == 'flv') {
                    $contentType = 'video/x-flv';
                }
                
                header("Content-Type: $contentType");
                header('Accept-Ranges: bytes');
            }
            
            // Set content length header
            header("Content-Length: $filesize");
            
            // Output the file
            readfile($videoPath);
            $videoFound = true;
            break;
        } else {
            error_log("Path exists but is not a valid file: $videoPath");
        }
    }
}

// If no video was found in any path
if (!$videoFound) {
    header("HTTP/1.0 404 Not Found");
    echo "Video file not found: $requestedFile";
    error_log("Video not found in any of the checked paths.");
}
?> 