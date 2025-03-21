<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Offer letter ID not provided");
}

try {
    $offer_id = $_GET['id'];
    $user_id = $_SESSION['user_id'];
    $is_hr = ($_SESSION['role'] === 'HR');

    // Check if user has permission to download this offer letter
    $query = "SELECT ol.* FROM offer_letters ol 
             WHERE ol.id = ? AND (? = 1 OR ol.user_id = ?)";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$offer_id, $is_hr, $user_id]);
    $offer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$offer) {
        die("Unauthorized access or offer letter not found");
    }

    // Get file content
    $file_path = $offer['file_path'];
    if (!file_exists($file_path)) {
        die("File not found");
    }

    // Output headers for download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $offer['original_name'] . '"');
    header('Content-Length: ' . $offer['file_size']);
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    readfile($file_path);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
} 