<?php
header('Content-Type: application/json');
require_once 'config/db_connect.php';

try {
    // Test the full query with overtime calculation
    $query = "SELECT 
                a.id as attendance_id,
                u.username,
                u.role,
                a.date,
                a.punch_out,
                a.overtime_hours,
                a.work_report,
                a.overtime_status,
                s.end_time as shift_end_time,
                TIMESTAMPDIFF(SECOND, 
                    STR_TO_DATE(CONCAT(a.date, ' ', s.end_time), '%Y-%m-%d %H:%i:%s'),
                    STR_TO_DATE(CONCAT(
                        CASE 
                            WHEN TIME(a.punch_out) < TIME(s.end_time) THEN DATE_ADD(a.date, INTERVAL 1 DAY)
                            ELSE a.date
                        END, 
                        ' ', a.punch_out
                    ), '%Y-%m-%d %H:%i:%s')
                ) as overtime_seconds
              FROM attendance a
              JOIN users u ON a.user_id = u.id
              LEFT JOIN user_shifts us ON u.id = us.user_id AND a.date BETWEEN us.effective_from AND COALESCE(us.effective_to, '9999-12-31')
              LEFT JOIN shifts s ON us.shift_id = s.id
              WHERE a.date BETWEEN '2025-10-01' AND '2025-10-31'
              HAVING overtime_seconds >= 5400
              ORDER BY a.date DESC
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
    error_log("Full query error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>