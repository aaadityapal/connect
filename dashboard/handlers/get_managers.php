<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

try {
    $query = "SELECT id, username, role FROM users 
              WHERE role IN ('Senior Manager (Studio)', 'Senior Manager (Site)')
              AND deleted_at IS NULL
              ORDER BY username ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $managers = [];
    while ($row = $result->fetch_assoc()) {
        $managers[] = [
            'id' => $row['id'],
            'username' => $row['username'],
            'role' => $row['role']
        ];
    }

    echo json_encode([
        'success' => true,
        'managers' => $managers
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching managers: ' . $e->getMessage()
    ]);
}

$conn->close(); 