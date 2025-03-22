<?php
function logError($message, $error = null, $file = null, $line = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] ";
    $logMessage .= "Message: {$message}\n";
    
    if ($error) {
        $logMessage .= "Error: " . print_r($error, true) . "\n";
    }
    
    if ($file) {
        $logMessage .= "File: {$file}\n";
    }
    
    if ($line) {
        $logMessage .= "Line: {$line}\n";
    }
    
    $logMessage .= "User ID: " . ($_SESSION['user_id'] ?? 'Not logged in') . "\n";
    $logMessage .= "URL: " . $_SERVER['REQUEST_URI'] . "\n";
    $logMessage .= "----------------------------------------\n";
    
    error_log($logMessage, 3, $_SERVER['DOCUMENT_ROOT'] . '/logs/offer_letter_errors.log');
} 