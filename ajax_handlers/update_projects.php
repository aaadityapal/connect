<?php
require_once '../config/db_connect.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = [
        'success' => false,
        'data' => null,
        'error' => null
    ];

    try {
        $from_date = $_POST['from_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null;

        if (!$from_date || !$end_date) {
            throw new Exception('Date range is required');
        }

        // Get projects data for the date range
        $projects_query = "SELECT 
            COUNT(*) as total_projects,
            SUM(CASE WHEN project_type = 'architecture' THEN 1 ELSE 0 END) as architecture_total,
            SUM(CASE WHEN project_type = 'interior' THEN 1 ELSE 0 END) as interior_total,
            SUM(CASE WHEN project_type = 'construction' THEN 1 ELSE 0 END) as construction_total
            FROM projects 
            WHERE status != 'cancelled'
            AND created_at BETWEEN ? AND ?";
        
        $stmt = $conn->prepare($projects_query);
        $stmt->bind_param('ss', $from_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        $response['success'] = true;
        $response['data'] = $data;

    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} 