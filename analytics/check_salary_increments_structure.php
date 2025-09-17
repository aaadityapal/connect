<?php
// Script to check the actual structure of salary_increments table
require_once '../config/db_connect.php';

header('Content-Type: application/json');

try {
    // Get the actual table structure
    $structure_query = "DESCRIBE salary_increments";
    $structure_result = $pdo->query($structure_query);
    
    $columns = [];
    while ($row = $structure_result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    echo json_encode([
        'success' => true,
        'columns' => $columns,
        'table_exists' => true
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'table_exists' => false
    ]);
}
?>