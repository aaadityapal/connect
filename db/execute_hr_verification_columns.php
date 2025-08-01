<?php
// Include database connection
require_once '../config/db_connect.php';

// Path to SQL file
$sqlFile = 'add_hr_verification_columns.sql';

// Read SQL file
$sql = file_get_contents($sqlFile);

// Split SQL statements
$statements = explode(';', $sql);

// Execute each statement
$success = true;
$errors = [];

try {
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
            echo "Executed: " . substr($statement, 0, 50) . "...<br>";
        }
    }
    echo "<p style='color: green;'>All SQL statements executed successfully!</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error executing SQL: " . $e->getMessage() . "</p>";
}
?>