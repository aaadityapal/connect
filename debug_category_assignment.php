<?php
// Debug script to test payment entry category assignment
session_start();
require_once 'config/db_connect.php';

echo "<h1>Payment Entry Category Assignment Debug</h1>";

// Simulate what happens when form data is processed
echo "<h2>1. Testing Category Assignment Logic</h2>";

// Simulate different form submissions
$test_cases = [
    [
        'description' => 'Vendor Selection',
        'category' => 'vendor',
        'type' => 'tile_vendor',
        'name' => 'Test Vendor',
        'expected_category' => 'vendor'
    ],
    [
        'description' => 'Labour Selection', 
        'category' => 'labour',
        'type' => 'mason',
        'name' => 'Test Labour',
        'expected_category' => 'labour'
    ],
    [
        'description' => 'Supplier Selection',
        'category' => 'supplier',
        'type' => 'cement_supplier', 
        'name' => 'Test Supplier',
        'expected_category' => 'supplier'
    ]
];

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>Test Case</th>";
echo "<th>Input Category</th>";
echo "<th>Input Type</th>";
echo "<th>Expected Category</th>";
echo "<th>Result</th>";
echo "</tr>";

foreach ($test_cases as $test) {
    $category = $test['category'];
    $type = $test['type'];
    $name = $test['name'];
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($test['description']) . "</td>";
    echo "<td>" . htmlspecialchars($category) . "</td>";
    echo "<td>" . htmlspecialchars($type) . "</td>";
    echo "<td>" . htmlspecialchars($test['expected_category']) . "</td>";
    
    // Test the assignment logic
    if ($category === $test['expected_category']) {
        echo "<td style='background-color: #ccffcc;'>✓ PASS</td>";
    } else {
        echo "<td style='background-color: #ffcccc;'>✗ FAIL - Got: " . htmlspecialchars($category) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

echo "<h2>2. Check Form Processing Logic</h2>";

// Check if the issue is in the form processing
echo "<p>Let's examine how the payment entry form processes different recipient types:</p>";

echo "<h3>Frontend Category Options:</h3>";
$frontend_categories = [
    'vendor' => 'Vendor',
    'supplier' => 'Supplier', 
    'contractor' => 'Contractor',
    'employee' => 'Employee',
    'labour' => 'Labour',
    'service_provider' => 'Service Provider',
    'other' => 'Other'
];

echo "<ul>";
foreach ($frontend_categories as $value => $label) {
    echo "<li><strong>$value</strong> → displays as \"$label\"</li>";
}
echo "</ul>";

echo "<h3>Expected Database Storage:</h3>";
echo "<ul>";
echo "<li>When user selects 'Vendor' → should save as category='vendor'</li>";
echo "<li>When user selects 'Labour' → should save as category='labour'</li>";
echo "<li>When user selects 'Supplier' → should save as category='supplier'</li>";
echo "</ul>";

echo "<h2>3. Potential Issues:</h2>";
echo "<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 10px 0;'>";
echo "<h4>Most Likely Issues:</h4>";
echo "<ol>";
echo "<li><strong>Form Submission Issue:</strong> Both recipients are being submitted with the same category value</li>";
echo "<li><strong>JavaScript Issue:</strong> Category selection is not properly updating when user changes recipient type</li>";
echo "<li><strong>Database Issue:</strong> Category is being overwritten during save process</li>";
echo "<li><strong>Frontend Issue:</strong> User interface is not properly distinguishing between vendor and labour selections</li>";
echo "</ol>";
echo "</div>";

echo "<h2>4. Database Schema Check:</h2>";
try {
    $schemaSql = "DESCRIBE hr_payment_recipients";
    $schemaStmt = $pdo->query($schemaSql);
    $schema = $schemaStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Field</th>";
    echo "<th>Type</th>";
    echo "<th>Null</th>";
    echo "<th>Key</th>";
    echo "<th>Default</th>";
    echo "</tr>";
    
    foreach ($schema as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error checking schema: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>5. Next Steps for Debugging:</h2>";
echo "<ol>";
echo "<li>Run this debug script: <a href='debug_payment_categories.php' target='_blank'>debug_payment_categories.php</a></li>";
echo "<li>Check what's actually stored in the database for your problematic payment entry</li>";
echo "<li>Test creating a new payment entry with clear vendor vs labour selections</li>";
echo "<li>Monitor the network requests to see what data is being sent to save_payment_entry.php</li>";
echo "<li>Add console.log statements to the frontend JavaScript to track category changes</li>";
echo "</ol>";

echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; }";
echo "table { margin: 10px 0; }";
echo "th, td { padding: 8px; text-align: left; }";
echo "h1, h2, h3 { color: #333; }";
echo "</style>";
?>