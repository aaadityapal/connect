<?php
function sendNotification($requestId, $status) {
    global $pdo;
    
    // Get request and user details
    $sql = "SELECT ta.*, u.email, u.name, 
            (SELECT email FROM users WHERE role = 'hr' LIMIT 1) as hr_email,
            (SELECT email FROM users WHERE role = 'senior_manager' LIMIT 1) as manager_email
            FROM travel_allowances ta 
            JOIN users u ON ta.user_id = u.id 
            WHERE ta.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Prepare notification content
    switch ($status) {
        case 'pending':
            // Notify manager
            $subject = "New Travel Allowance Request";
            $message = "New travel allowance request from {$request['name']} needs your approval.";
            sendEmail($request['manager_email'], $subject, $message);
            break;
            
        case 'approved_by_manager':
            // Notify HR
            $subject = "Travel Allowance Request - Manager Approved";
            $message = "A travel allowance request from {$request['name']} has been approved by the manager and needs your review.";
            sendEmail($request['hr_email'], $subject, $message);
            break;
            
        case 'approved_by_hr':
            // Notify employee
            $subject = "Travel Allowance Request Approved";
            $message = "Your travel allowance request has been approved.";
            sendEmail($request['email'], $subject, $message);
            break;
            
        case 'rejected':
            // Notify employee
            $subject = "Travel Allowance Request Rejected";
            $message = "Your travel allowance request has been rejected. Please check the comments for more information.";
            sendEmail($request['email'], $subject, $message);
            break;
    }
    
    // Store notification in database
    $sql = "INSERT INTO notifications (user_id, type, message, related_id, created_at) 
            VALUES (?, 'travel_allowance', ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$request['user_id'], $message, $requestId]);
}

function sendEmail($to, $subject, $message) {
    // Configure your email settings
    $headers = "From: your-system@example.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // Send email
    mail($to, $subject, $message, $headers);
}
