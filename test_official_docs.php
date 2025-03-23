<?php
session_start();
require_once 'config.php';

// Ensure we have a user ID for testing
if (!isset($_SESSION['user_id'])) {
    echo "You must be logged in to run this test.";
    exit;
}

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Official Documents</title>
</head>
<body>
    <h1>Testing Official Documents for User ID: <?php echo $user_id; ?></h1>
    
    <h2>Database Connection Test</h2>
    <div id="db-test">Testing...</div>
    
    <h2>Official Documents Query Test</h2>
    <div id="query-test">Testing...</div>
    
    <script>
        // Test database connection
        fetch('test_db_connection.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('db-test').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('db-test').innerHTML = 'Error: ' + error.message;
            });
            
        // Test official documents query
        fetch('get_employee_official_documents.php')
            .then(response => response.json())
            .then(data => {
                const resultElement = document.getElementById('query-test');
                resultElement.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
            })
            .catch(error => {
                document.getElementById('query-test').innerHTML = 'Error: ' + error.message;
            });
    </script>
</body>
</html> 