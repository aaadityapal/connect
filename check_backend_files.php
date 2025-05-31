<?php
// Check if the file exists
$file_path = 'backend/get_custom_titles.php';
echo "Checking if file exists: $file_path\n";

if (file_exists($file_path)) {
    echo "File exists!\n";
    
    // Check file permissions
    $perms = fileperms($file_path);
    echo "File permissions: " . decoct($perms & 0777) . "\n";
    
    // Check if file is readable
    if (is_readable($file_path)) {
        echo "File is readable.\n";
    } else {
        echo "File is NOT readable.\n";
    }
    
    // Check file size
    $size = filesize($file_path);
    echo "File size: $size bytes\n";
    
    // Display file contents
    echo "\nFile contents:\n";
    echo "----------------------------------------\n";
    echo file_get_contents($file_path);
    echo "\n----------------------------------------\n";
} else {
    echo "File does not exist!\n";
}

// Check if the backend directory exists and is writable
$dir_path = 'backend';
echo "\nChecking if directory exists: $dir_path\n";

if (is_dir($dir_path)) {
    echo "Directory exists!\n";
    
    // Check directory permissions
    $perms = fileperms($dir_path);
    echo "Directory permissions: " . decoct($perms & 0777) . "\n";
    
    // Check if directory is writable
    if (is_writable($dir_path)) {
        echo "Directory is writable.\n";
    } else {
        echo "Directory is NOT writable.\n";
    }
} else {
    echo "Directory does not exist!\n";
}
?> 