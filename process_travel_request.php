<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'HR' && $_SESSION['role'] !== 'senior_manager')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = $_POST['request_id'];
    $action = $_POST['action'];
    $userRole = $_SESSION['role'];
    
    try {
        $status = '';
        if ($userRole === 'senior_manager') {
            $status = $action === 'approve' ? 'approved_by_manager' : 'rejected';
        } else {
            $status = $action === 'approve' ? 'approved_by_hr' : 'rejected';
        }

        $sql = "UPDATE travel_allowances SET status = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $requestId]);

        // Send notification
        require_once 'includes/notification_system.php';
        sendNotification($requestId, $status);

        echo json_encode([
            'success' => true,
            'message' => 'Travel request ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error processing request: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
