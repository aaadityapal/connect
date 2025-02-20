<?php
require_once 'config/db_connect.php';

if (!isset($_GET['id'])) {
    die('No agreement ID specified');
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=your_database", "username", "password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT pdf_path, client_name FROM agreements WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $agreement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agreement) {
        die('Agreement not found');
    }
    
    if (!file_exists($agreement['pdf_path'])) {
        die('PDF file not found');
    }
    
    // Output the PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Agreement_' . $agreement['client_name'] . '.pdf"');
    readfile($agreement['pdf_path']);
    
} catch(PDOException $e) {
    die("Error retrieving agreement: " . $e->getMessage());
}
?> 