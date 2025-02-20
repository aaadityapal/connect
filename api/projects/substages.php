<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/db_connect.php';
header('Content-Type: application/json');

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        throw new Exception('User not authenticated');
    }

    $query = "
        SELECT 
            ss.*,
            ps.stage_number,
            p.title as project_title
        FROM crm.project_substages ss
        LEFT JOIN crm.project_stages ps ON ss.stage_id = ps.id
        LEFT JOIN crm.projects p ON ps.project_id = p.id
        WHERE ss.assigned_to = :user_id 
        AND ss.deleted_at IS NULL
        AND ss.status IN ('not_started', 'pending')
        ORDER BY ss.end_date ASC
    ";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute(['user_id' => $userId]);
        $substages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($substages);
    } catch (PDOException $e) {
        throw new Exception("Database error: " . $e->getMessage());
    }

} catch (Exception $e) {
    error_log("Error in substages.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} 