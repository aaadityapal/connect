<?php
// Include database connection
require_once 'config/db_connect.php';

echo "<h1>Updating Company Labours Table</h1>";

// Read the SQL file
$sql_file = file_get_contents('update_company_labours_columns.sql');

// Execute the SQL queries
try {
    if ($conn->multi_query($sql_file)) {
        do {
            // Store first result set
            if ($result = $conn->store_result()) {
                while ($row = $result->fetch_assoc()) {
                    echo "<p>{$row['message']}</p>";
                }
                $result->free();
            }
            // Check if there are more result sets
        } while ($conn->more_results() && $conn->next_result());
    }
    
    echo "<p>Company labours table update completed!</p>";
    echo "<p><a href='site_expenses.php'>Return to Site Expenses</a></p>";
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
} finally {
    // Close connection
    $conn->close();
}
?> 