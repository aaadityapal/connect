<?php
// Read the content of the file
$filePath = 'includes/calendar_data_handler.php';
$content = file_get_contents($filePath);

// Remove the PHP closing tag and any whitespace after it
$content = rtrim($content);
$content = preg_replace('/\s*\?>.*$/s', '', $content);

// Ensure the file ends with a newline
$content .= PHP_EOL;

// Write the fixed content back to the file
file_put_contents($filePath, $content);

echo "File fixed successfully!";
?> 