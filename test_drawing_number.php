<?php
require_once 'config/db_connect.php';
session_start();

// Set a user ID for testing if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Use a valid admin user ID
}

// Create a header
echo "<h1>Drawing Number Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    table, th, td { border: 1px solid #ddd; }
    th, td { padding: 10px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>";

// Function to log test steps
function logStep($message, $type = 'info') {
    echo "<p class='$type'>" . htmlspecialchars($message) . "</p>";
}

// Function to log detailed information
function logDetails($title, $data) {
    echo "<h3>" . htmlspecialchars($title) . "</h3>";
    echo "<pre>" . htmlspecialchars(print_r($data, true)) . "</pre>";
}

// Test the drawing number handling
try {
    logStep("Starting drawing number test", "success");
    
    // 1. Create a test project if needed
    $conn->begin_transaction();
    
    // Check if our test project exists
    $check_sql = "SELECT id FROM projects WHERE title = 'Drawing Number Test Project'";
    $result = $conn->query($check_sql);
    $project_id = null;
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $project_id = $row['id'];
        logStep("Using existing test project ID: " . $project_id);
    } else {
        // Create a test project
        $insert_project_sql = "INSERT INTO projects (
            title, 
            description, 
            project_type, 
            category_id,
            start_date, 
            end_date, 
            assigned_to,
            created_by, 
            created_at
        ) VALUES (
            'Drawing Number Test Project',
            'Test project for drawing number issue',
            'architecture',
            1,
            NOW(),
            DATE_ADD(NOW(), INTERVAL 30 DAY),
            NULL,
            ?,
            NOW()
        )";
        
        $stmt = $conn->prepare($insert_project_sql);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $project_id = $conn->insert_id;
        logStep("Created new test project with ID: " . $project_id);
    }
    
    // 2. Create a test stage
    $check_stage_sql = "SELECT id FROM project_stages WHERE project_id = ? AND stage_number = 1 AND deleted_at IS NULL";
    $stmt = $conn->prepare($check_stage_sql);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stage_id = null;
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stage_id = $row['id'];
        logStep("Using existing test stage ID: " . $stage_id);
    } else {
        // Create a test stage
        $insert_stage_sql = "INSERT INTO project_stages (
            project_id,
            stage_number,
            assigned_to,
            start_date,
            end_date,
            created_by,
            created_at
        ) VALUES (
            ?,
            1,
            NULL,
            NOW(),
            DATE_ADD(NOW(), INTERVAL 15 DAY),
            ?,
            NOW()
        )";
        
        $stmt = $conn->prepare($insert_stage_sql);
        $stmt->bind_param("ii", $project_id, $_SESSION['user_id']);
        $stmt->execute();
        $stage_id = $conn->insert_id;
        logStep("Created new test stage with ID: " . $stage_id);
    }
    
    // 3. Create test substages with different drawing number scenarios
    $drawing_numbers = [
        'Test 1' => 'CD_1001',
        'Test 2' => '',
        'Test 3' => '0',
        'Test 4' => 0,
        'Test 5' => null,
        'Test 6' => 'INT-CD_1001'
    ];
    
    $substage_ids = [];
    $substage_number = 1;
    
    foreach ($drawing_numbers as $title => $drawing_number) {
        $original_value = $drawing_number;
        
        // Log the raw value we're testing
        logStep("Testing drawing number: '" . var_export($drawing_number, true) . "' (type: " . gettype($drawing_number) . ")", "info");
        
        // Process drawing number similar to update_projects.php
        $processed_drawing_number = (!empty($drawing_number) && 
                                   $drawing_number !== '0' && 
                                   $drawing_number !== 0)
            ? $drawing_number 
            : null;
        
        logStep("Processed drawing number: " . var_export($processed_drawing_number, true), "info");
        
        // Create the substage
        $insert_substage_sql = "INSERT INTO project_substages (
            stage_id,
            title,
            substage_number,
            assigned_to,
            start_date,
            end_date,
            drawing_number,
            created_by,
            created_at
        ) VALUES (
            ?,
            ?,
            ?,
            NULL,
            NOW(),
            DATE_ADD(NOW(), INTERVAL 10 DAY),
            ?,
            ?,
            NOW()
        )";
        
        $stmt = $conn->prepare($insert_substage_sql);
        $stmt->bind_param("isisi", $stage_id, $title, $substage_number, $processed_drawing_number, $_SESSION['user_id']);
        $result = $stmt->execute();
        
        if ($result) {
            $substage_id = $conn->insert_id;
            $substage_ids[$title] = $substage_id;
            logStep("Created substage '$title' with ID $substage_id and drawing number: " . var_export($processed_drawing_number, true), "success");
        } else {
            logStep("Failed to create substage: " . $conn->error, "error");
        }
        
        $substage_number++;
    }
    
    // 4. Verify the substages were created with correct drawing numbers
    echo "<h2>Created Substages</h2>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Title</th><th>Original Value</th><th>Type</th><th>Stored Value</th><th>DB Type</th></tr>";
    
    foreach ($substage_ids as $title => $id) {
        $check_sql = "SELECT id, title, drawing_number FROM project_substages WHERE id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $original = $drawing_numbers[$title];
        $stored = $row['drawing_number'];
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . htmlspecialchars(var_export($original, true)) . "</td>";
        echo "<td>" . htmlspecialchars(gettype($original)) . "</td>";
        echo "<td>" . htmlspecialchars(var_export($stored, true)) . "</td>";
        echo "<td>" . htmlspecialchars(gettype($stored)) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 5. Test the update process
    echo "<h2>Testing Update Process</h2>";
    
    // Now let's update the first substage with various values and see what happens
    $update_tests = [
        'Update with string value' => 'SD_2001',
        'Update with empty string' => '',
        'Update with string zero' => '0',
        'Update with integer zero' => 0,
        'Update with null' => null,
        'Update with INT prefix' => 'INT-LP_2001'
    ];
    
    $test_substage_id = array_values($substage_ids)[0]; // Get the first substage ID
    
    foreach ($update_tests as $test_name => $test_value) {
        logStep("$test_name: " . var_export($test_value, true) . " (type: " . gettype($test_value) . ")", "info");
        
        // Process the drawing number as in update_projects.php
        $processed_drawing_number = (!empty($test_value) && 
                                   $test_value !== '0' && 
                                   $test_value !== 0)
            ? $test_value 
            : null;
        
        logStep("Processed drawing number: " . var_export($processed_drawing_number, true), "info");
        
        // Update the substage
        $update_sql = "UPDATE project_substages SET drawing_number = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        
        // Bind parameters based on whether drawing_number is null
        if ($processed_drawing_number === null) {
            $stmt->bind_param("si", $processed_drawing_number, $test_substage_id);
        } else {
            $stmt->bind_param("si", $processed_drawing_number, $test_substage_id);
        }
        
        $result = $stmt->execute();
        
        if ($result) {
            logStep("Update successful", "success");
        } else {
            logStep("Update failed: " . $conn->error, "error");
        }
        
        // Verify the update
        $check_sql = "SELECT drawing_number FROM project_substages WHERE id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("i", $test_substage_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        logStep("Value in database after update: " . var_export($row['drawing_number'], true) . " (type: " . gettype($row['drawing_number']) . ")", "info");
        echo "<hr>";
    }
    
    // 6. Test exact scenario from the client form submission
    echo "<h2>Testing Client Form Submission Scenario</h2>";
    
    // Create a JSON object similar to what would be submitted from the form
    $substage_data = [
        'id' => $test_substage_id,
        'title' => 'Client Test Substage',
        'assignTo' => '0',
        'startDate' => date('Y-m-d H:i:s'),
        'dueDate' => date('Y-m-d H:i:s', strtotime('+10 days')),
        'drawingNumber' => 'CD_1001'
    ];
    
    logDetails("Substage data from client", $substage_data);
    
    // Process the substage data exactly as in update_projects.php
    $substageAssignedTo = (!empty($substage_data['assignTo']) && $substage_data['assignTo'] !== '0') ? $substage_data['assignTo'] : null;
    
    // Debug the drawing number value
    $rawDrawingNumber = isset($substage_data['drawingNumber']) ? $substage_data['drawingNumber'] : null;
    logStep("Raw drawing number: " . var_export($rawDrawingNumber, true) . 
            " Type: " . gettype($rawDrawingNumber) . 
            " Is empty: " . (empty($rawDrawingNumber) ? 'yes' : 'no') . 
            " Is '0': " . ($rawDrawingNumber === '0' ? 'yes' : 'no') .
            " Is 0: " . ($rawDrawingNumber === 0 ? 'yes' : 'no'));
    
    // Handle drawing number NULL value - only use values that are not empty strings, '0', 0, or null
    $drawingNumber = (!empty($substage_data['drawingNumber']) && 
                     $substage_data['drawingNumber'] !== '0' && 
                     $substage_data['drawingNumber'] !== 0)
        ? $substage_data['drawingNumber'] 
        : null;
    
    logStep("Processed drawing number: " . var_export($drawingNumber, true));
    
    // Update the substage
    $update_substage_sql = "UPDATE project_substages SET 
        title = ?,
        assigned_to = ?,
        start_date = ?,
        end_date = ?,
        drawing_number = ?,
        updated_at = NOW(),
        updated_by = ?
        WHERE id = ?";
    
    $stmt = $conn->prepare($update_substage_sql);
    $stmt->bind_param("sissisi",
        $substage_data['title'],
        $substageAssignedTo,
        $substage_data['startDate'],
        $substage_data['dueDate'],
        $drawingNumber,
        $_SESSION['user_id'],
        $test_substage_id
    );
    $result = $stmt->execute();
    
    if ($result) {
        logStep("Update successful using exact update_projects.php code", "success");
    } else {
        logStep("Update failed: " . $conn->error, "error");
    }
    
    // Verify the update
    $check_sql = "SELECT * FROM project_substages WHERE id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $test_substage_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    logDetails("Database row after update", $row);
    
    // 7. Test with parameter display
    echo "<h2>Testing Parameter Binding</h2>";
    
    // Let's try to see what parameters are actually being bound
    $drawingNumber = 'CD_1001';
    logStep("Test binding with drawing_number = " . var_export($drawingNumber, true), "info");
    
    // Create a custom function to log parameter binding
    function executeWithLogging($conn, $sql, $params) {
        echo "<h3>SQL Statement:</h3>";
        echo "<pre>" . htmlspecialchars($sql) . "</pre>";
        
        echo "<h3>Parameters:</h3>";
        echo "<table>";
        echo "<tr><th>Position</th><th>Value</th><th>Type</th></tr>";
        
        foreach ($params as $index => $value) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($index) . "</td>";
            echo "<td>" . htmlspecialchars(var_export($value, true)) . "</td>";
            echo "<td>" . htmlspecialchars(gettype($value)) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Execute the statement
        $stmt = $conn->prepare($sql);
        
        // Create parameter type string
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_double($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 's'; // Default to string for null and other types
            }
        }
        
        // Bind parameters
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        // Execute
        $result = $stmt->execute();
        
        if ($result) {
            echo "<p class='success'>Statement executed successfully</p>";
        } else {
            echo "<p class='error'>Statement execution failed: " . htmlspecialchars($conn->error) . "</p>";
        }
        
        return $result;
    }
    
    // Test update with logging
    $update_sql = "UPDATE project_substages SET drawing_number = ? WHERE id = ?";
    $params = [$drawingNumber, $test_substage_id];
    executeWithLogging($conn, $update_sql, $params);
    
    // Check the result
    $check_sql = "SELECT drawing_number FROM project_substages WHERE id = ?";
    $params = [$test_substage_id];
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $test_substage_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    logStep("Final value in database: " . var_export($row['drawing_number'], true) . " (type: " . gettype($row['drawing_number']) . ")", "info");
    
    // 8. Show table structure
    echo "<h2>Database Table Structure</h2>";
    $table_info_sql = "DESCRIBE project_substages";
    $result = $conn->query($table_info_sql);
    
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
    
    // Commit the transaction
    $conn->commit();
    logStep("Test completed successfully. All changes committed.", "success");
    
} catch (Exception $e) {
    $conn->rollback();
    logStep("Error: " . $e->getMessage(), "error");
}
?> 