<?php
// Include database connection
require_once '../config/db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Only POST is allowed.'
    ]);
    exit;
}

// Get the request body
$request_body = file_get_contents('php://input');
$data = json_decode($request_body, true);

// Check if user_id is provided
if (!isset($data['user_id']) || empty($data['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User ID is required.'
    ]);
    exit;
}

$user_id = intval($data['user_id']);

try {
    // First, check if the user exists and is active
    $check_user_query = "SELECT id, username, email FROM users WHERE id = :user_id AND status = 'active'";
    $stmt = $pdo->prepare($check_user_query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found or not active.'
        ]);
        exit;
    }
    
    // Record the reminder in the database
    $insert_reminder_query = "
        INSERT INTO user_notifications (user_id, type, message, created_at, is_read)
        VALUES (:user_id, 'reminder', 'Please add your site supervision events for today.', NOW(), 0)
    ";
    
    $stmt = $pdo->prepare($insert_reminder_query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Send email notification if email is available
    $success = true;
    $message = 'Reminder sent successfully.';
    
    if (!empty($user['email'])) {
        // Email sending logic would go here
        // For now, we'll just simulate email sending
        
        // Log the email sending attempt
        $log_query = "
            INSERT INTO email_logs (recipient, subject, message, sent_at, status)
            VALUES (:recipient, 'Site Supervision Event Reminder', 'As a Site Supervisor, please add your supervision events for today.', NOW(), 'sent')
        ";
        
        $stmt = $pdo->prepare($log_query);
        $stmt->bindParam(':recipient', $user['email'], PDO::PARAM_STR);
        $stmt->execute();
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Reminder sent successfully to ' . $user['username'],
        'user_id' => $user_id
    ]);
    
} catch (PDOException $e) {
    // Return error message
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?> 