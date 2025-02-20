<?php
require_once 'config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    $sql = "SELECT 
        COUNT(*) as total_projects,
        SUM(CASE WHEN project_type = 'Architecture' THEN 1 ELSE 0 END) as architecture_count,
        SUM(CASE WHEN project_type = 'Interior' THEN 1 ELSE 0 END) as interior_count,
        SUM(CASE WHEN project_type = 'Construction' THEN 1 ELSE 0 END) as construction_count
    FROM projects 
    WHERE created_at BETWEEN ? AND ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    
    header('Content-Type: application/json');
    echo json_encode($data);
    
    mysqli_close($conn);
}
?> 