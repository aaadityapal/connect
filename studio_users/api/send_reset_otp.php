<?php
ob_start(); 
header('Content-Type: application/json');

try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized session. Please login again.']);
        exit;
    }

    require_once __DIR__ . '/../../config/db_connect.php';
    require_once __DIR__ . '/../../config/email_config.php';
    require_once __DIR__ . '/includes/smtp_client.php';

    $email = $_POST['email'] ?? '';

    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Email is required']);
        exit;
    }

    // 1. Double check the email matches the session user
    $stmt = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['email'] !== $email) {
        echo json_encode(['status' => 'error', 'message' => 'Email does not match our records.']);
        exit;
    }

    // 2. Generate OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['reset_otp'] = $otp;
    $_SESSION['reset_otp_time'] = time();
    $_SESSION['reset_email'] = $email;

    // 3. Send Email using SMTP to HR precisely as requested
    $hr_email = "hr.architectshive@gmail.com";
    $subject = "Password Reset OTP Request - " . ($user['username'] ?? 'User');
    $user_email = $user['email'];
    
    $subject = "Password Reset Verification Code - ArchitectsHive";
    $message_to_user = "Your verification code is: " . $otp . "\r\n\r\n";
    $message_to_user .= "This code will expire in 10 minutes.\r\n";
    $message_to_user .= "If you did not request this code, please ignore this email.";

    $message_to_hr = "Hello HR Team,\r\n\r\n";
    $message_to_hr .= "A user has requested a One-Time Password (OTP) to reset their password.\r\n\r\n";
    $message_to_hr .= "User Name: " . ($user['username'] ?? 'User') . "\r\n";
    $message_to_hr .= "User Email: " . $email . "\r\n\r\n";
    $message_to_hr .= "The generated OTP for this user is: " . $otp . "\r\n\r\n";
    $message_to_hr .= "This code will expire in 10 minutes.\r\n";
    $message_to_hr .= "Please securely convey this OTP to the user for verification.\r\n\r\n";
    $message_to_hr .= "System Generated Request.";

    $mailer = new MinimalSmtpClient(SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    
    try {
        $mail_sent_to_user = $mailer->send($user_email, $subject, $message_to_user);
        $mail_sent_to_hr   = $mailer->send($hr_email, $subject, $message_to_hr);

        if ($mail_sent_to_user || $mail_sent_to_hr) {
            // Log activity
            try {
                $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, ?, ?)");
                $logStmt->execute([$_SESSION['user_id'], 'password_reset_otp', 'OTP generated and sent for password reset.']);
            } catch (Exception $e) {}

            echo json_encode(['status' => 'success', 'message' => 'OTP has been sent to your registered email. Check your inbox.']);
        } else {
            throw new Exception("Failed to send OTP email to user and HR.");
        }
    } catch (Exception $smtpError) {
        // Fallback or log error
        error_log("SMTP Error: " . $smtpError->getMessage() . " | OTP for $email: $otp");
        
        echo json_encode([
            'status' => 'error', 
            'message' => 'Failed to send OTP email. SMTP Error: ' . $smtpError->getMessage()
        ]);
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
ob_end_flush();
