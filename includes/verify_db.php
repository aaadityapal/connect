<?php
require_once 'config/db_connect.php';

try {
    // Check leave_request table structure
    $query = "DESCRIBE leave_request";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("leave_request table structure: " . print_r($columns, true));

    // Check for pending leaves
    $query = "
        SELECT 
            lr.id,
            lr.status,
            lr.user_id,
            u.username
        FROM leave_request lr
        LEFT JOIN users u ON lr.user_id = u.id
        WHERE lr.status = 'pending'
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Pending leaves check: " . print_r($results, true));

} catch (PDOException $e) {
    error_log("Database verification error: " . $e->getMessage());
}
?> 