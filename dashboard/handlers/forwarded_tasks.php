<?php
// Basic error handling and headers
header('Content-Type: application/json');
session_start();

try {
    // Include database connection
    require_once __DIR__ . '/../../config/db_connect.php';

    // Basic authentication check
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Not authenticated");
    }

    $user_id = $_SESSION['user_id'];

    // Simple query to test the connection and data retrieval
    $query = "
        SELECT 
            ft.*,
            p.title as project_title
        FROM forward_tasks ft
        LEFT JOIN projects p ON ft.project_id = p.id
        WHERE ft.forwarded_to = ?";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $tasks = [];

    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $tasks,
        'user_id' => $user_id
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id'] ?? 'not set'
    ]);
}

// Clean up
if (isset($stmt)) {
    $stmt->close();
}
if (isset($conn)) {
    $conn->close();
} 