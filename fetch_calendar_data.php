<?php
session_start();
require_once 'config/db_connect.php';

try {
    $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    
    // Get the first and last day of the month
    $startDate = date('Y-m-d', strtotime("$year-$month-01"));
    $endDate = date('Y-m-t', strtotime($startDate));
    
    // Fetch projects for the month
    $query = "SELECT 
                p.id, 
                p.title, 
                p.start_date, 
                p.end_date, 
                p.status,
                d.name as department
              FROM projects p
              LEFT JOIN departments d ON p.department_id = d.id
              WHERE 
                (p.start_date BETWEEN ? AND ?) OR
                (p.end_date BETWEEN ? AND ?) OR
                (p.start_date <= ? AND p.end_date >= ?)
              AND p.deleted_at IS NULL
              ORDER BY p.start_date ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'projects' => $projects
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch calendar data'
    ]);
}
?> 