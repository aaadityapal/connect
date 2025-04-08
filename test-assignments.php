<?php
// Test script to display assignments for current user
session_start();
require_once 'config/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

// Get current user ID
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not logged in';

echo "<h1>Assignment Test Script</h1>";
echo "<p>Current user ID: $user_id</p>";

if (!isset($_SESSION['user_id'])) {
    echo "<p>You are not logged in. Please log in to see assignments.</p>";
    exit;
}

// Test query for assignment logs
$sql = "SELECT * FROM assignment_status_logs WHERE assigned_to = ? AND new_status = 'assigned' ORDER BY timestamp DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Assignment Logs for Your User</h2>";
echo "<p>Found " . $result->num_rows . " assignments for user ID: " . $_SESSION['user_id'] . "</p>";

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Type</th><th>Entity ID</th><th>Status</th><th>Timestamp</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['entity_type'] . "</td>";
        echo "<td>" . $row['entity_id'] . "</td>";
        echo "<td>" . $row['new_status'] . "</td>";
        echo "<td>" . $row['timestamp'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No assignments found for your user.</p>";
}

// Check if the user ID in the logs matches current session
echo "<h2>All Assignment Logs</h2>";
$sql = "SELECT assigned_to, COUNT(*) as count FROM assignment_status_logs WHERE new_status = 'assigned' GROUP BY assigned_to";
$result = $conn->query($sql);

echo "<p>Users with assignments:</p>";
echo "<table border='1'>";
echo "<tr><th>User ID</th><th>Count</th><th>Current User?</th></tr>";

while ($row = $result->fetch_assoc()) {
    $is_current = ($row['assigned_to'] == $_SESSION['user_id']) ? 'Yes' : 'No';
    echo "<tr>";
    echo "<td>" . $row['assigned_to'] . "</td>";
    echo "<td>" . $row['count'] . "</td>";
    echo "<td>" . $is_current . "</td>";
    echo "</tr>";
}
echo "</table>";

// View assignments for a specific user (for testing)
if (isset($_GET['view_user'])) {
    $view_user = intval($_GET['view_user']);
    
    echo "<h2>Assignments for User ID: $view_user</h2>";
    
    $sql = "SELECT * FROM assignment_status_logs WHERE assigned_to = ? AND new_status = 'assigned' ORDER BY timestamp DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $view_user);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Type</th><th>Entity ID</th><th>Status</th><th>Timestamp</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['entity_type'] . "</td>";
            echo "<td>" . $row['entity_id'] . "</td>";
            echo "<td>" . $row['new_status'] . "</td>";
            echo "<td>" . $row['timestamp'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No assignments found for this user.</p>";
    }
}

// Add form to view assignments for any user
echo "<h2>View Assignments for Another User</h2>";
echo "<form method='get'>";
echo "User ID: <input type='number' name='view_user' required>";
echo "<button type='submit'>View</button>";
echo "</form>";

// Add form to test logging in as a different user (for testing only)
echo "<h2>Temporary: Switch User ID</h2>";
echo "<form method='post' action='switch_user.php'>";
echo "Switch to User ID: <input type='number' name='user_id' required>";
echo "<button type='submit'>Switch</button>";
echo "</form>";

?> 