<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page with a success message
session_start(); // Start new session to store message
$_SESSION['success'] = "You have been successfully logged out.";
header('Location: login.php');
exit();
?>
