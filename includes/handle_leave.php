<?php
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Check if user is logged in and has appropriate permissions
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate input
if (!isset($_POST['leave_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$leave_id = filter_var($_POST['leave_id'], FILTER_VALIDATE_INT);
$action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);
$action_comments = filter_var($_POST['comments'] ?? '', FILTER_SANITIZE_STRING);

if (!$leave_id || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Update leave request status
    $query = "
        UPDATE leave_request 
        SET 
            status = :status,
            action_by = :action_by,
            action_at = NOW(),
            action_comments = :action_comments,
            updated_at = NOW(),
            updated_by = :updated_by
        WHERE id = :leave_id AND status = 'pending'
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':status' => $action === 'approve' ? 'approved' : 'rejected',
        ':action_by' => $_SESSION['user_id'],
        ':action_comments' => $action_comments,
        ':updated_by' => $_SESSION['user_id'],
        ':leave_id' => $leave_id
    ]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Leave request not found or already processed');
    }

    // Get leave request details for notification
    $query = "
        SELECT lr.*, u.email, u.username, u.full_name
        FROM leave_request lr
        JOIN users u ON lr.user_id = u.id
        WHERE lr.id = :leave_id
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':leave_id' => $leave_id]);
    $leave_request = $stmt->fetch(PDO::FETCH_ASSOC);

    // Send notification to employee
    $notification_message = "Your leave request from " . 
                          date('M d, Y', strtotime($leave_request['start_date'])) . 
                          " has been " . ($action === 'approve' ? 'approved' : 'rejected');

    $query = "
        INSERT INTO notifications (user_id, message, type, created_at)
        VALUES (:user_id, :message, 'leave_update', NOW())
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':user_id' => $leave_request['user_id'],
        ':message' => $notification_message
    ]);

    // Commit transaction
    $pdo->commit();

    // Send email notification (if configured)
    if (isset($leave_request['email'])) {
        // You can implement email sending here
        // sendEmail($leave_request['email'], $notification_message);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Leave request ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully',
        'updated_count' => getPendingLeavesCount($pdo)
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error processing leave request: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to process leave request']);
} 