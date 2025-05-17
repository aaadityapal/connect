<?php
// Script to update the attendance table with new punch-out location columns

// Include database connection
require_once 'config/db_connect.php';

// Read the SQL file
$sql_file = file_get_contents('update_attendance_table.sql');

// Split the SQL file into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql_file)));

// Execute each statement
$success = true;
$messages = [];

foreach ($statements as $statement) {
    // Skip comments and empty statements
    if (empty($statement) || strpos($statement, '--') === 0 || strpos($statement, '/*') === 0) {
        continue;
    }
    
    // Execute the statement
    $result = $conn->query($statement);
    
    if (!$result) {
        $success = false;
        $messages[] = "Error executing statement: " . $conn->error . "\nStatement: " . $statement;
    } else {
        $messages[] = "Successfully executed: " . substr($statement, 0, 50) . "...";
    }
}

// Output the results
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Table Update</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1 {
            color: #333;
        }
        .success {
            color: #28a745;
            padding: 10px;
            background-color: #d4edda;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error {
            color: #dc3545;
            padding: 10px;
            background-color: #f8d7da;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .message {
            margin-bottom: 5px;
            padding: 5px 10px;
            border-left: 3px solid #ccc;
        }
    </style>
</head>
<body>
    <h1>Attendance Table Update</h1>
    
    <?php if ($success): ?>
        <div class="success">
            <strong>Success!</strong> The attendance table has been updated successfully.
        </div>
    <?php else: ?>
        <div class="error">
            <strong>Error!</strong> There were issues updating the attendance table.
        </div>
    <?php endif; ?>
    
    <h2>Execution Details:</h2>
    <div>
        <?php foreach ($messages as $message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endforeach; ?>
    </div>
    
    <h2>Table Structure:</h2>
    <pre>
<?php
// Display the current table structure
$result = $conn->query("DESCRIBE attendance");
if ($result) {
    echo "Field | Type | Null | Key | Default | Extra\n";
    echo "------|------|------|-----|---------|------\n";
    while ($row = $result->fetch_assoc()) {
        echo "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']} | {$row['Extra']}\n";
    }
} else {
    echo "Error retrieving table structure: " . $conn->error;
}
?>
    </pre>
    
    <p><a href="similar_dashboard.php">Return to Dashboard</a></p>
</body>
</html> 