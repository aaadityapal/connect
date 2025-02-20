<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
require_once '../config/db_connect.php';

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Get user ID from session
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        throw new Exception('User not authenticated');
    }

    // Get date filters
    $dateFrom = $_GET['from'] ?? date('Y-m-01'); // First day of current month
    $dateTo = $_GET['to'] ?? date('Y-m-d'); // Today

    // Get total projects
    $projects_query = "SELECT COUNT(*) as total_projects 
                      FROM projects 
                      WHERE assigned_to = ? 
                      AND deleted_at IS NULL 
                      AND (start_date BETWEEN ? AND ? OR end_date BETWEEN ? AND ?)";
    
    $stmt = $conn->prepare($projects_query);
    $stmt->bind_param("issss", $user_id, $dateFrom, $dateTo, $dateFrom, $dateTo);
    $stmt->execute();
    $projects_result = $stmt->get_result();
    $projects_data = $projects_result->fetch_assoc();

    // Get total stages
    $stages_query = "SELECT COUNT(*) as total_stages 
                    FROM project_stages ps
                    JOIN projects p ON ps.project_id = p.id
                    WHERE p.assigned_to = ?
                    AND ps.deleted_at IS NULL
                    AND p.deleted_at IS NULL
                    AND (p.start_date BETWEEN ? AND ? OR p.end_date BETWEEN ? AND ?)";
    
    $stmt = $conn->prepare($stages_query);
    $stmt->bind_param("issss", $user_id, $dateFrom, $dateTo, $dateFrom, $dateTo);
    $stmt->execute();
    $stages_result = $stmt->get_result();
    $stages_data = $stages_result->fetch_assoc();

    // Get total substages
    $substages_query = "SELECT COUNT(*) as total_substages 
                       FROM project_substages pss
                       JOIN project_stages ps ON pss.stage_id = ps.id
                       JOIN projects p ON ps.project_id = p.id
                       WHERE p.assigned_to = ?
                       AND pss.deleted_at IS NULL
                       AND ps.deleted_at IS NULL
                       AND p.deleted_at IS NULL
                       AND (p.start_date BETWEEN ? AND ? OR p.end_date BETWEEN ? AND ?)";
    
    $stmt = $conn->prepare($substages_query);
    $stmt->bind_param("issss", $user_id, $dateFrom, $dateTo, $dateFrom, $dateTo);
    $stmt->execute();
    $substages_result = $stmt->get_result();
    $substages_data = $substages_result->fetch_assoc();

    $summary = [
        'total_projects' => $projects_data['total_projects'],
        'total_stages' => $stages_data['total_stages'],
        'total_substages' => $substages_data['total_substages']
    ];

    // Debug output
    error_log("Tasks found: " . count($summary));
    error_log("Query parameters - User ID: $user_id, From: $dateFrom, To: $dateTo");

    echo json_encode($summary);

} catch (Exception $e) {
    error_log("Tasks API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'total_projects' => 0,
        'total_stages' => 0,
        'total_substages' => 0
    ]);
} 