<?php
// Script to execute the calendar event schema SQL

// Include database configuration
require_once 'config.php';

// Function to output results
function outputMessage($message, $success = true) {
    echo '<div style="padding: 10px; margin: 5px 0; border-radius: 5px; background-color: ' . 
         ($success ? '#d4edda' : '#f8d7da') . '; color: ' . 
         ($success ? '#155724' : '#721c24') . ';">' . 
         $message . '</div>';
}

echo '<h1>Calendar Event Schema Installer</h1>';

// Check if the schema file exists
$schemaFile = 'calendar_event_schema.sql';
if (!file_exists($schemaFile)) {
    outputMessage("Schema file '$schemaFile' not found!", false);
    exit;
}

// Read the schema file
$sql = file_get_contents($schemaFile);
if (!$sql) {
    outputMessage("Failed to read schema file!", false);
    exit;
}

// Split SQL into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)), 'strlen');

// Execute each statement
$successCount = 0;
$errorCount = 0;

try {
    foreach ($statements as $statement) {
        try {
            $result = $pdo->exec($statement);
            if ($result !== false) {
                $successCount++;
                outputMessage("Successfully executed: " . substr($statement, 0, 50) . "...");
            } else {
                $errorCount++;
                outputMessage("Statement executed with possible issues: " . substr($statement, 0, 50) . "...", false);
            }
        } catch (PDOException $e) {
            $errorCount++;
            outputMessage("Error executing SQL: " . $e->getMessage() . "<br>Statement: " . substr($statement, 0, 50) . "...", false);
        }
    }
    
    echo "<h2>Schema Installation Summary</h2>";
    echo "<p>Total statements: " . count($statements) . "</p>";
    echo "<p>Successful: " . $successCount . "</p>";
    echo "<p>Errors: " . $errorCount . "</p>";
    
    if ($errorCount == 0) {
        echo "<p style='color: green; font-weight: bold;'>Schema installation completed successfully!</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>Schema installation completed with errors. Please check the output above.</p>";
    }
    
    echo "<p><a href='debug_calendar_event.php'>Go to debug page to verify tables</a></p>";
    
} catch (Exception $e) {
    outputMessage("General error: " . $e->getMessage(), false);
}
?> 