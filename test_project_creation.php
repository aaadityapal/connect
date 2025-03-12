<?php
session_start();
require_once 'config/db_connect.php';

// Simulate being logged in
$_SESSION['user_id'] = 1; // Replace with a valid user ID

// Test data that mimics the frontend submission
$testData = [
    'projectTitle' => 'Test Project',
    'projectDescription' => 'Test Description',
    'projectType' => '5', // Replace with valid project type ID
    'category_id' => 1,   // Replace with valid category ID
    'startDate' => '2024-03-15 10:00:00',
    'dueDate' => '2024-03-20 10:00:00',
    'assignTo' => 1,      // Replace with valid user ID
    'stages' => [
        [
            'title' => 'Test Stage 1',
            'assignTo' => 1,
            'startDate' => '2024-03-15 10:00:00',
            'endDate' => '2024-03-17 10:00:00',
            'substages' => [
                [
                    'title' => 'Test Substage 1',
                    'assignTo' => 1,
                    'startDate' => '2024-03-15 10:00:00',
                    'endDate' => '2024-03-16 10:00:00'
                ]
            ]
        ]
    ]
];

// Function to check date format
function validateDateTime($dateStr) {
    if (empty($dateStr)) return false;
    
    $timestamp = strtotime($dateStr);
    if ($timestamp === false) return false;
    
    $formatted = date('Y-m-d H:i:s', $timestamp);
    echo "Original: $dateStr\n";
    echo "Formatted: $formatted\n";
    echo "Timestamp: $timestamp\n\n";
    
    return true;
}

// Test date formatting
echo "Testing date formats:\n";
echo "Project Start Date: ";
validateDateTime($testData['startDate']);
echo "Project Due Date: ";
validateDateTime($testData['dueDate']);

// Simulate the API request
$ch = curl_init('http://localhost/your-project-path/api/create_project.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\nAPI Response (HTTP $httpCode):\n";
$responseData = json_decode($response, true);
print_r($responseData);

// Direct database test
try {
    echo "\nTesting direct database insertion:\n";
    
    $conn->begin_transaction();
    
    $query = "INSERT INTO projects (
        title, description, project_type, category_id, 
        start_date, end_date, created_by, assigned_to, 
        status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'not_started', NOW())";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("sssiisii", 
        $testData['projectTitle'],
        $testData['projectDescription'],
        $testData['projectType'],
        $testData['category_id'],
        $testData['startDate'],
        $testData['dueDate'],
        $_SESSION['user_id'],
        $testData['assignTo']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $projectId = $conn->insert_id;
    echo "Test project created with ID: $projectId\n";
    
    // Verify the inserted data
    $verifyQuery = "SELECT * FROM projects WHERE id = ?";
    $verifyStmt = $conn->prepare($verifyQuery);
    $verifyStmt->bind_param("i", $projectId);
    $verifyStmt->execute();
    $result = $verifyStmt->get_result()->fetch_assoc();
    
    echo "\nVerifying inserted data:\n";
    echo "Start Date: " . $result['start_date'] . "\n";
    echo "Due Date: " . $result['end_date'] . "\n";
    
    // Rollback the test data
    $conn->rollback();
    
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// Check database table structure
echo "\nChecking projects table structure:\n";
$tableQuery = "SHOW CREATE TABLE projects";
$tableResult = $conn->query($tableQuery);
$tableInfo = $tableResult->fetch_assoc();
print_r($tableInfo);

$conn->close();
?> 