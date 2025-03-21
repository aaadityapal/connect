<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['offer_id']) || !isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

try {
    $offer_id = $_POST['offer_id'];
    $action = $_POST['action'];
    $user_id = $_SESSION['user_id'];

    // Validate action
    if (!in_array($action, ['accepted', 'rejected'])) {
        throw new Exception('Invalid action');
    }

    // Check if user has permission to respond to this offer letter
    $query = "SELECT id FROM offer_letters 
              WHERE id = ? AND user_id = ? AND status = 'pending'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$offer_id, $user_id]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Unauthorized access or offer letter already processed');
    }

    // Update offer letter status
    $update_query = "UPDATE offer_letters SET status = ? WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($update_query);
    $stmt->execute([$action, $offer_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Offer letter ' . $action . ' successfully'
        ]);
    } else {
        throw new Exception('Failed to update offer letter status');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 