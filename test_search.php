<?php
// Include database connection
require_once 'config/db_connect.php';

// Test search functionality
$search = 'Aditya';

try {
    $sql = "SELECT labour_id, full_name, phone_number, labour_type 
            FROM hr_labours 
            WHERE full_name LIKE ? 
               OR phone_number LIKE ? 
               OR labour_type LIKE ?
            ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $searchTerm = '%' . $search . '%';
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Search term: " . $search . "\n";
    echo "Results found: " . count($results) . "\n";
    
    foreach ($results as $row) {
        echo "ID: " . $row['labour_id'] . ", Name: " . $row['full_name'] . ", Phone: " . $row['phone_number'] . ", Type: " . $row['labour_type'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>