<?php
// Utility to clear debug log contents
header('Content-Type: text/plain');

$log_file = 'logs/calendar_event_errors.log';

if (file_exists($log_file)) {
    if (file_put_contents($log_file, "=== LOG CLEARED " . date('Y-m-d H:i:s') . " ===\n") !== false) {
        echo "Log file cleared successfully at " . date('Y-m-d H:i:s');
    } else {
        echo "Failed to clear log file. Check file permissions.";
    }
} else {
    echo "Log file does not exist yet.";
    
    // Create log directory if it doesn't exist
    if (!file_exists('logs')) {
        if (mkdir('logs', 0777, true)) {
            echo "\nCreated logs directory.";
        } else {
            echo "\nFailed to create logs directory.";
        }
    }
    
    // Try to create an empty log file
    if (file_put_contents($log_file, "=== LOG CREATED " . date('Y-m-d H:i:s') . " ===\n") !== false) {
        echo "\nCreated empty log file.";
    } else {
        echo "\nFailed to create log file. Check directory permissions.";
    }
}
?> 