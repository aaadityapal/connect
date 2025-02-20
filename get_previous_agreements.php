<?php
require_once 'config/db_connect.php'; // Make sure to create this file with your database credentials

try {
    $pdo = new PDO("mysql:host=localhost;dbname=your_database", "username", "password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Query to get all agreements ordered by date descending
    $stmt = $pdo->query("
        SELECT 
            id,
            ref_no,
            client_name,
            DATE_FORMAT(created_at, '%d-%m-%Y') as date,
            pdf_path
        FROM agreements 
        ORDER BY created_at DESC
    ");

    $agreements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($agreements);

} catch(PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 