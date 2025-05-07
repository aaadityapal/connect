<?php
// Set proper content type header for JSON response
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check PHP version and configuration
$php_info = [
    'version' => phpversion(),
    'sapi' => php_sapi_name(),
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Not available',
    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'Not available',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'Not available',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Not available',
    'php_self' => $_SERVER['PHP_SELF'] ?? 'Not available',
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit')
];

// Check file paths
$file_paths = [
    'current_file' => __FILE__,
    'current_dir' => __DIR__,
    'includes_dir' => __DIR__ . '/includes',
    'uploads_dir' => __DIR__ . '/uploads',
    'process_work_media_path' => __DIR__ . '/includes/process_work_media.php',
    'work_progress_media_handler_path' => __DIR__ . '/includes/work_progress_media_handler.php'
];

// Check file permissions
$file_permissions = [];
foreach ($file_paths as $key => $path) {
    if (file_exists($path)) {
        $file_permissions[$key] = [
            'exists' => true,
            'readable' => is_readable($path),
            'writable' => is_writable($path),
            'permissions' => substr(sprintf('%o', fileperms($path)), -4)
        ];
    } else {
        $file_permissions[$key] = [
            'exists' => false
        ];
    }
}

// Output the diagnostic information
echo json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'php_info' => $php_info,
    'file_paths' => $file_paths,
    'file_permissions' => $file_permissions,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Not available',
    'server_vars' => [
        'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'Not available',
        'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'Not available',
        'SERVER_ADDR' => $_SERVER['SERVER_ADDR'] ?? 'Not available',
        'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? 'Not available'
    ]
], JSON_PRETTY_PRINT);
?> 