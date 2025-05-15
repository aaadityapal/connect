<?php
// Set headers for proper video serving
header('Content-Type: video/mp4');
header('Accept-Ranges: bytes');

// Get the requested file name from URL parameter
$requestedFile = isset($_GET['file']) ? $_GET['file'] : '';

// Log request for debugging
error_log("Work progress video request: " . $requestedFile);

// Define possible video paths focusing on work progress
$possiblePaths = [];

// Check in each work folder from 1 to 20
for ($i = 1; $i <= 20; $i++) {
    $possiblePaths[] = "uploads/calendar_events/work_progress_media/work_{$i}/{$requestedFile}";
}

// Also try direct paths
$possiblePaths[] = "uploads/work_progress/{$requestedFile}";
$possiblePaths[] = "uploads/{$requestedFile}";

// Log path search
error_log("Searching for work progress video in paths: " . implode(", ", $possiblePaths));

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
            
            // Set content length header
            header("Content-Length: $filesize");
            
            // Output the file
            readfile($videoPath);
            $videoFound = true;
            break;
        } else {
            error_log("Path exists but is not a valid file: $videoPath");
        }
    } else {
        error_log("File not found at: $videoPath");
    }
}

// If no video was found in any path
if (!$videoFound) {
    header("HTTP/1.0 404 Not Found");
    echo "Video file not found: $requestedFile";
    error_log("Work progress video not found in any of the checked paths.");
}
?> 