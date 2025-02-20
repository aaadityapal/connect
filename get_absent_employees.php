<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';

header('Content-Type: application/json');

try {
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

    // Query for absent employees (who haven't punched in today and aren't on leave)
    $query = "SELECT 
                u.id,
                u.username,
                u.department,
                u.employee_id
              FROM users u
              WHERE u.id NOT IN (
                  SELECT user_id 
                  FROM attendance 
                  WHERE DATE(date) = ?
              )
              AND u.id NOT IN (
                  SELECT user_id 
                  FROM leaves 
                  WHERE ? BETWEEN from_date AND to_date 
                  AND status = 'approved'
              )
              AND u.status = 'active'
              ORDER BY u.username ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $date, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = [
            'id' => $row['id'],
            'employee_id' => $row['employee_id'],
            'username' => $row['username'],
            'department' => $row['department']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $employees,
        'count' => count($employees)
    ]);

} catch (Exception $e) {
    error_log("Absent Employees Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}