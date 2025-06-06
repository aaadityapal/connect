<?php
// Check if this is a download request
$isDownload = isset($_GET['download']) && $_GET['download'] == 1;

// Get the requested file name from URL parameter
$requestedFile = isset($_GET['file']) ? $_GET['file'] : '';

// Log request for debugging
error_log("Work progress video request: " . $requestedFile . ($isDownload ? " (Download)" : " (Stream)"));

// Define possible video paths focusing on work progress
$possiblePaths = [];

// Handle specific problem videos first
if ($requestedFile === '6841936e5d999_174912804.mp4') {
    // Try all potential locations for this specific file
    $possiblePaths[] = "uploads/videos/{$requestedFile}";
    for ($i = 1; $i <= 20; $i++) {
        $possiblePaths[] = "uploads/calendar_events/work_progress_media/work_{$i}/{$requestedFile}";
    }
    $possiblePaths[] = "uploads/site_media/{$requestedFile}";
    $possiblePaths[] = "uploads/calendar_events/{$requestedFile}";
    $possiblePaths[] = "uploads/work_progress/{$requestedFile}";
    $possiblePaths[] = "uploads/{$requestedFile}";
} else {
    // Check in each work folder from 1 to 20
    for ($i = 1; $i <= 20; $i++) {
        $possiblePaths[] = "uploads/calendar_events/work_progress_media/work_{$i}/{$requestedFile}";
    }
    // Also try direct paths
    $possiblePaths[] = "uploads/work_progress/{$requestedFile}";
    $possiblePaths[] = "uploads/videos/{$requestedFile}";
    $possiblePaths[] = "uploads/{$requestedFile}";
}

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
                // Headers for streaming
                header('Content-Type: video/mp4');
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