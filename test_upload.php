<?php
$uploadDir = dirname(__DIR__) . 'hr/uploads/hr_documents/';
$testFile = $uploadDir . 'test.txt';

// Try to create a test file
try {
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        echo "Created directory: $uploadDir<br>";
    }

    file_put_contents($testFile, 'Test content');
    echo "Successfully created test file at: $testFile<br>";
    echo "Directory is writable: " . (is_writable($uploadDir) ? 'Yes' : 'No') . "<br>";
    echo "File exists: " . (file_exists($testFile) ? 'Yes' : 'No') . "<br>";
    echo "File permissions: " . substr(sprintf('%o', fileperms($testFile)), -4) . "<br>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Display directory contents
echo "<br>Directory contents:<br>";
$files = scandir($uploadDir);
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo $file . "<br>";
    }
} 