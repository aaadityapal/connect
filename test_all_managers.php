<?php
require_once 'config/db_connect.php';

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
    
    echo "Studio Managers:\n";
    foreach ($studioManagers as $manager) {
        echo "- ID: " . $manager['id'] . ", Name: " . $manager['name'] . ", Role: " . $manager['role'] . "\n";
        echo "  Display format: " . $manager['name'] . " (" . $manager['role'] . ")\n";
    }
    
    echo "\nSite Managers:\n";
    foreach ($siteManagers as $manager) {
        echo "- ID: " . $manager['id'] . ", Name: " . $manager['name'] . ", Role: " . $manager['role'] . "\n";
        echo "  Display format: " . $manager['name'] . " (" . $manager['role'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>