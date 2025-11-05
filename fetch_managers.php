<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

try {
    // Fetch active users with role "Senior Manager (Studio)"
    $studioQuery = "SELECT id, username, role FROM users WHERE role = ? AND status = ? ORDER BY username";
    $studioStmt = $pdo->prepare($studioQuery);
    $studioStmt->execute(['Senior Manager (Studio)', 'Active']);
    
    $studioManagers = [];
    while ($row = $studioStmt->fetch(PDO::FETCH_ASSOC)) {
        $studioManagers[] = [
            'id' => $row['id'],
            'name' => $row['username'],
            'role' => $row['role']
        ];
    }
    
    // Fetch active users with role "Senior Manager (Site)"
    $siteQuery = "SELECT id, username, role FROM users WHERE role = ? AND status = ? ORDER BY username";
    $siteStmt = $pdo->prepare($siteQuery);
    $siteStmt->execute(['Senior Manager (Site)', 'Active']);
    
    $siteManagers = [];
    while ($row = $siteStmt->fetch(PDO::FETCH_ASSOC)) {
        $siteManagers[] = [
            'id' => $row['id'],
            'name' => $row['username'],
            'role' => $row['role']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'studio_managers' => $studioManagers,
        'site_managers' => $siteManagers
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to fetch managers: ' . $e->getMessage()]);
}
?>