<?php
// Start session for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Query to get managers with specific roles
$query = "SELECT id, username, role, designation 
          FROM users 
          WHERE role IN ('Senior Manager (Studio)', 'Senior Manager (Site)') 
          OR designation IN ('Senior Manager (Studio)', 'Senior Manager (Site)')
          ORDER BY username ASC";
          
$result = $conn->query($query);

if (!$result) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database query failed: ' . $conn->error
    ]);
    exit();
}

// Fetch all managers
$managers = [];
while ($row = $result->fetch_assoc()) {
    $managers[] = [
        'id' => $row['id'],
        'username' => htmlspecialchars($row['username']),
        'role' => !empty($row['role']) ? htmlspecialchars($row['role']) : htmlspecialchars($row['designation'])
    ];
}

// Return the data as JSON
header('Content-Type: application/json');
echo json_encode($managers);
exit();
?> 