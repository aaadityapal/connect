<?php
// Set the log file path
$log_file = 'C:/xampp/apache/logs/error.log';

// Check if the file exists
if (!file_exists($log_file)) {
    die("Error log file not found at: $log_file");
}

// Read the last 500 lines of the log file
$lines = [];
$handle = fopen($log_file, 'r');
if ($handle) {
    $buffer = [];
    $line_count = 0;
    
    // Read through the file
    while (!feof($handle)) {
        $line = fgets($handle);
        $buffer[] = $line;
        $line_count++;
        
        // Keep only the last 500 lines
        if ($line_count > 500) {
            array_shift($buffer);
        }
    }
    
    fclose($handle);
    $lines = $buffer;
} else {
    die("Could not open log file for reading");
}

// Filter for our debug messages
$filtered_lines = [];
foreach ($lines as $line) {
    if (strpos($line, 'Remaining amount') !== false || 
        strpos($line, 'addProjectPayout') !== false || 
        strpos($line, 'updateProjectPayout') !== false) {
        $filtered_lines[] = $line;
    }
}

// Display the results
echo "<h1>Debug Messages for Remaining Amount</h1>";

if (empty($filtered_lines)) {
    echo "<p>No debug messages found related to remaining amount.</p>";
} else {
    echo "<p>Found " . count($filtered_lines) . " debug messages:</p>";
    echo "<pre style='background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow: auto; max-height: 600px;'>";
    foreach ($filtered_lines as $line) {
        echo htmlspecialchars($line) . "\n";
    }
    echo "</pre>";
}
?> 