<?php
session_start();
header('Content-Type: application/json');

$otp = $_POST['otp'] ?? '';
$email = $_POST['email'] ?? '';

if (empty($otp)) {
    echo json_encode(['status' => 'error', 'message' => 'OTP is required']);
    exit;
}

if (!isset($_SESSION['reset_otp']) || !isset($_SESSION['reset_email'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Request a new OTP.']);
    exit;
}

// 1. Check expiration (10 minutes)
if (time() - ($_SESSION['reset_otp_time'] ?? 0) > 600) {
    unset($_SESSION['reset_otp'], $_SESSION['reset_otp_time'], $_SESSION['reset_email']);
    echo json_encode(['status' => 'error', 'message' => 'OTP has expired. Please request a new one.']);
    exit;
}

// 2. Verify OTP and Email
if ($_SESSION['reset_otp'] === $otp && $_SESSION['reset_email'] === $email) {
    // 3. Mark identity as verified for this session
    $_SESSION['identity_verified'] = true;
    
    // Cleanup OTP and attempts
    unset($_SESSION['reset_otp'], $_SESSION['reset_otp_time'], $_SESSION['reset_otp_attempts']);
    
    echo json_encode(['status' => 'success', 'message' => 'Identity verified successfully!']);
} else {
    // Log failed attempt
    $_SESSION['reset_otp_attempts'] = ($_SESSION['reset_otp_attempts'] ?? 0) + 1;
    
    if ($_SESSION['reset_otp_attempts'] >= 5) {
        unset($_SESSION['reset_otp'], $_SESSION['reset_otp_time'], $_SESSION['reset_otp_attempts'], $_SESSION['reset_email']);
        echo json_encode(['status' => 'error', 'message' => 'Too many failed attempts. For security, please request a new OTP.']);
    } else {
        $remaining = 5 - $_SESSION['reset_otp_attempts'];
        echo json_encode(['status' => 'error', 'message' => "Invalid OTP. You have $remaining attempts left."]);
    }
}
