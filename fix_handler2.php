<?php
// Debug file for path issues

// Show errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Path Debug Tool</h1>";

echo "<h2>Server Information</h2>";
echo "<pre>";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "SCRIPT_FILENAME: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "Current Directory: " . getcwd() . "\n";
echo "Directory of this script: " . __DIR__ . "\n";
echo "</pre>";

echo "<h2>File Exists Tests</h2>";
echo "<pre>";
$paths = [
    'config/db_connect.php',
    './config/db_connect.php',
    '../config/db_connect.php',
    __DIR__ . '/config/db_connect.php',
    __DIR__ . '/../config/db_connect.php',
    $_SERVER['DOCUMENT_ROOT'] . '/config/db_connect.php',
    $_SERVER['DOCUMENT_ROOT'] . '/hr/config/db_connect.php',
    getcwd() . '/config/db_connect.php',
];

foreach ($paths as $path) {
    echo "Testing path: $path\n";
    if (file_exists($path)) {
        echo "  ✅ File exists!\n";
    } else {
        echo "  ❌ File not found.\n";
    }
}
echo "</pre>";

// Create a simple test by copying db_connect.php to includes folder
echo "<h2>Fix Options</h2>";

$sourceFile = 'config/db_connect.php';
$targetDir = 'includes/config';
$targetFile = $targetDir . '/db_connect.php';

if (!is_dir($targetDir)) {
    if (mkdir($targetDir, 0755, true)) {
        echo "<p>Created directory: $targetDir</p>";
    } else {
        echo "<p>Failed to create directory: $targetDir</p>";
    }
}

if (is_dir($targetDir)) {
    if (copy($sourceFile, $targetFile)) {
        echo "<p>✅ Successfully copied db_connect.php to includes/config/</p>";
        echo "<p>You can now update calendar_data_handler.php to use: <code>require_once 'config/db_connect.php';</code></p>";
    } else {
        echo "<p>❌ Failed to copy file. Error: " . error_get_last()['message'] . "</p>";
    }
}

echo "<h2>Calendar Handler Fix</h2>";
$handlerFile = 'includes/calendar_data_handler.php';
$handlerContent = file_get_contents($handlerFile);
$originalContent = $handlerContent;

// Option 1: Try to update to use the copied file in includes/config
$newPath1 = "require_once 'config/db_connect.php';";
// Option 2: Use an absolute path with __DIR__
$newPath2 = "require_once __DIR__ . '/../config/db_connect.php';";
// Option 3: Use an absolute path with document root
$newPath3 = "require_once '{$_SERVER['DOCUMENT_ROOT']}/hr/config/db_connect.php';";

// Try to identify the current require_once line
$pattern = "/require_once\s+['\"].*db_connect\.php['\"]\s*;/";
if (preg_match($pattern, $handlerContent, $matches)) {
    echo "<p>Found include line: <code>" . htmlspecialchars($matches[0]) . "</code></p>";
    
    echo "<p>Select a fix to apply:</p>";
    echo "<form method='post'>";
    echo "<input type='radio' name='fix' value='1' id='fix1'> <label for='fix1'>Use <code>$newPath1</code> (Local path)</label><br>";
    echo "<input type='radio' name='fix' value='2' id='fix2' checked> <label for='fix2'>Use <code>$newPath2</code> (Relative path with __DIR__)</label><br>";
    echo "<input type='radio' name='fix' value='3' id='fix3'> <label for='fix3'>Use <code>$newPath3</code> (Absolute path with DOCUMENT_ROOT)</label><br>";
    echo "<button type='submit'>Apply Fix</button>";
    echo "</form>";
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
        $fixNumber = (int)$_POST['fix'];
        $newPath = $fixNumber === 1 ? $newPath1 : ($fixNumber === 2 ? $newPath2 : $newPath3);
        
        $updatedContent = preg_replace($pattern, $newPath, $handlerContent);
        
        if (file_put_contents($handlerFile, $updatedContent)) {
            echo "<p>✅ Successfully updated path to: <code>$newPath</code></p>";
        } else {
            echo "<p>❌ Failed to update file. Error: " . error_get_last()['message'] . "</p>";
        }
    }
} else {
    echo "<p>❌ Could not find the include line in the calendar_data_handler.php file</p>";
}
?> 