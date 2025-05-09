<?php
// Utility to view debug log contents
header('Content-Type: text/plain');

$log_file = 'logs/calendar_event_errors.log';

if (file_exists($log_file)) {
    // Limit to last 100 lines for performance
    $log_content = file($log_file);
    $last_lines = array_slice($log_content, -100);
    
    if (empty($last_lines)) {
        echo "Log file exists but is empty.";
    } else {
        echo "=== LAST 100 LOG ENTRIES ===\n\n";
        echo implode('', $last_lines);
    }
} else {
    echo "Log file does not exist yet. It will be created when the backend file is accessed.";
}
?> 