<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User ID is required']);
    exit();
}

$user_id = $data['user_id'];
$manager_id = isset($data['manager_id']) ? $data['manager_id'] : null;
$action = isset($data['action']) ? $data['action'] : 'add'; // 'add' or 'remove'

try {
    if ($action === 'add') {
        if (!$manager_id) {
            echo json_encode(['success' => false, 'error' => 'Manager ID is required to add relation']);
            exit();
        }

        // Prevent self-reporting
        if ($user_id == $manager_id) {
            echo json_encode(['success' => false, 'error' => 'User cannot report to themselves']);
            exit();
        }

        // Add the relationship
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_reporting (subordinate_id, manager_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $manager_id]);
    } 
    else if ($action === 'remove') {
        if (!$manager_id) {
            // If no manager_id, remove ALL relationships for this user
            $stmt = $pdo->prepare("DELETE FROM user_reporting WHERE subordinate_id = ?");
            $stmt->execute([$user_id]);
        } else {
            // Remove specific relationship
            $stmt = $pdo->prepare("DELETE FROM user_reporting WHERE subordinate_id = ? AND manager_id = ?");
            $stmt->execute([$user_id, $manager_id]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Hierarchy updated'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
