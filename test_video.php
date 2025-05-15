<?php
// Set headers for proper video serving
header('Content-Type: video/mp4');
header('Accept-Ranges: bytes');

// Get the requested file name from URL parameter
$requestedFile = isset($_GET['file']) ? $_GET['file'] : '6825cb090f121_1747307273.mp4';

// Define possible video paths based on the requested file
$possiblePaths = [];

if ($requestedFile === '6825cb090f121_1747307273.mp4') {
    $possiblePaths[] = 'uploads/calendar_events/work_progress_media/work_8/6825cb090f121_1747307273.mp4';
    $possiblePaths[] = 'uploads/work_progress/6825cb090f121_1747307273.mp4';
} elseif ($requestedFile === '6825cb048b42ac_1747309896.mp4') {
    // For this specific problematic video, try multiple work folders
    for ($i = 1; $i <= 15; $i++) {
        $possiblePaths[] = "uploads/calendar_events/work_progress_media/work_{$i}/6825cb048b42ac_1747309896.mp4";
    }
    // Also check standard paths
    $possiblePaths[] = 'uploads/work_progress/6825cb048b42ac_1747309896.mp4';
    $possiblePaths[] = 'uploads/calendar_events/inventory_media/inventory_6/6825cb048b42ac_1747309896.mp4';
    $possiblePaths[] = 'uploads/inventory_videos/6825cb048b42ac_1747309896.mp4';
} else {
    // Prioritize work progress paths for all videos (since that's what's not working)
    for ($i = 1; $i <= 10; $i++) {
        $possiblePaths[] = "uploads/calendar_events/work_progress_media/work_{$i}/" . $requestedFile;
    }
    
    // Then try other common paths
    $possiblePaths[] = 'uploads/work_progress/' . $requestedFile;
    $possiblePaths[] = 'uploads/calendar_events/inventory_media/inventory_6/' . $requestedFile;
    $possiblePaths[] = 'uploads/inventory_videos/' . $requestedFile;
}

// Log the debug info
error_log("Requested video file: " . $requestedFile);
error_log("Checking paths: " . implode(", ", $possiblePaths));

// Try to find the video in one of the possible paths
$videoFound = false;
foreach ($possiblePaths as $videoPath) {
    if (file_exists($videoPath)) {
        // Output file size and debug info for console
        $filesize = filesize($videoPath);
        $file_extension = pathinfo($videoPath, PATHINFO_EXTENSION);
        
        error_log("Serving video file: $videoPath");
        error_log("File size: $filesize bytes");
        error_log("File extension: $file_extension");
        
        // Set content length header
        header("Content-Length: $filesize");
        
        // Output the file
        readfile($videoPath);
        $videoFound = true;
        break;
    } else {
        error_log("File not found at: $videoPath");
    }
}

// If no video was found in any path, create a small dummy video file with error message
if (!$videoFound) {
    error_log("Video file not found in any of the checked paths. Providing a dummy video file.");
    
    // Set a static path to a default empty MP4 file that exists on the server
    $defaultVideoPath = 'assets/default_error_video.mp4';
    
    if (file_exists($defaultVideoPath)) {
        $filesize = filesize($defaultVideoPath);
        header("Content-Length: $filesize");
        readfile($defaultVideoPath);
    } else {
        // No default video found either, return a 404
        header("HTTP/1.0 404 Not Found");
        echo "Video file not found: $requestedFile. Checked paths: " . implode(", ", $possiblePaths);
    }
}
?> 