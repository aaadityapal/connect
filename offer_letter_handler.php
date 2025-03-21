<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    header('Location: login.php');
    exit();
}

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

if (!$id) {
    die('Invalid request');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM offer_letters WHERE id = ?");
    $stmt->execute([$id]);
    $letter = $stmt->fetch();

    if (!$letter) {
        die('Offer letter not found');
    }

    $file_path = $letter['file_path'];
    
    if ($action === 'download') {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $letter['original_name'] . '"');
        readfile($file_path);
    } elseif ($action === 'view') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $letter['original_name'] . '"');
        readfile($file_path);
    }
} catch (PDOException $e) {
    die('Error accessing file');
} 