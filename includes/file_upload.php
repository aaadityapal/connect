<?php
/**
 * File Upload Handler
 * 
 * Functions to handle file uploads for the site supervision system
 */

/**
 * Upload a file
 * 
 * @param array $file The $_FILES array element
 * @param string $destination The destination directory (relative to upload_dir)
 * @param array $allowedTypes Array of allowed MIME types
 * @param int $maxSize Maximum allowed file size in bytes
 * @return array|bool Array with file info on success, false on failure
 */
function uploadFile($file, $destination = 'general', $allowedTypes = [], $maxSize = 5242880) {
    // Define upload directory - use a directory accessible within the web root for simpler testing
    $uploadBaseDir = dirname($_SERVER['DOCUMENT_ROOT']) . '/uploads/';
    
    // Fallback to a directory within the web root if parent directory is not writable
    if (!is_writable(dirname($_SERVER['DOCUMENT_ROOT']))) {
        $uploadBaseDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
    }
    
    $uploadDir = $uploadBaseDir . $destination . '/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        $dirCreated = mkdir($uploadDir, 0755, true);
        if (!$dirCreated) {
            return [
                'error' => 'Failed to create upload directory: ' . $uploadDir . 
                          '. Please check permissions. Current script user: ' . get_current_user()
            ];
        }
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        return [
            'error' => 'Upload directory is not writable: ' . $uploadDir . 
                      '. Please check permissions. Current script user: ' . get_current_user()
        ];
    }
    
    // Check if file was uploaded properly
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'No file was uploaded or upload failed'];
    }
    
    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        
        $errorMessage = isset($errorMessages[$file['error']]) 
            ? $errorMessages[$file['error']] 
            : 'Unknown upload error';
        
        return ['error' => $errorMessage];
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return ['error' => 'File size exceeds the maximum limit of ' . formatFileSize($maxSize)];
    }
    
    // Check file type if restrictions are provided
    if (!empty($allowedTypes)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $fileType = $finfo->file($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            return ['error' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes)];
        }
    }
    
    // Generate a unique filename to prevent overwrites
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    $filename = generateUniqueFilename($extension);
    $targetPath = $uploadDir . $filename;
    
    // Move the uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['error' => 'Failed to move uploaded file'];
    }
    
    // Return file information
    return [
        'filename' => $filename,
        'original_name' => $file['name'],
        'path' => $destination . '/' . $filename,
        'full_path' => $targetPath,
        'relative_path' => 'uploads/' . $destination . '/' . $filename,
        'type' => $file['type'],
        'size' => $file['size'],
        'error' => null
    ];
}

/**
 * Generate a unique filename
 * 
 * @param string $extension File extension
 * @return string Unique filename
 */
function generateUniqueFilename($extension) {
    return uniqid('file_', true) . '_' . date('Ymd') . '.' . $extension;
}

/**
 * Format file size for human-readable output
 * 
 * @param int $bytes File size in bytes
 * @param int $decimals Number of decimal places
 * @return string Formatted file size
 */
function formatFileSize($bytes, $decimals = 2) {
    $size = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $size[$factor];
}

/**
 * Upload an image file
 * 
 * @param array $file The $_FILES array element
 * @param string $destination The destination directory
 * @param int $maxSize Maximum allowed file size in bytes
 * @return array|bool Array with file info on success, false on failure
 */
function uploadImage($file, $destination = 'images', $maxSize = 5242880) {
    $allowedTypes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif'
    ];
    
    return uploadFile($file, $destination, $allowedTypes, $maxSize);
}

/**
 * Upload a document file
 * 
 * @param array $file The $_FILES array element
 * @param string $destination The destination directory
 * @param int $maxSize Maximum allowed file size in bytes
 * @return array|bool Array with file info on success, false on failure
 */
function uploadDocument($file, $destination = 'documents', $maxSize = 10485760) {
    $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain'
    ];
    
    return uploadFile($file, $destination, $allowedTypes, $maxSize);
}

/**
 * Upload a video file
 * 
 * @param array $file The $_FILES array element
 * @param string $destination The destination directory
 * @param int $maxSize Maximum allowed file size in bytes
 * @return array|bool Array with file info on success, false on failure
 */
function uploadVideo($file, $destination = 'videos', $maxSize = 104857600) {
    $allowedTypes = [
        'video/mp4',
        'video/mpeg',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-ms-wmv'
    ];
    
    return uploadFile($file, $destination, $allowedTypes, $maxSize);
}

/**
 * Delete a file
 * 
 * @param string $filepath The path to the file relative to upload directory
 * @return bool True on success, false on failure
 */
function deleteUploadedFile($filepath) {
    // Define upload directory
    $uploadBaseDir = dirname($_SERVER['DOCUMENT_ROOT']) . '/uploads/';
    $fullPath = $uploadBaseDir . $filepath;
    
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    
    return false;
} 