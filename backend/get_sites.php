<?php
// Set headers for JSON response
header('Content-Type: application/json');

// Include database connection
include_once('../includes/db_connect.php');

// Get all sites
$query = "SELECT id, name FROM sites ORDER BY name";
$result = $conn->query($query);

if ($result) {
    $sites = array();
    while ($row = $result->fetch_assoc()) {
        $sites[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'sites' => $sites
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch sites'
    ]);
}
?>