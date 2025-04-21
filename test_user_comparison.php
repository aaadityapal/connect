<?php
// Test comparison with different user ID formats

// Direct output for command line use
echo "==== User ID Comparison Test ====\n";

// Static test values
$test_values = [
    ['null', null],
    ['empty_string', ''],
    ['zero_string', '0'],
    ['zero_int', 0],
    ['string_21', '21'],
    ['int_21', 21],
    ['string_with_spaces', ' 21 ']
];

// Compare each value with every other value
echo "\nComparison Matrix:\n";
echo "Format: value1 OP value2 = result\n";
echo "============================\n";

foreach ($test_values as [$label1, $value1]) {
    foreach ($test_values as [$label2, $value2]) {
        // Test == operator
        $equal = $value1 == $value2 ? 'true' : 'false';
        echo "$label1 ($value1) == $label2 ($value2) = $equal\n";
        
        // Test === operator
        $identical = $value1 === $value2 ? 'true' : 'false';
        echo "$label1 ($value1) === $label2 ($value2) = $identical\n";
        
        // Test with parseInt equivalent
        $int1 = is_null($value1) ? 0 : intval($value1);
        $int2 = is_null($value2) ? 0 : intval($value2);
        $int_equal = $int1 === $int2 ? 'true' : 'false';
        echo "parseInt($label1) [$int1] === parseInt($label2) [$int2] = $int_equal\n";
        
        echo "----\n";
    }
    echo "\n";
}

// Test with real message user_ids from the database
try {
    require_once 'config/db_connect.php';
    
    echo "\n==== Database Message IDs ====\n";
    
    // Get a few message IDs from the database
    $sql = "SELECT id, user_id FROM stage_chat_messages ORDER BY id ASC LIMIT 5";
    $stmt = $pdo->query($sql);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($messages) > 0) {
        echo "\nFound " . count($messages) . " messages in database.\n";
        
        foreach ($messages as $msg) {
            echo "\nMessage ID: " . $msg['id'] . "\n";
            echo "User ID: " . $msg['user_id'] . " (type: " . gettype($msg['user_id']) . ")\n";
            
            // Compare with test values
            foreach ($test_values as [$label, $test_value]) {
                // Regular comparison
                $equal = $msg['user_id'] == $test_value ? 'true' : 'false';
                echo "$label ($test_value) == db_user_id (" . $msg['user_id'] . ") = $equal\n";
                
                // Integer comparison
                $int_msg_id = intval($msg['user_id']);
                $int_test = is_null($test_value) ? 0 : intval($test_value);
                $int_equal = $int_msg_id === $int_test ? 'true' : 'false';
                echo "parseInt($label) [$int_test] === parseInt(db_user_id) [$int_msg_id] = $int_equal\n";
            }
        }
    } else {
        echo "No messages found in database.\n";
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n==== Session User ID Test ====\n";
session_start();
echo "Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set') . "\n";
echo "Session user_id type: " . (isset($_SESSION['user_id']) ? gettype($_SESSION['user_id']) : 'N/A') . "\n";

// Recommended solution
echo "\n==== Recommended Fix ====\n";
echo "Based on the tests, the best approach is to use parseInt in JavaScript:\n";
echo "if (parseInt(currentUserId) === parseInt(messageUserId) || isAdmin) { ... }\n";
echo "This ensures numeric comparison regardless of type (string/int/null).\n";
?> 