<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }

    // Validate parameters
    if (!isset($_POST['type']) || !isset($_POST['id']) || !is_numeric($_POST['id'])) {
        throw new Exception('Invalid parameters');
    }

    $type = $_POST['type'];
    $id = (int)$_POST['id'];

    // Define table names for different types
    $tables = [
        'announcement' => 'announcements',
        'event' => 'events',
        'holiday' => 'holidays'
    ];

    // Validate type
    if (!array_key_exists($type, $tables)) {
        throw new Exception('Invalid item type');
    }

    $table = $tables[$type];

    // Delete the item
    $query = "DELETE FROM $table WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute(['id' => $id]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => ucfirst($type) . ' deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete ' . $type);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}