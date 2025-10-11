<?php
session_start();
require_once 'config/db_connect.php';

echo "<h2>Date Testing for Project Creation</h2>";

// 1. Test the date coming from frontend
$testProject = [
    'projectTitle' => 'Test Project',
    'projectDescription' => 'Testing dates',
    'projectType' => '5',
    'category_id' => 1,
    'startDate' => '2024-03-15 10:00:00',
    'dueDate' => '2024-03-20 10:00:00',
    'assignTo' => 1
];

// Debug each step of date handling
echo "<h3>Detailed Date Debugging</h3>";

// 1. Show raw input
echo "Raw Input:<br>";
echo "startDate: " . var_export($testProject['startDate'], true) . "<br>";
echo "dueDate: " . var_export($testProject['dueDate'], true) . "<br><br>";

// 2. Test strtotime conversion
echo "Timestamp Conversion:<br>";
echo "startDate timestamp: " . strtotime($testProject['startDate']) . "<br>";
echo "dueDate timestamp: " . strtotime($testProject['dueDate']) . "<br><br>";

// 3. Format dates with explicit timezone
date_default_timezone_set('Asia/Kolkata'); // Set your timezone
$formattedStartDate = date('Y-m-d H:i:s', strtotime($testProject['startDate']));
$formattedDueDate = date('Y-m-d H:i:s', strtotime($testProject['dueDate']));

echo "Formatted Dates with Timezone:<br>";
echo "Current Timezone: " . date_default_timezone_get() . "<br>";
echo "formattedStartDate: " . $formattedStartDate . "<br>";
echo "formattedDueDate: " . $formattedDueDate . "<br><br>";

// 4. Test database insertion with debug
try {
    $conn->begin_transaction();
    
    // Show the SQL query with actual values
    $debugQuery = "INSERT INTO projects (
        title, description, project_type, category_id, 
        start_date, end_date, created_by, assigned_to, 
        status, created_at
    ) VALUES (
        '{$testProject['projectTitle']}',
        '{$testProject['projectDescription']}',
        '{$testProject['projectType']}',
        {$testProject['category_id']},
        '{$formattedStartDate}',
        '{$formattedDueDate}',
        {$_SESSION['user_id']},
        {$testProject['assignTo']},
        'not_started',
        NOW()
    )";
    
    echo "Debug SQL Query:<br>";
    echo htmlspecialchars($debugQuery) . "<br><br>";
    
    // Prepare and execute with binding
    $query = "INSERT INTO projects (
        title, description, project_type, category_id, 
        start_date, end_date, created_by, assigned_to, 
        status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'not_started', NOW())";
    
    $stmt = $conn->prepare($query);
    
    // Debug parameter binding
    echo "Parameter Types and Values:<br>";
    echo "start_date type: " . gettype($formattedStartDate) . "<br>";
    echo "start_date value: " . $formattedStartDate . "<br>";
    echo "end_date type: " . gettype($formattedDueDate) . "<br>";
    echo "end_date value: " . $formattedDueDate . "<br><br>";
    
    $stmt->bind_param("sssiisii", 
        $testProject['projectTitle'],
        $testProject['projectDescription'],
        $testProject['projectType'],
        $testProject['category_id'],
        $formattedStartDate,
        $formattedDueDate,
        $_SESSION['user_id'],
        $testProject['assignTo']
    );
    
    if ($stmt->execute()) {
        $projectId = $conn->insert_id;
        echo "Test project inserted with ID: " . $projectId . "<br><br>";
        
        // Verify the stored data
        $checkQuery = "SELECT start_date, end_date FROM projects WHERE id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("i", $projectId);
        $checkStmt->execute();
        $result = $checkStmt->get_result()->fetch_assoc();
        
        echo "Database Values:<br>";
        echo "start_date in DB: " . var_export($result['start_date'], true) . "<br>";
        echo "end_date in DB: " . var_export($result['end_date'], true) . "<br><br>";
    }
    
    // Show table structure
    echo "<h3>Database Table Structure</h3>";
    $tableQuery = "SHOW CREATE TABLE projects";
    $tableResult = $conn->query($tableQuery);
    $tableInfo = $tableResult->fetch_assoc();
    echo "<pre>" . htmlspecialchars($tableInfo['Create Table']) . "</pre>";
    
    $conn->rollback();
    
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Error Code: " . $e->getCode() . "<br>";
}

$conn->close();
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
    h2, h3 { color: #333; }
    pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
</style> 
```
