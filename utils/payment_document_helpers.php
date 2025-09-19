<?php
/**
 * Helper functions for payment document organization
 */

/**
 * Creates the organized directory structure for payment documents
 * 
 * @param int $paymentId The payment entry ID
 * @param int $recipientId The recipient ID
 * @param bool $isSplit Whether this is for split payment documents
 * @return string The directory path
 */
function createPaymentDocumentDirectory($paymentId, $recipientId, $isSplit = false) {
    $basePath = "../uploads/payment_documents/payment_{$paymentId}/recipient_{$recipientId}/";
    
    if ($isSplit) {
        $basePath .= "splits/";
    }
    
    // Create directory if it doesn't exist
    if (!file_exists($basePath)) {
        mkdir($basePath, 0777, true);
    }
    
    return $basePath;
}

/**
 * Generates a clean, organized filename for payment documents
 * 
 * @param string $originalName The original filename
 * @param int $documentId Optional document ID for uniqueness
 * @param string $prefix Optional prefix (e.g., 'split_')
 * @return string The clean filename
 */
function generateOrganizedFilename($originalName, $documentId = null, $prefix = '') {
    $fileExtension = pathinfo($originalName, PATHINFO_EXTENSION);
    $cleanFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    
    // Limit filename length to prevent path issues
    if (strlen($cleanFileName) > 50) {
        $cleanFileName = substr($cleanFileName, 0, 50);
    }
    
    $filename = $prefix . $cleanFileName;
    
    if ($documentId) {
        $filename .= '_doc' . $documentId;
    } else {
        $filename .= '_' . uniqid();
    }
    
    return $filename . '.' . $fileExtension;
}

/**
 * Gets the relative path for storing in database
 * 
 * @param int $paymentId The payment entry ID
 * @param int $recipientId The recipient ID
 * @param string $filename The filename
 * @param bool $isSplit Whether this is for split payment documents
 * @return string The relative path
 */
function getPaymentDocumentRelativePath($paymentId, $recipientId, $filename, $isSplit = false) {
    $path = "uploads/payment_documents/payment_{$paymentId}/recipient_{$recipientId}/";
    
    if ($isSplit) {
        $path .= "splits/";
    }
    
    return $path . $filename;
}

/**
 * Validates uploaded file for payment documents
 * 
 * @param array $file The $_FILES array element
 * @param int $maxSize Maximum file size in bytes (default 5MB)
 * @return array|bool Returns error array if validation fails, true if valid
 */
function validatePaymentDocumentFile($file, $maxSize = 5242880) { // 5MB default
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'File upload error: ' . $file['error']];
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        $maxSizeMB = round($maxSize / 1024 / 1024, 1);
        return ['error' => "File size too large. Maximum allowed size is {$maxSizeMB}MB"];
    }
    
    // Check file type
    $allowedTypes = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['error' => 'Invalid file type. Allowed types: JPG, PNG, PDF, DOC, DOCX, XLS, XLSX'];
    }
    
    // Check filename for security
    $filename = $file['name'];
    if (preg_match('/[<>:"/\\|?*]/', $filename)) {
        return ['error' => 'Invalid characters in filename'];
    }
    
    return true;
}

/**
 * Creates a backup of the old file organization for safety
 * 
 * @param string $sourceDir The source directory to backup
 * @param string $backupDir The backup destination
 * @return bool Success status
 */
function createPaymentDocumentBackup($sourceDir = '../uploads/payment_documents/', $backupDir = '../backups/payment_documents_backup_') {
    $backupPath = $backupDir . date('Y_m_d_H_i_s');
    
    if (!file_exists($sourceDir)) {
        return true; // Nothing to backup
    }
    
    // Create backup directory
    if (!file_exists($backupPath)) {
        mkdir($backupPath, 0777, true);
    }
    
    // Copy files recursively
    return copyDirectory($sourceDir, $backupPath);
}

/**
 * Recursively copy directory contents
 * 
 * @param string $src Source directory
 * @param string $dst Destination directory
 * @return bool Success status
 */
function copyDirectory($src, $dst) {
    $dir = opendir($src);
    if (!$dir) return false;
    
    @mkdir($dst, 0777, true);
    
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            
            if (is_dir($srcPath)) {
                copyDirectory($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }
    
    closedir($dir);
    return true;
}

/**
 * Logs payment document operations for audit trail
 * 
 * @param string $operation The operation performed
 * @param int $paymentId Payment ID
 * @param int $recipientId Recipient ID
 * @param string $filename Filename
 * @param int $userId User performing the operation
 */
function logPaymentDocumentOperation($operation, $paymentId, $recipientId, $filename, $userId = null) {
    $logFile = '../logs/payment_documents.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $userInfo = $userId ? "User:{$userId}" : "System";
    $logEntry = "[{$timestamp}] {$operation} - Payment:{$paymentId} Recipient:{$recipientId} File:{$filename} By:{$userInfo}\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>