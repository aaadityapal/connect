<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, project_name, status, archived_date FROM projects");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'total_projects' => count($results),
        'archived_projects' => array_filter($results, function($p) {
            return $p['status'] === 'archived';
        }),
        'active_projects' => array_filter($results, function($p) {
            return $p['status'] === 'active' || $p['status'] === null;
        })
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
