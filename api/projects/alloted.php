<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db_connect.php';
header('Content-Type: application/json');

try {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $userId = $_SESSION['user_id'] ?? null; // Get logged in user ID
    if (!$userId) {
        throw new Exception('User not authenticated');
    }

    $query = "
        SELECT id, title, description, project_type, start_date, end_date, status
        FROM projects
        WHERE assigned_to = :user_id 
        AND deleted_at IS NULL
        ORDER BY end_date ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $userId]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($projects);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in alloted.php: " . $e->getMessage());
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}