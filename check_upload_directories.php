<?php
function createUploadDirectories() {
    $directories = [
        'uploads/documents/official',
        'uploads/documents/personal'
    ];

    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log("Failed to create directory: " . $dir);
                return false;
            }
        }
        
        // Set proper permissions
        chmod($dir, 0755);
    }
    return true;
} 