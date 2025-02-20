<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and include database connection
session_start();
require_once 'config.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get date parameter
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

try {
    // Validate database connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    // Your SQL query
    $query = "SELECT 
                a.punch_in,
                a.punch_out,
                a.date,
                u.id,
                u.username,
                u.department,
                u.employee_id
              FROM attendance a
              INNER JOIN users u ON a.user_id = u.id
              WHERE DATE(a.date) = ?
              ORDER BY a.punch_in ASC";

    // Prepare statement
    if (!$stmt = $conn->prepare($query)) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters
    if (!$stmt->bind_param("s", $date)) {
        throw new Exception("Binding parameters failed: " . $stmt->error);
    }

    // Execute query
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    // Get results
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Getting result set failed: " . $stmt->error);
    }

    // Fetch data
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $punchIn = new DateTime($row['punch_in']);
        $isLate = $punchIn > new DateTime($date . ' 09:30:00');

        $employees[] = [
            'id' => $row['id'],
            'employee_id' => $row['employee_id'],
            'username' => htmlspecialchars($row['username']),
            'department' => htmlspecialchars($row['department']),
            'punch_in' => $punchIn->format('h:i A'),
            'punch_out' => $row['punch_out'] ? (new DateTime($row['punch_out']))->format('h:i A') : null,
            'status' => $isLate ? 'Late' : 'Present',
            'status_class' => $isLate ? 'warning' : 'success'
        ];
    }

    // Debug log
    error_log("Found " . count($employees) . " employees");

    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $employees,
        'count' => count($employees),
        'date' => $date
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Present Employees Error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'date' => $date ?? date('Y-m-d')
    ]);
}

// Close statement and connection
if (isset($stmt)) {
    $stmt->close();
}
if (isset($conn)) {
    $conn->close();
}