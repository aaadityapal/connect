<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

try {
    $query = "
        SELECT 
            lt.*,
            COALESCE(
                (SELECT COUNT(*) 
                FROM leave_request lr 
                WHERE lr.user_id = :user_id 
                AND lr.leave_type = lt.name 
                AND lr.status = 'approved'
                AND YEAR(lr.start_date) = YEAR(CURRENT_DATE())
                ), 0
            ) as used_days
        FROM leave_types lt
        WHERE lt.status = 'active'
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $_GET['employee_id']]);
    $leaveTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $leaveTypes
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 