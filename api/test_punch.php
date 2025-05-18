<?php
// Start session
session_start();

// Simulate logged in user
$_SESSION['user_id'] = 1; // Set to a valid user ID

// Make an internal test request to process_punch.php
$_POST['punch_type'] = 'in'; // or 'out'
$_POST['latitude'] = 12.9716;
$_POST['longitude'] = 77.5946;
$_POST['accuracy'] = 10;
$_POST['address'] = 'Test address';
$_POST['work_report'] = 'Test work report';

// Include the process_punch.php file directly to see any errors
echo "Testing process_punch.php...<br>";
try {
    // Capture output
    ob_start();
    require_once 'process_punch.php';
    $result = ob_get_clean();
    
    // Display result
    echo "Result: " . $result;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 