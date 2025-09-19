<?php
// Test script to verify the new file structure implementation
session_start();
require_once 'config/db_connect.php';

echo "<h2>Testing New Labour File Structure</h2>";

// Get a sample labour ID to test
$sql = "SELECT labour_id, full_name FROM hr_labours LIMIT 1";
$stmt = $pdo->query($sql);
$labour = $stmt->fetch(PDO::FETCH_ASSOC);

if ($labour) {
    $labourId = $labour['labour_id'];
    echo "<h3>Testing with Labour ID: {$labourId} ({$labour['full_name']})</h3>";
    
    // Test the API endpoint
    echo "<h4>1. API Response Test</h4>";
    $apiUrl = "http://localhost/hr/api/get_labour_details.php?id={$labourId}";
    echo "<p>API URL: <a href='{$apiUrl}' target='_blank'>{$apiUrl}</a></p>";
    
    // Test file structure
    echo "<h4>2. File Structure Test</h4>";
    $labourDir = "uploads/labour_documents/{$labourId}/";
    echo "<p>Labour Directory: {$labourDir}</p>";
    
    if (is_dir($labourDir)) {
        echo "<p style='color: green;'>✅ Directory exists</p>";
        $files = scandir($labourDir);
        echo "<ul>";
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                echo "<li>{$file}</li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>⚠️ Directory does not exist (will be created when files are uploaded)</p>";
    }
    
    // Expected file structure
    echo "<h4>3. Expected File Structure</h4>";
    $expectedFiles = ['aadhar.jpg', 'pan.pdf', 'voter.png', 'other.jpg'];
    echo "<ul>";
    foreach ($expectedFiles as $file) {
        $filePath = $labourDir . $file;
        if (file_exists($filePath)) {
            echo "<li style='color: green;'>✅ {$file} (exists)</li>";
        } else {
            echo "<li style='color: gray;'>⭕ {$file} (not found)</li>";
        }
    }
    echo "</ul>";
    
} else {
    echo "<p style='color: red;'>❌ No labour records found in database</p>";
}

echo "<hr>";
echo "<h4>4. Add New Labour Test</h4>";
echo "<p>To test the new file upload system:</p>";
echo "<ol>";
echo "<li>Go to <a href='analytics/executive_insights_dashboard.php' target='_blank'>Executive Dashboard</a></li>";
echo "<li>Click 'Add Labour' button</li>";
echo "<li>Fill the form and upload documents</li>";
echo "<li>Check if files are saved in the new structure: uploads/labour_documents/{labour_id}/</li>";
echo "</ol>";

echo "<hr>";
echo "<h4>5. View Modal Test</h4>";
echo "<p>To test the view modal with new file structure:</p>";
echo "<ol>";
echo "<li>Go to <a href='analytics/executive_insights_dashboard.php' target='_blank'>Executive Dashboard</a></li>";
echo "<li>Click the 'view' (👁️) button on any labour in the 'Recently Added Data' section</li>";
echo "<li>Check if document images load properly in the modal</li>";
echo "</ol>";
?>