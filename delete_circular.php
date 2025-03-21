<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }

    // Validate circular ID
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        throw new Exception('Invalid circular ID');
    }

    $circular_id = (int)$_POST['id'];

    // First get the circular details to delete attachment if exists
    $query = "SELECT attachment_path FROM circulars WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $circular_id]);
    $circular = $stmt->fetch(PDO::FETCH_ASSOC);

    // Delete the attachment file if it exists
    if ($circular && $circular['attachment_path'] && file_exists($circular['attachment_path'])) {
        unlink($circular['attachment_path']);
    }

    // Delete the circular from database
    $query = "DELETE FROM circulars WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute(['id' => $circular_id]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Circular deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete circular');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}