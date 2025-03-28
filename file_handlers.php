<?php
function viewFile($file_path) {
    // Validate file exists
    if (!file_exists($file_path)) {
        return [
            'success' => false,
            'error' => 'File not found'
        ];
    }
    
    // Get file mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_path);
    finfo_close($finfo);
    
    // Set headers
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($file_path));
    
    // Output file
    readfile($file_path);
    exit;
}

function downloadFile($file_path) {
    // Validate file exists
    if (!file_exists($file_path)) {
        return [
            'success' => false,
            'error' => 'File not found'
        ];
    }
    
    // Get filename
    $filename = basename($file_path);
    
    // Set headers
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file_path));
    
    // Output file
    readfile($file_path);
    exit;
}