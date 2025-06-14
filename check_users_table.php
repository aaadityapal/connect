<?php
// Include database connection
include 'config/db_connect.php';

// Set header for HTML output
header('Content-Type: text/html');
echo "<html><head><title>Database Check</title></head><body>";

// Check users table structure
echo "<h2>Users Table Structure</h2>";
$result = $conn->query("DESCRIBE users");
if ($result) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Error getting table structure: " . $conn->error . "</p>";
}

// Get one manager record
echo "<h2>Sample Manager Record</h2>";
$result = $conn->query("SELECT * FROM users WHERE role = 'Senior Manager (Studio)' OR role = 'Senior Manager (Site)' LIMIT 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<table border='1'>";
    foreach ($row as $key => $value) {
        echo "<tr><td><strong>" . $key . "</strong></td><td>" . $value . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No manager records found.</p>";
}

// Check manager_payments records
echo "<h2>Manager Payments Records</h2>";
$result = $conn->query("SELECT * FROM manager_payments LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr>";
    $fields = $result->fetch_fields();
    foreach ($fields as $field) {
        echo "<th>" . $field->name . "</th>";
    }
    echo "</tr>";
    
    // Reset result pointer
    $result->data_seek(0);
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . $value . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No payment records found.</p>";
}

// Check get_managers.php query result
echo "<h2>Get Managers Query Result</h2>";
$result = $conn->query("SELECT u.id, u.username, u.role,
                      (SELECT COALESCE(SUM(amount), 0) FROM manager_payments WHERE manager_id = u.id) as amount_paid
                      FROM users u
                      WHERE u.role = 'Senior Manager (Studio)' OR u.role = 'Senior Manager (Site)'
                      LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr><th>ID</th><th>Username</th><th>Role</th><th>Amount Paid</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['username'] . "</td>";
        echo "<td>" . $row['role'] . "</td>";
        echo "<td>" . $row['amount_paid'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No manager records found.</p>";
}

echo "</body></html>";
?> 