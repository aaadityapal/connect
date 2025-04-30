<?php
// This script will create the necessary tables for the Site Updates & Expenses feature

require_once 'config/db_connect.php';

// Read SQL file content
$sqlContent = file_get_contents('create_site_expense_tables.sql');

// Split SQL file content into separate queries
$queries = explode(';', $sqlContent);

// Execute each query
$success = true;
$errorMessages = [];

foreach ($queries as $query) {
    $query = trim($query);
    if (empty($query)) continue;
    
    if (!$conn->query($query)) {
        $success = false;
        $errorMessages[] = $conn->error;
    }
}

// Output result
if ($success) {
    echo "<h2>Tables Created Successfully</h2>";
    echo "<p>The site_updates and travel_expenses tables have been created successfully.</p>";
    echo "<p><a href='site_expenses.php'>Go to Site Updates & Expenses Page</a></p>";
} else {
    echo "<h2>Error Creating Tables</h2>";
    echo "<p>The following errors occurred:</p>";
    echo "<ul>";
    foreach ($errorMessages as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}
?> 