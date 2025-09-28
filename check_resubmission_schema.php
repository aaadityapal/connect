<?php
// Test file to check if resubmission columns exist
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Please log in first');
}

include_once('includes/db_connect.php');

// Check if the new resubmission columns exist
echo "<h2>Database Schema Check</h2>";

try {
    $result = $conn->query("DESCRIBE travel_expenses");
    
    $existing_columns = array();
    $required_columns = array(
        'original_expense_id',
        'resubmission_count', 
        'is_resubmitted',
        'resubmitted_from',
        'resubmission_date',
        'max_resubmissions'
    );
    
    echo "<h3>Current Table Columns:</h3>";
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
        echo "<li>{$row['Field']} ({$row['Type']})</li>";
    }
    echo "</ul>";
    
    echo "<h3>Resubmission Columns Status:</h3>";
    echo "<ul>";
    foreach ($required_columns as $column) {
        $exists = in_array($column, $existing_columns);
        $status = $exists ? "✅ EXISTS" : "❌ MISSING";
        echo "<li>{$column}: {$status}</li>";
    }
    echo "</ul>";
    
    // Check if we need to add the columns
    $missing_columns = array_diff($required_columns, $existing_columns);
    
    if (!empty($missing_columns)) {
        echo "<h3>⚠️ Missing Columns Detected</h3>";
        echo "<p>The following columns need to be added:</p>";
        echo "<ul>";
        foreach ($missing_columns as $column) {
            echo "<li>{$column}</li>";
        }
        echo "</ul>";
        
        echo "<h3>🔧 Auto-Fix Available</h3>";
        echo "<p><a href='?fix=1' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Add Missing Columns Automatically</a></p>";
        echo "<p><em>Or manually run the SQL file: travel_expenses_resubmission.sql</em></p>";
    } else {
        echo "<h3>✅ All Required Columns Present</h3>";
        echo "<p>The resubmission functionality should work correctly.</p>";
        
        // Test a simple resubmission query
        $user_id = $_SESSION['user_id'];
        $test_query = "SELECT id, resubmission_count, max_resubmissions FROM travel_expenses WHERE user_id = ? AND status = 'rejected' LIMIT 1";
        $stmt = $conn->prepare($test_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo "<h3>📝 Test Query Results</h3>";
            echo "<p>Found rejected expense ID: {$row['id']}</p>";
            echo "<p>Current resubmission count: {$row['resubmission_count']}</p>";
            echo "<p>Max resubmissions allowed: {$row['max_resubmissions']}</p>";
        } else {
            echo "<h3>📝 No Rejected Expenses Found</h3>";
            echo "<p>Create a rejected expense to test resubmission functionality.</p>";
        }
        
        $stmt->close();
    }
    
} catch (Exception $e) {
    echo "<h3>❌ Database Error</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Auto-fix functionality
if (isset($_GET['fix']) && $_GET['fix'] == '1') {
    echo "<h2>🔧 Auto-Fix in Progress</h2>";
    
    try {
        // Add the missing columns
        $alterQueries = array(
            "ALTER TABLE travel_expenses ADD COLUMN original_expense_id INT DEFAULT NULL COMMENT 'ID of the original expense if this is a resubmission'",
            "ALTER TABLE travel_expenses ADD COLUMN resubmission_count INT DEFAULT 0 COMMENT 'Number of times this expense has been resubmitted'",
            "ALTER TABLE travel_expenses ADD COLUMN is_resubmitted TINYINT(1) DEFAULT 0 COMMENT 'Whether this expense is a resubmission of another'",
            "ALTER TABLE travel_expenses ADD COLUMN resubmitted_from INT DEFAULT NULL COMMENT 'ID of the expense this was resubmitted from'",
            "ALTER TABLE travel_expenses ADD COLUMN resubmission_date TIMESTAMP NULL DEFAULT NULL COMMENT 'When this expense was resubmitted'",
            "ALTER TABLE travel_expenses ADD COLUMN max_resubmissions INT DEFAULT 3 COMMENT 'Maximum allowed resubmissions for this expense'"
        );
        
        foreach ($alterQueries as $query) {
            try {
                $conn->query($query);
                echo "<p>✅ Successfully executed: " . substr($query, 0, 50) . "...</p>";
            } catch (Exception $e) {
                // Column might already exist
                echo "<p>⚠️ " . $e->getMessage() . "</p>";
            }
        }
        
        // Update existing records
        $updateQuery = "UPDATE travel_expenses SET resubmission_count = 0, is_resubmitted = 0, max_resubmissions = 3 WHERE resubmission_count IS NULL";
        $conn->query($updateQuery);
        echo "<p>✅ Updated existing records with default values</p>";
        
        echo "<p><strong>🎉 Auto-fix completed! Please refresh this page to verify.</strong></p>";
        echo "<p><a href='?' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Refresh & Check Again</a></p>";
        
    } catch (Exception $e) {
        echo "<p>❌ Auto-fix failed: " . $e->getMessage() . "</p>";
        echo "<p>Please run the SQL file manually.</p>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Resubmission Schema Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; max-width: 800px; }
        h2, h3 { color: #333; }
        ul { list-style-type: none; padding: 0; }
        li { padding: 5px 0; }
        .status { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Travel Expense Resubmission Schema Check</h1>
    <p>This tool checks if the required database columns for resubmission tracking are present.</p>
    <hr>
</body>
</html>