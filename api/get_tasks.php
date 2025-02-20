<?php
require_once 'config/db_connect.php';

$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;

// Add user filter to the query if specified
$where_clause = "p.status != 'Archived' AND u.deleted_at IS NULL";
if ($user_id) {
    $where_clause .= " AND p.assigned_to = :user_id";
}

$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.project_name,
        p.client_name,
        p.created_at as start_date,
        p.status,
        p.assigned_to,
        CASE 
            WHEN p.stage5_status = 'Completed' THEN 'Stage 5'
            WHEN p.stage4_status = 'Completed' THEN 'Stage 4'
            WHEN p.stage3_status = 'Completed' THEN 'Stage 3'
            WHEN p.stage2_status = 'Completed' THEN 'Stage 2'
            WHEN p.stage1_status = 'Completed' THEN 'Stage 1'
            ELSE 'Stage 1'
        END as current_stage,
        u.username as assigned_person_name,
        u.position as assigned_person_position,
        u.department as assigned_person_department
    FROM projects p
    LEFT JOIN users u ON p.assigned_to = u.id
    WHERE $where_clause
    ORDER BY p.created_at DESC
");

if ($user_id) {
    $stmt->bindParam(':user_id', $user_id);
}

$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ensure dates are in the correct format for FullCalendar
foreach ($events as &$event) {
    $event['start_date'] = date('Y-m-d', strtotime($event['start_date']));
}

header('Content-Type: application/json');
echo json_encode($events); 