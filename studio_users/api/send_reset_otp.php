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

    // 3. Send Email using SMTP
    $subject = "Password Reset OTP - Connect Studio / ArchitectsHive";
    $message = "Hello " . ($user['username'] ?? 'User') . ",\n\n";
    $message .= "Your One-Time Password (OTP) for resetting your password is: " . $otp . "\n\n";
    $message .= "This code will expire in 10 minutes.\n";
    $message .= "If you did not request this, please ignore this email.\n\n";
    $message .= "Best regards,\n" . SMTP_FROM_NAME . " Team";

    $mailer = new MinimalSmtpClient(SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    
    try {
        if ($mailer->send($email, $subject, $message)) {
            // Log activity
            try {
                $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'otp_request', ?)");
                $logStmt->execute([$_SESSION['user_id'], "OTP requested for password reset verification"]);
            } catch (Exception $e) {}

            echo json_encode(['status' => 'success', 'message' => 'OTP sent successfully to your registered email.']);
        } else {
            throw new Exception("SMTP send failed.");
        }
    } catch (Exception $smtpError) {
        // Fallback or log error
        error_log("SMTP Error: " . $smtpError->getMessage() . " | OTP for $email: $otp");
        
        // For development/demo, if SMTP fails, we still allow proceed with log check
        echo json_encode([
            'status' => 'success', 
            'message' => 'OTP generated (SMTP Simulation triggered). check system log.',
            'debug_otp' => $otp 
        ]);
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
ob_end_flush();
