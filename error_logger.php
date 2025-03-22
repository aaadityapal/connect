<?php
function logError($type, $message, $details = []) {
    $log_dir = 'logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $date = date('Y-m-d H:i:s');
    $log_file = $log_dir . '/upload_errors.log';
    
    $error_data = [
        'timestamp' => $date,
        'type' => $type,
        'message' => $message,
        'details' => $details,
        'server' => $_SERVER,
        'session' => isset($_SESSION) ? $_SESSION : null
    ];
    
    $log_message = "[{$date}] {$type}: {$message}\n";
    $log_message .= "Details: " . json_encode($details, JSON_PRETTY_PRINT) . "\n";
    $log_message .= "----------------------------------------\n";
    
    error_log($log_message, 3, $log_file);
    return $error_data;
} 