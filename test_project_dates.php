<?php
session_start();
require_once 'config/db_connect.php';

echo "<h2>Project Date Testing</h2>";

// Simulate project data
$testData = [
    'startDate' => '2024-03-15 10:00:00',
    'dueDate' => '2024-03-20 10:00:00'
];

echo "<h3>Test 1: Direct Insert with STR_TO_DATE</h3>";
try {
    $conn->begin_transaction();
    
    // Test Query 1: Using STR_TO_DATE
    $query1 = "INSERT INTO projects (
        title, description, project_type, category_id,
        start_date, end_date, created_by, assigned_to, status, created_at
    ) VALUES (
        'Test Project 1', 
        'Test Description', 
        '5', 
        1, 
        STR_TO_DATE(?, '%Y-%m-%d %H:%i:%s'), 
        STR_TO_DATE(?, '%Y-%m-%d %H:%i:%s'), 
        1, 
        1, 
        'not_started', 
        NOW()
    )";
    
    $stmt = $conn->prepare($query1);
    $stmt->bind_param("ss", $testData['startDate'], $testData['dueDate']);
    
    if ($stmt->execute()) {
        $id1 = $conn->insert_id;
        echo "Test 1 Success - ID: $id1<br>";
        
        // Verify the dates
        $check = $conn->query("SELECT start_date, end_date FROM projects WHERE id = $id1");
        $result = $check->fetch_assoc();
        echo "Stored dates:<br>";
        echo "Start Date: " . $result['start_date'] . "<br>";
        echo "End Date: " . $result['end_date'] . "<br><br>";
    }

    echo "<h3>Test 2: Insert with Formatted Dates</h3>";
    
    // Format dates
    $formattedStart = date('Y-m-d H:i:s', strtotime($testData['startDate']));
    $formattedDue = date('Y-m-d H:i:s', strtotime($testData['dueDate']));
    
    $query2 = "INSERT INTO projects (
        title, description, project_type, category_id,
        start_date, end_date, created_by, assigned_to, status, created_at
    ) VALUES (
        'Test Project 2', 
        'Test Description', 
        '5', 
        1, 
        ?, 
        ?, 
        1, 
        1, 
        'not_started', 
        NOW()
    )";
    
    $stmt = $conn->prepare($query2);
    $stmt->bind_param("ss", $formattedStart, $formattedDue);
    
    if ($stmt->execute()) {
        $id2 = $conn->insert_id;
        echo "Test 2 Success - ID: $id2<br>";
        
        // Verify the dates
        $check = $conn->query("SELECT start_date, end_date FROM projects WHERE id = $id2");
        $result = $check->fetch_assoc();
        echo "Stored dates:<br>";
        echo "Start Date: " . $result['start_date'] . "<br>";
        echo "End Date: " . $result['end_date'] . "<br><br>";
    }

    echo "<h3>Database Column Information</h3>";
    $columnInfo = $conn->query("SHOW COLUMNS FROM projects WHERE Field IN ('start_date', 'end_date')");
    echo "<pre>";
    while ($col = $columnInfo->fetch_assoc()) {
        print_r($col);
    }
    echo "</pre>";

    // Rollback the test data
    $conn->rollback();
    echo "<br>Test data rolled back successfully.";

} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "<br>";
    echo "SQL State: " . $e->getCode() . "<br>";
}

$conn->close();
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
    h2, h3 { color: #333; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
</style> 