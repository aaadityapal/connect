<?php
require_once 'config/db_connect.php';

// Create a header
echo "<h1>Drawing Number Parameter Binding Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    table, th, td { border: 1px solid #ddd; }
    th, td { padding: 10px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>";

try {
    // Check the table structure first
    echo "<h2>Table Structure</h2>";
    $result = $conn->query("DESCRIBE project_substages");
    
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $key => $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    // Create a test substage with a specific drawing number
    $conn->begin_transaction();
    
    echo "<h2>Creating Test Substage</h2>";
    
    // Since we need a valid stage_id, let's find one
    $stage_query = "SELECT id FROM project_stages LIMIT 1";
    $stage_result = $conn->query($stage_query);
    
    if ($stage_result->num_rows === 0) {
        throw new Exception("No stages found in the database. Please create a stage first.");
    }
    
    $stage_row = $stage_result->fetch_assoc();
    $stage_id = $stage_row['id'];
    
    echo "<p class='info'>Using stage ID: " . htmlspecialchars($stage_id) . "</p>";
    
    // Create a unique title for this test
    $test_title = "Binding Test " . date('Y-m-d H:i:s');
    
    // Insert with a proper drawing number
    $drawing_number = "CD_1001";
    
    $insert_sql = "INSERT INTO project_substages (
        stage_id,
        title,
        substage_number,
        start_date,
        end_date,
        drawing_number,
        created_at
    ) VALUES (
        ?,
        ?,
        1,
        NOW(),
        DATE_ADD(NOW(), INTERVAL 10 DAY),
        ?,
        NOW()
    )";
    
    // Let's examine what's going on with binding
    echo "<h3>Insert Binding</h3>";
    echo "<p>SQL: " . htmlspecialchars($insert_sql) . "</p>";
    echo "<p>Parameters:</p>";
    echo "<ul>";
    echo "<li>stage_id: " . htmlspecialchars(var_export($stage_id, true)) . " (" . gettype($stage_id) . ")</li>";
    echo "<li>title: " . htmlspecialchars(var_export($test_title, true)) . " (" . gettype($test_title) . ")</li>";
    echo "<li>drawing_number: " . htmlspecialchars(var_export($drawing_number, true)) . " (" . gettype($drawing_number) . ")</li>";
    echo "</ul>";
    
    // Execute the insert
    $stmt = $conn->prepare($insert_sql);
    
    // Try different binding approaches
    echo "<h3>Testing Different Binding Approaches</h3>";
    
    // Method 1: Standard binding
    echo "<h4>Method 1: Standard binding</h4>";
    try {
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iss", $stage_id, $test_title, $drawing_number);
        $result = $stmt->execute();
        
        if ($result) {
            $substage_id = $conn->insert_id;
            echo "<p class='success'>Insert successful! New substage ID: " . htmlspecialchars($substage_id) . "</p>";
            
            // Check the inserted value
            $check_sql = "SELECT drawing_number FROM project_substages WHERE id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $substage_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_row = $check_result->fetch_assoc();
            
            echo "<p>Inserted drawing_number: " . htmlspecialchars(var_export($check_row['drawing_number'], true)) . 
                 " (" . gettype($check_row['drawing_number']) . ")</p>";
        } else {
            echo "<p class='error'>Insert failed: " . htmlspecialchars($stmt->error) . "</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Now let's try different binding types
    echo "<h2>Testing Different Binding Types for Drawing Number</h2>";
    
    $test_values = [
        ["Type" => "String (CD_1001)", "Value" => "CD_1001", "Binding" => "s"],
        ["Type" => "String ('0')", "Value" => "0", "Binding" => "s"],
        ["Type" => "Integer (0)", "Value" => 0, "Binding" => "i"],
        ["Type" => "Empty string", "Value" => "", "Binding" => "s"],
        ["Type" => "NULL value", "Value" => null, "Binding" => "s"]
    ];
    
    foreach ($test_values as $test) {
        echo "<h3>Testing: " . htmlspecialchars($test["Type"]) . "</h3>";
        
        $test_title = "Binding Test " . $test["Type"] . " " . date('Y-m-d H:i:s');
        $drawing_number = $test["Value"];
        $binding_type = $test["Binding"];
        
        echo "<p>Drawing number value: " . htmlspecialchars(var_export($drawing_number, true)) . 
             " (" . gettype($drawing_number) . ")</p>";
        echo "<p>Using binding type: " . htmlspecialchars($binding_type) . "</p>";
        
        try {
            $insert_sql = "INSERT INTO project_substages (
                stage_id,
                title,
                substage_number,
                start_date,
                end_date,
                drawing_number,
                created_at
            ) VALUES (
                ?,
                ?,
                1,
                NOW(),
                DATE_ADD(NOW(), INTERVAL 10 DAY),
                ?,
                NOW()
            )";
            
            $stmt = $conn->prepare($insert_sql);
            
            // Different binding based on the value type
            if ($drawing_number === null) {
                // Special handling for NULL values
                $null_value = null;
                $stmt->bind_param("iss", $stage_id, $test_title, $null_value);
            } else if ($binding_type === "i") {
                $stmt->bind_param("isi", $stage_id, $test_title, $drawing_number);
            } else {
                $stmt->bind_param("iss", $stage_id, $test_title, $drawing_number);
            }
            
            $result = $stmt->execute();
            
            if ($result) {
                $substage_id = $conn->insert_id;
                echo "<p class='success'>Insert successful! New substage ID: " . htmlspecialchars($substage_id) . "</p>";
                
                // Check the inserted value
                $check_sql = "SELECT drawing_number FROM project_substages WHERE id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $substage_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $check_row = $check_result->fetch_assoc();
                
                echo "<p>Inserted drawing_number: " . htmlspecialchars(var_export($check_row['drawing_number'], true)) . 
                     " (" . gettype($check_row['drawing_number']) . ")</p>";
            } else {
                echo "<p class='error'>Insert failed: " . htmlspecialchars($stmt->error) . "</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
        echo "<hr>";
    }
    
    // Now let's see what actually happens with update operations
    echo "<h2>Testing Update Operations</h2>";
    
    // First, let's create a substage to update
    $test_title = "Update Test " . date('Y-m-d H:i:s');
    $initial_drawing_number = "INITIAL_1001";
    
    $insert_sql = "INSERT INTO project_substages (
        stage_id,
        title,
        substage_number,
        start_date,
        end_date,
        drawing_number,
        created_at
    ) VALUES (
        ?,
        ?,
        1,
        NOW(),
        DATE_ADD(NOW(), INTERVAL 10 DAY),
        ?,
        NOW()
    )";
    
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("iss", $stage_id, $test_title, $initial_drawing_number);
    $stmt->execute();
    $update_test_id = $conn->insert_id;
    
    echo "<p class='info'>Created test substage for updates, ID: " . htmlspecialchars($update_test_id) . "</p>";
    echo "<p>Initial drawing_number: " . htmlspecialchars($initial_drawing_number) . "</p>";
    
    $update_tests = [
        ["Type" => "Update to CD_2001", "Value" => "CD_2001", "Binding" => "s"],
        ["Type" => "Update to '0'", "Value" => "0", "Binding" => "s"],
        ["Type" => "Update to 0 (int)", "Value" => 0, "Binding" => "i"],
        ["Type" => "Update to empty string", "Value" => "", "Binding" => "s"],
        ["Type" => "Update to NULL", "Value" => null, "Binding" => "s"]
    ];
    
    foreach ($update_tests as $test) {
        echo "<h3>Update Test: " . htmlspecialchars($test["Type"]) . "</h3>";
        
        $drawing_number = $test["Value"];
        $binding_type = $test["Binding"];
        
        echo "<p>New drawing number value: " . htmlspecialchars(var_export($drawing_number, true)) . 
             " (" . gettype($drawing_number) . ")</p>";
        echo "<p>Using binding type: " . htmlspecialchars($binding_type) . "</p>";
        
        $update_sql = "UPDATE project_substages SET drawing_number = ? WHERE id = ?";
        
        $stmt = $conn->prepare($update_sql);
        
        // Different binding based on the value type
        if ($drawing_number === null) {
            $null_value = null;
            $stmt->bind_param("si", $null_value, $update_test_id);
        } else if ($binding_type === "i") {
            $stmt->bind_param("ii", $drawing_number, $update_test_id);
        } else {
            $stmt->bind_param("si", $drawing_number, $update_test_id);
        }
        
        $result = $stmt->execute();
        
        if ($result) {
            echo "<p class='success'>Update successful!</p>";
            
            // Check the updated value
            $check_sql = "SELECT drawing_number FROM project_substages WHERE id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $update_test_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_row = $check_result->fetch_assoc();
            
            echo "<p>Updated drawing_number: " . htmlspecialchars(var_export($check_row['drawing_number'], true)) . 
                 " (" . gettype($check_row['drawing_number']) . ")</p>";
        } else {
            echo "<p class='error'>Update failed: " . htmlspecialchars($stmt->error) . "</p>";
        }
        
        echo "<hr>";
    }
    
    // Check if there are any specific data conversion issues
    echo "<h2>Checking Data Type Conversion</h2>";
    
    // Final test with update_projects.php style code
    echo "<h3>Final Test with update_projects.php Style Code</h3>";
    
    // Create a test substage
    $final_test_title = "Final Test " . date('Y-m-d H:i:s');
    $initial_value = "INITIAL_2001";
    
    $insert_sql = "INSERT INTO project_substages (
        stage_id,
        title,
        substage_number,
        start_date,
        end_date,
        drawing_number,
        created_at
    ) VALUES (
        ?,
        ?,
        1,
        NOW(),
        DATE_ADD(NOW(), INTERVAL 10 DAY),
        ?,
        NOW()
    )";
    
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("iss", $stage_id, $final_test_title, $initial_value);
    $stmt->execute();
    $final_test_id = $conn->insert_id;
    
    echo "<p class='info'>Created final test substage, ID: " . htmlspecialchars($final_test_id) . "</p>";
    
    // Simulate the exact code from update_projects.php
    $substage = [
        'id' => $final_test_id,
        'title' => 'Updated Title',
        'assignTo' => '0',
        'startDate' => date('Y-m-d H:i:s'),
        'dueDate' => date('Y-m-d H:i:s', strtotime('+10 days')),
        'drawingNumber' => 'CD_1001' // The drawing number we want to save
    ];
    
    echo "<p>Raw drawing number: " . var_export($substage['drawingNumber'], true) . 
          " Type: " . gettype($substage['drawingNumber']) . 
          " Is empty: " . (empty($substage['drawingNumber']) ? 'yes' : 'no') . 
          " Is '0': " . ($substage['drawingNumber'] === '0' ? 'yes' : 'no') .
          " Is 0: " . ($substage['drawingNumber'] === 0 ? 'yes' : 'no') . "</p>";
    
    // Process drawing number as in update_projects.php
    $drawingNumber = (!empty($substage['drawingNumber']) && 
                     $substage['drawingNumber'] !== '0' && 
                     $substage['drawingNumber'] !== 0)
        ? $substage['drawingNumber'] 
        : null;
    
    echo "<p>Processed drawing number: " . var_export($drawingNumber, true) . "</p>";
    
    // Update the substage
    $update_substage_sql = "UPDATE project_substages SET 
        title = ?,
        assigned_to = NULL,
        start_date = ?,
        end_date = ?,
        drawing_number = ?,
        updated_at = NOW()
        WHERE id = ?";
    
    $stmt = $conn->prepare($update_substage_sql);
    $stmt->bind_param("ssssi",
        $substage['title'],
        $substage['startDate'],
        $substage['dueDate'],
        $drawingNumber,
        $substage['id']
    );
    $result = $stmt->execute();
    
    if ($result) {
        echo "<p class='success'>Final update successful!</p>";
    } else {
        echo "<p class='error'>Final update failed: " . htmlspecialchars($stmt->error) . "</p>";
    }
    
    // Check the updated value
    $check_sql = "SELECT * FROM project_substages WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $final_test_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    
    echo "<h4>Final Result:</h4>";
    echo "<pre>" . htmlspecialchars(print_r($check_row, true)) . "</pre>";
    
    // Commit the transaction
    $conn->commit();
    echo "<p class='success'>Test completed successfully!</p>";
    
} catch (Exception $e) {
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?> 