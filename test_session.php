<?php
// Start session
session_start();

// Check session data
echo "<h1>Session Data</h1>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Add mock session data for testing if none exists
if (!isset($_SESSION['user_id'])) {
    echo "<h2>Setting mock session data for testing</h2>";
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'TestUser';
    $_SESSION['role'] = 'Site Supervisor';
    
    echo "<p>Session data set. Please refresh to see the changes.</p>";
}
?> 