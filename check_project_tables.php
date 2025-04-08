<?php
// Test script to check project-related tables
session_start();
require_once 'config/db_connect.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Project Tables Check</h1>";
echo "<p>This script checks if the projects, project_stages, and project_substages tables exist and have data.</p>";

// Check projects table
echo "<h2>Projects Table</h2>";
$sql = "SHOW TABLES LIKE 'projects'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    echo "<p style='color:green'>✓ Projects table exists</p>";
    
    // Check data
    $sql = "SELECT * FROM projects WHERE id IN (SELECT DISTINCT entity_id FROM assignment_status_logs WHERE entity_type = 'project') LIMIT 5";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        echo "<p style='color:green'>✓ Found " . $result->num_rows . " projects referenced in assignment logs</p>";
        
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Project Name</th><th>Status</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . ($row['project_name'] ?? 'N/A') . "</td>";
            echo "<td>" . ($row['status'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color:red'>✗ No projects found that are referenced in assignment logs</p>";
        echo "<p>Error: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:red'>✗ Projects table does not exist</p>";
}

// Check project_stages table
echo "<h2>Project Stages Table</h2>";
$sql = "SHOW TABLES LIKE 'project_stages'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    echo "<p style='color:green'>✓ Project Stages table exists</p>";
    
    // Check data
    $sql = "SELECT * FROM project_stages WHERE id IN (SELECT DISTINCT entity_id FROM assignment_status_logs WHERE entity_type = 'stage') LIMIT 5";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        echo "<p style='color:green'>✓ Found " . $result->num_rows . " stages referenced in assignment logs</p>";
        
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Stage Number</th><th>Project ID</th><th>Status</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . ($row['stage_number'] ?? 'N/A') . "</td>";
            echo "<td>" . ($row['project_id'] ?? 'N/A') . "</td>";
            echo "<td>" . ($row['status'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color:red'>✗ No stages found that are referenced in assignment logs</p>";
        echo "<p>Error: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:red'>✗ Project Stages table does not exist</p>";
}

// Check project_substages table
echo "<h2>Project Substages Table</h2>";
$sql = "SHOW TABLES LIKE 'project_substages'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    echo "<p style='color:green'>✓ Project Substages table exists</p>";
    
    // Check data
    $sql = "SELECT * FROM project_substages WHERE id IN (SELECT DISTINCT entity_id FROM assignment_status_logs WHERE entity_type = 'substage') LIMIT 5";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        echo "<p style='color:green'>✓ Found " . $result->num_rows . " substages referenced in assignment logs</p>";
        
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Title</th><th>Stage ID</th><th>Status</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . ($row['title'] ?? 'N/A') . "</td>";
            echo "<td>" . ($row['stage_id'] ?? 'N/A') . "</td>";
            echo "<td>" . ($row['status'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color:red'>✗ No substages found that are referenced in assignment logs</p>";
        echo "<p>Error: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:red'>✗ Project Substages table does not exist</p>";
}

// Check assignment status logs
echo "<h2>Assignment Status Logs</h2>";
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not logged in';
echo "<p>Current user ID: $user_id</p>";

$sql = "SELECT * FROM assignment_status_logs WHERE assigned_to = ? AND new_status = 'assigned' ORDER BY timestamp DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

echo "<p>Found " . $result->num_rows . " assignments for user ID: " . $_SESSION['user_id'] . "</p>";

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Type</th><th>Entity ID</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['entity_type'] . "</td>";
        echo "<td>" . $row['entity_id'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} 

// Test actual notification query 
echo "<h2>Testing Notifications Query</h2>";

// Project assignments
$sql = "SELECT 
        'project' as source_type,
        p.id as source_id,
        CONCAT('Project Assignment: ', p.title) as title,
        CONCAT('You have been assigned to project: ', p.title) as message,
        l.created_at as created_at,
        NULL as expiration_date,
        'fas fa-user-plus' as icon,
        'success' as type,
        CONCAT('view_project.php?id=', p.id) as action_url,
        0 as read_status
    FROM assignment_status_logs l
    JOIN projects p ON p.id = l.entity_id AND l.entity_type = 'project'
    WHERE l.new_status = 'assigned'
    AND l.entity_type = 'project'
    AND l.assigned_to = ?
    ORDER BY l.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$project_count = $result->num_rows;
echo "<p>Project assignments query found " . $project_count . " results for user " . $_SESSION['user_id'] . "</p>";

if ($project_count > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Source Type</th><th>Source ID</th><th>Title</th><th>Message</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['source_type'] . "</td>";
        echo "<td>" . $row['source_id'] . "</td>";
        echo "<td>" . $row['title'] . "</td>";
        echo "<td>" . $row['message'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color:red'>No project assignments found with the notification query!</p>";
    echo "<pre>SQL: " . str_replace('?', $_SESSION['user_id'], $sql) . "</pre>";
}

// Add a link back to the dashboard
echo "<p><a href='index.php'>Return to Dashboard</a></p>";
?> 