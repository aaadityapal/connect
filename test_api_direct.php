<?php
// Test API without session authentication for debugging
$_SESSION['user_id'] = 1; // Mock user authentication

// Include the actual API
include 'api/get_payment_entry_details.php';
?>