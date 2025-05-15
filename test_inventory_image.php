<?php
// Set headers for proper image serving
header('Content-Type: image/jpeg');

// Define multiple paths to try
$possiblePaths = [
    // Path from the error message
    'uploads/calendar_events/inventory_media/inventory_6/6825cb09107df_1747307273.jpg',
    
    // Without the "../" prefix
    'uploads/calendar_events/inventory_media/inventory_6/6825cb09107df_1747307273.jpg',
    
    // Regular inventory paths
    'uploads/inventory/6825cb09107df_1747307273.jpg',
    'uploads/inventory_images/6825cb09107df_1747307273.jpg',
    'uploads/inventory_bills/6825cb09107df_1747307273.jpg'
];

// Try to find the image in one of the possible paths
$foundImage = false;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        // Log success for debugging
        error_log("Found image at: $path");
        
        // Output the file
        readfile($path);
        $foundImage = true;
        break;
    } else {
        error_log("Image not found at: $path");
    }
}

// If no image was found, return a placeholder
if (!$foundImage) {
    header("HTTP/1.0 404 Not Found");
    echo "Image not found in any of the tested paths.";
    
    // Detailed error logging
    error_log("Image not found in any of the tested paths: " . implode(", ", $possiblePaths));
}
?> 