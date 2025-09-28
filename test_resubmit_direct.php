<?php
// Direct test of resubmit functionality to debug JSON issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Resubmit Functionality</h2>";

session_start();

// Simulate being logged in (use your actual user ID)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 35; // Use your actual user ID from the earlier test
    echo "<p>✅ Simulated login with user ID: 35</p>";
}

// Check if we can connect to database
echo "<h3>1. Database Connection Test</h3>";
try {
    include_once('includes/db_connect.php');
    if ($conn->connect_error) {
        echo "<p>❌ Database connection failed: " . $conn->connect_error . "</p>";
        exit;
    } else {
        echo "<p>✅ Database connected successfully</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Database include failed: " . $e->getMessage() . "</p>";
    exit;
}

// Check if rejected expense exists
echo "<h3>2. Rejected Expense Check</h3>";
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, status, purpose FROM travel_expenses WHERE user_id = ? AND status = 'rejected' LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $rejected_expense = $result->fetch_assoc();
    echo "<p>✅ Found rejected expense ID: {$rejected_expense['id']}</p>";
    echo "<p>Purpose: {$rejected_expense['purpose']}</p>";
    $expense_id = $rejected_expense['id'];
} else {
    echo "<p>❌ No rejected expenses found</p>";
    echo "<p>Creating a test rejected expense...</p>";
    
    // Create a test rejected expense
    $test_stmt = $conn->prepare("INSERT INTO travel_expenses (user_id, purpose, mode_of_transport, from_location, to_location, travel_date, distance, amount, status, notes) VALUES (?, 'Test Expense', 'Car', 'Home', 'Office', CURDATE(), 10, 100, 'rejected', 'Test expense for resubmission')");
    $test_stmt->bind_param("i", $user_id);
    if ($test_stmt->execute()) {
        $expense_id = $conn->insert_id;
        echo "<p>✅ Created test rejected expense ID: {$expense_id}</p>";
    } else {
        echo "<p>❌ Failed to create test expense</p>";
        exit;
    }
    $test_stmt->close();
}
$stmt->close();

// Test the core resubmission logic manually
echo "<h3>3. Manual Resubmission Test</h3>";
try {
    // Get the rejected expense
    $stmt = $conn->prepare("SELECT * FROM travel_expenses WHERE id = ? AND user_id = ? AND status = 'rejected'");
    $stmt->bind_param("ii", $expense_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        throw new Exception("Rejected expense not found");
    }
    
    $expense = $result->fetch_assoc();
    $stmt->close();
    
    echo "<p>✅ Retrieved expense data</p>";
    
    // Check resubmission count
    $current_count = isset($expense['resubmission_count']) ? intval($expense['resubmission_count']) : 0;
    $max_allowed = isset($expense['max_resubmissions']) ? intval($expense['max_resubmissions']) : 3;
    
    echo "<p>Current resubmission count: {$current_count}</p>";
    echo "<p>Max allowed: {$max_allowed}</p>";
    
    if ($current_count >= $max_allowed) {
        echo "<p>❌ Maximum resubmissions reached</p>";
    } else {
        echo "<p>✅ Resubmission allowed</p>";
        
        // Test the INSERT statement
        echo "<h4>Testing INSERT statement...</h4>";
        $conn->begin_transaction();
        
        try {
            $insert_stmt = $conn->prepare("
                INSERT INTO travel_expenses (
                    user_id, purpose, mode_of_transport, from_location, 
                    to_location, travel_date, distance, amount, status, notes, 
                    bill_file_path, manager_status, accountant_status, hr_status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, 'pending', 'pending', 'pending', NOW())
            ");
            
            if (!$insert_stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $notes = isset($expense['notes']) ? $expense['notes'] : '';
            $bill_path = isset($expense['bill_file_path']) ? $expense['bill_file_path'] : '';
            
            $bind_result = $insert_stmt->bind_param(
                "isssssddss",
                $expense['user_id'],
                $expense['purpose'],
                $expense['mode_of_transport'],
                $expense['from_location'],
                $expense['to_location'],
                $expense['travel_date'],
                $expense['distance'],
                $expense['amount'],
                $notes,
                $bill_path
            );
            
            if (!$bind_result) {
                throw new Exception("Bind failed: " . $insert_stmt->error);
            }
            
            if (!$insert_stmt->execute()) {
                throw new Exception("Execute failed: " . $insert_stmt->error);
            }
            
            $new_expense_id = $conn->insert_id;
            $insert_stmt->close();
            
            // Rollback for testing
            $conn->rollback();
            
            echo "<p>✅ INSERT test successful (rolled back)</p>";
            echo "<p>Would have created expense ID: {$new_expense_id}</p>";
            
            // Test JSON response
            echo "<h4>Testing JSON Response...</h4>";
            $response = array(
                'success' => true,
                'message' => 'Test resubmission successful',
                'new_expense_id' => $new_expense_id,
                'original_expense_id' => $expense_id
            );
            
            $json_output = json_encode($response);
            if ($json_output === false) {
                echo "<p>❌ JSON encoding failed: " . json_last_error_msg() . "</p>";
            } else {
                echo "<p>✅ JSON encoding successful</p>";
                echo "<pre>" . htmlspecialchars($json_output) . "</pre>";
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            echo "<p>❌ INSERT test failed: " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Test failed: " . $e->getMessage() . "</p>";
}

$conn->close();
?>
?>

<!DOCTYPE html>
<html>
<head>
    <title>Resubmit Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        h3 { color: #333; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
    </style>
</head>
</html>