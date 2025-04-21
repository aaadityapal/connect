<?php
// Start the session
session_start();

// Set content type to HTML
header('Content-Type: text/html');

// Output current session data
echo "<h2>Current Session Data</h2>";
echo "<pre>";
var_dump($_SESSION);
echo "</pre>";

// Check if user_id is set
echo "<h2>User ID Status</h2>";
if (isset($_SESSION['user_id'])) {
    echo "user_id is set: " . $_SESSION['user_id'] . " (type: " . gettype($_SESSION['user_id']) . ")<br>";
} else {
    echo "user_id is NOT set<br>";
}

// Check if user_role is set
if (isset($_SESSION['user_role'])) {
    echo "user_role is set: " . $_SESSION['user_role'] . " (type: " . gettype($_SESSION['user_role']) . ")<br>";
} else {
    echo "user_role is NOT set<br>";
}

// Database connection to fetch a message and check its user_id
echo "<h2>Message User ID from Database</h2>";
try {
    require_once 'config/db_connect.php';
    
    // Get first message from stage_chat_messages
    $sql = "SELECT id, user_id FROM stage_chat_messages LIMIT 1";
    $stmt = $pdo->query($sql);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($message) {
        echo "Message ID: " . $message['id'] . "<br>";
        echo "Message user_id: " . $message['user_id'] . " (type: " . gettype($message['user_id']) . ")<br>";
        
        // Test comparison
        if (isset($_SESSION['user_id'])) {
            $session_id = $_SESSION['user_id'];
            $message_id = $message['user_id'];
            
            echo "<h3>Comparison Tests</h3>";
            echo "Session ID: $session_id (type: " . gettype($session_id) . ")<br>";
            echo "Message user_id: $message_id (type: " . gettype($message_id) . ")<br>";
            
            // Direct comparison
            echo "Direct comparison (== ): " . ($session_id == $message_id ? 'true' : 'false') . "<br>";
            
            // Strict comparison
            echo "Strict comparison (===): " . ($session_id === $message_id ? 'true' : 'false') . "<br>";
            
            // String comparison
            echo "String comparison (string vs string): " . (strval($session_id) === strval($message_id) ? 'true' : 'false') . "<br>";
            
            // Integer comparison
            echo "Integer comparison (int vs int): " . (intval($session_id) === intval($message_id) ? 'true' : 'false') . "<br>";
        }
    } else {
        echo "No messages found in database";
    }
} catch (Exception $e) {
    echo "Error connecting to database: " . $e->getMessage();
}

// Function to test with static values
function testComparisonWithStaticValues() {
    echo "<h2>Static Comparison Tests</h2>";
    
    // Test with string "21" vs integer 21
    $string_id = "21"; 
    $int_id = 21;
    
    echo "String '21' vs Integer 21:<br>";
    echo "Direct comparison (==): " . ($string_id == $int_id ? 'true' : 'false') . "<br>";
    echo "Strict comparison (===): " . ($string_id === $int_id ? 'true' : 'false') . "<br>";
    echo "String vs String: " . (strval($string_id) === strval($int_id) ? 'true' : 'false') . "<br>";
    echo "Integer vs Integer: " . (intval($string_id) === intval($int_id) ? 'true' : 'false') . "<br>";
    
    // Test with null vs string "21"
    $null_id = null;
    
    echo "<br>Null vs String '21':<br>";
    echo "Direct comparison (==): " . ($null_id == $string_id ? 'true' : 'false') . "<br>";
    echo "Strict comparison (===): " . ($null_id === $string_id ? 'true' : 'false') . "<br>";
    
    // Test with string conversion
    echo "<br>String Conversion Tests:<br>";
    echo "String(null): '" . strval($null_id) . "'<br>";
    echo "String(21): '" . strval($int_id) . "'<br>";
}

// Run static tests
testComparisonWithStaticValues();
?> 