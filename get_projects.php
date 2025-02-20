<?php
session_start();
require_once 'config.php';

try {
    $stmt = $pdo->prepare("
        SELECT 
            project_name,
            id,
            client_name,
            father_husband_name,
            mobile,
            email,
            location,
            project_type,
            total_cost,
            assigned_to,
            created_at,
            status,
            archived_date
        FROM projects 
        WHERE archived_date IS NULL
        ORDER BY created_at DESC
    ");
    
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($projects);
} catch (PDOException $e) {
    error_log("Error getting projects: " . $e->getMessage());
    echo json_encode([]);
} 