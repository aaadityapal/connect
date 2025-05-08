<?php
// Minimal test script to debug issues
echo "Starting test\n";

// Basic environment info
echo "PHP Version: " . phpversion() . "\n";
echo "Extensions loaded: \n";
$extensions = get_loaded_extensions();
foreach ($extensions as $ext) {
    echo "- $ext\n";
}

// Check for mysqli and PDO
echo "\nChecking for database extensions:\n";
echo "mysqli extension: " . (extension_loaded('mysqli') ? "Loaded" : "Not loaded") . "\n";
echo "PDO extension: " . (extension_loaded('pdo') ? "Loaded" : "Not loaded") . "\n";
echo "PDO MySQL extension: " . (extension_loaded('pdo_mysql') ? "Loaded" : "Not loaded") . "\n";

echo "\nTest completed\n"; 