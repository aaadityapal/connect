<?php
require_once 'config/db_connect.php';

try {
    // Fetch active users with role "Senior Manager (Studio)"
    $query = "SELECT id, username FROM users WHERE role = ? AND status = ? ORDER BY username";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['Senior Manager (Studio)', 'Active']);
    
    $managers = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $managers[] = [
            'id' => $row['id'],
            'name' => $row['username']
        ];
    }
    
    echo "Found " . count($managers) . " managers:\n";
    foreach ($managers as $manager) {
        echo "- ID: " . $manager['id'] . ", Name: " . $manager['name'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>