<?php
// This is a test script to check if process_expense_action.php is working correctly

// Start session
session_start();

// Simulate being logged in as HR
$_SESSION['user_id'] = 1; // Replace with a valid user ID
$_SESSION['role'] = 'HR'; // Set role to HR

// Display current session data
echo "<h3>Current Session:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Create test form
echo "<h3>Test Form</h3>";
echo "<form action='process_expense_action.php' method='post'>";
echo "Expense ID: <input type='number' name='expense_id' value='1'><br>";
echo "Action: <select name='action'><option value='approve'>Approve</option><option value='reject'>Reject</option></select><br>";
echo "Notes: <textarea name='notes'>Test approval/rejection</textarea><br>";
echo "<input type='submit' value='Submit'>";
echo "</form>";

// Show instructions
echo "<h3>Instructions:</h3>";
echo "<p>1. Make sure you're logged in as an HR user</p>";
echo "<p>2. Enter a valid expense ID</p>";
echo "<p>3. Choose an action (approve/reject)</p>";
echo "<p>4. Submit the form</p>";
echo "<p>5. Check the response</p>";

// Show current database connection info
echo "<h3>Database Connection:</h3>";
echo "<p>Check if includes/db_connect.php exists: " . (file_exists('includes/db_connect.php') ? 'Yes' : 'No') . "</p>";
?> 