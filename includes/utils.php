<?php
/**
 * Utility Functions for HR Application
 * Common helper functions used across the application
 */

if (!function_exists('getSafeMimeType')) {
    /**
     * Get MIME type safely with multiple fallback methods
     * @param string $file_path Full path to the file
     * @return string MIME type or 'application/octet-stream' as fallback
     */
    function getSafeMimeType($file_path) {
        if (!file_exists($file_path)) {
            return 'application/octet-stream';
        }
        
        // Method 1: mime_content_type (if available)
        if (function_exists('mime_content_type')) {
            try {
                $mime = mime_content_type($file_path);
                if ($mime !== false) {
                    return $mime;
                }
            } catch (Exception $e) {
                // Continue to next method
            }
        }
        
        // Method 2: finfo (usually available)
        if (function_exists('finfo_file')) {
            try {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo !== false) {
                    $mime = finfo_file($finfo, $file_path);
                    finfo_close($finfo);
                    if ($mime !== false) {
                        return $mime;
                    }
                }
            } catch (Exception $e) {
                // Continue to next method
            }
        }
        
        // Method 3: Extension-based fallback
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $mime_types = [
            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            
            // Documents
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
            
            // Archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            
            // Other
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'csv' => 'text/csv'
        ];
        
        return $mime_types[$extension] ?? 'application/octet-stream';
    }
}

if (!function_exists('formatFileSize')) {
    /**
     * Format file size in human readable format
     * @param int $size File size in bytes
     * @return string Formatted size (e.g., "1.5 MB")
     */
    function formatFileSize($size) {
        if ($size == 0) return '0 bytes';
        
        $units = ['bytes', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($size) / log(1024));
        $power = min($power, count($units) - 1);
        
        $size = $size / pow(1024, $power);
        return round($size, 2) . ' ' . $units[$power];
    }
}

if (!function_exists('safeHtmlOutput')) {
    /**
     * Safely output HTML content by escaping special characters
     * @param string $content Content to escape
     * @return string Escaped content
     */
    function safeHtmlOutput($content) {
        return htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatCurrency')) {
    /**
     * Format currency amount
     * @param float $amount Amount to format
     * @param string $currency Currency symbol (default: ₹)
     * @return string Formatted currency
     */
    function formatCurrency($amount, $currency = '₹') {
        return $currency . number_format($amount, 2);
    }
}

if (!function_exists('formatDateTime')) {
    /**
     * Format datetime for display
     * @param string $datetime Datetime string
     * @param string $format Output format (default: 'F j, Y g:i A')
     * @return string Formatted datetime
     */
    function formatDateTime($datetime, $format = 'F j, Y g:i A') {
        try {
            return date($format, strtotime($datetime));
        } catch (Exception $e) {
            return $datetime; // Return original if formatting fails
        }
    }
}

if (!function_exists('generateReferenceId')) {
    /**
     * Generate reference ID for payments
     * @param int $payment_id Payment ID
     * @param string $created_at Creation timestamp
     * @return string Reference ID
     */
    function generateReferenceId($payment_id, $created_at) {
        $year = date('Y', strtotime($created_at));
        return "REF-{$payment_id}-{$year}";
    }
}

if (!function_exists('isImageFile')) {
    /**
     * Check if file is an image based on extension
     * @param string $filename Filename or path
     * @return bool True if image file
     */
    function isImageFile($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
        return in_array($extension, $image_extensions);
    }
}

if (!function_exists('isPdfFile')) {
    /**
     * Check if file is a PDF
     * @param string $filename Filename or path
     * @return bool True if PDF file
     */
    function isPdfFile($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return $extension === 'pdf';
    }
}

if (!function_exists('logError')) {
    /**
     * Log errors to a custom log file
     * @param string $message Error message
     * @param string $context Additional context
     */
    function logError($message, $context = '') {
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
        
        $log_file = $log_dir . '/app_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}";
        
        if ($context) {
            $log_entry .= " | Context: {$context}";
        }
        
        $log_entry .= "\n";
        
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}
?>