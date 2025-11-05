<?php
header('Content-Type: application/json');
require_once 'config/db_connect.php';

try {
    // Simple test query to check if the connection works
    $query = "SELECT 
                a.id as attendance_id,
                u.username,
                u.role,
                a.date,
                a.punch_out,
                a.overtime_hours,
                a.work_report,
                a.overtime_status,
                s.end_time as shift_end_time
              FROM attendance a
              JOIN users u ON a.user_id = u.id
              LEFT JOIN user_shifts us ON u.id = us.user_id AND a.date BETWEEN us.effective_from AND COALESCE(us.effective_to, '9999-12-31')
              LEFT JOIN shifts s ON us.shift_id = s.id
              WHERE a.date BETWEEN '2025-10-01' AND '2025-10-31'
              LIMIT 5";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $results,
        'count' => count($results)
    ]);
    
} catch (Exception $e) {
    error_log("Test query error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>