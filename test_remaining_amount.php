<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once __DIR__ . '/config/db_connect.php';

echo "<h1>Testing Remaining Amount Functionality</h1>";

// Test 1: Insert a record with a remaining amount
try {
    $stmt = $pdo->prepare("
        INSERT INTO project_payouts (
            project_name, 
            project_type, 
            client_name, 
            project_date, 
            amount, 
            payment_mode, 
            project_stage,
            remaining_amount
        ) VALUES (
            :project_name,
            :project_type,
            :client_name,
            :project_date,
            :amount,
            :payment_mode,
            :project_stage,
            :remaining_amount
        )
    ");
    
    $result = $stmt->execute([
        ':project_name' => 'Test Remaining Amount',
        ':project_type' => 'Architecture',
        ':client_name' => 'Test Client',
        ':project_date' => date('Y-m-d'),
        ':amount' => 5000.00,
        ':payment_mode' => 'Cash',
        ':project_stage' => 'Stage 1',
        ':remaining_amount' => 1500.00
    ]);
    
    if ($result) {
        $insertId = $pdo->lastInsertId();
        echo "<p style='color:green'>✓ Test record inserted successfully with ID: $insertId</p>";
    } else {
        echo "<p style='color:red'>✗ Failed to insert test record</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Error inserting test record: " . $e->getMessage() . "</p>";
}

// Test 2: Retrieve the record and check if remaining_amount is saved correctly
try {
    $stmt = $pdo->prepare("
        SELECT * FROM project_payouts 
        WHERE project_name = 'Test Remaining Amount'
        ORDER BY id DESC LIMIT 1
    ");
    
    $stmt->execute();
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($project) {
        echo "<h2>Retrieved Project:</h2>";
        echo "<pre>";
        print_r($project);
        echo "</pre>";
        
        if (isset($project['remaining_amount']) && $project['remaining_amount'] == 1500.00) {
            echo "<p style='color:green'>✓ Remaining amount saved and retrieved correctly!</p>";
        } else {
            echo "<p style='color:red'>✗ Remaining amount not saved correctly. Value: " . 
                (isset($project['remaining_amount']) ? $project['remaining_amount'] : 'NULL') . "</p>";
        }
    } else {
        echo "<p style='color:red'>✗ Could not retrieve the test record</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Error retrieving test record: " . $e->getMessage() . "</p>";
}

// Test 3: Check if the remaining_amount column exists
try {
    $stmt = $pdo->query("DESCRIBE project_payouts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Table Structure:</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    
    $remainingAmountExists = false;
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
        
        if ($column['Field'] === 'remaining_amount') {
            $remainingAmountExists = true;
        }
    }
    
    echo "</table>";
    
    if ($remainingAmountExists) {
        echo "<p style='color:green'>✓ remaining_amount column exists in the table</p>";
    } else {
        echo "<p style='color:red'>✗ remaining_amount column does NOT exist in the table</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Error checking table structure: " . $e->getMessage() . "</p>";
}

// Test 4: Check if the form is correctly sending the remaining_amount value
echo "<h2>Form Submission Test:</h2>";
echo "<p>Use this form to test if the remaining amount is being sent correctly:</p>";

echo "<form method='post' action='test_remaining_amount.php'>";
echo "<input type='hidden' name='action' value='test_form'>";
echo "<label>Project Name: <input type='text' name='project_name' value='Form Test Project'></label><br>";
echo "<label>Amount: <input type='number' name='amount' value='1000' step='0.01'></label><br>";
echo "<label>Remaining Amount: <input type='number' name='remaining_amount' value='200' step='0.01'></label><br>";
echo "<button type='submit'>Test Form Submission</button>";
echo "</form>";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_form') {
    echo "<h3>Form Data Received:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    if (isset($_POST['remaining_amount'])) {
        echo "<p style='color:green'>✓ remaining_amount value received: {$_POST['remaining_amount']}</p>";
    } else {
        echo "<p style='color:red'>✗ remaining_amount value NOT received</p>";
    }
}
?> 