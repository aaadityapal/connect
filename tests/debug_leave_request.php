<?php
session_start();
header('Content-Type: application/json');

// Enable detailed error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Test data that mimics what we're receiving
$testData = [
    'approver_id' => '11',
    'reason' => 'test',
    'start_date' => '2025-08-17',
    'end_date' => '2025-08-17',
    'leave_type' => 11,
    'dates' => [[
        'date' => '2025-08-17',
        'dayType' => 'Evening',
        'duration' => 0.25
    ]]
];

// Log the test data
echo "Test data:\n";
echo print_r($testData, true) . "\n";
error_log("Test data: " . print_r($testData, true));

require_once __DIR__ . '/../config/db_connect.php';

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Log PDO attributes
    echo "PDO attributes:\n";
    echo "ERRMODE: " . $pdo->getAttribute(PDO::ATTR_ERRMODE) . "\n";
    echo "EMULATE_PREPARES: " . $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) . "\n";
    
    // Test the duration_type and day_type columns
    $testEnum = $pdo->query("
        SELECT 
            COLUMN_TYPE 
        FROM 
            INFORMATION_SCHEMA.COLUMNS 
        WHERE 
            TABLE_NAME = 'leave_request' 
            AND COLUMN_NAME IN ('duration_type', 'day_type')
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Column definitions:\n";
    print_r($testEnum);
    
    // Set PDO to throw exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test database connection
    $testStmt = $pdo->query("SELECT 1");
    error_log("Database connection test successful");
    
    // Get table structure
    $tableStmt = $pdo->query("DESCRIBE leave_request");
    $columns = $tableStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Table structure:\n";
    echo print_r($columns, true) . "\n";
    error_log("Table structure:");
    error_log(print_r($columns, true));
    
    foreach ($testData['dates'] as $date) {
        // Map day type
        $durationType = 'full';
        $dayType = 'first_half';
        
        switch ($date['dayType'] ?? 'Full Day') {
            case 'Full Day':
                $durationType = 'full';
                $dayType = 'first_half';
                break;
            case 'Half Day':
            case 'Morning Half':
                $durationType = 'first_half';
                $dayType = 'first_half';
                break;
            case 'Second Half':
                $durationType = 'first_half';
                $dayType = 'second_half';
                break;
            case 'Morning':
                $durationType = 'second_half';
                $dayType = 'first_half';
                break;
            case 'Evening':
                $durationType = 'second_half';
                $dayType = 'second_half';
                break;
        }
        
        // Prepare SQL with explicit column list
        $sql = "INSERT INTO leave_request (
            user_id,
            leave_type,
            start_date,
            end_date,
            reason,
            duration,
            time_from,
            time_to,
            status,
            action_by,
            duration_type,
            day_type,
            comp_off_source_date
        ) VALUES (
            :user_id,
            :leave_type,
            :start_date,
            :end_date,
            :reason,
            :duration,
            :time_from,
            :time_to,
            'pending',
            :action_by,
            :duration_type,
            :day_type,
            :comp_off_source_date
        )";
        
        error_log("SQL Query: " . $sql);
        
        $stmt = $pdo->prepare($sql);
        
        // Set up parameters
        $params = [
            'user_id' => $_SESSION['user_id'] ?? 1, // Fallback for testing
            'leave_type' => $testData['leave_type'],
            'start_date' => date('Y-m-d', strtotime($date['date'])),
            'end_date' => date('Y-m-d', strtotime($date['date'])),
            'reason' => $testData['reason'],
            'duration' => $date['duration'],
            'time_from' => null,
            'time_to' => null,
            'action_by' => $testData['approver_id'],
            'duration_type' => $durationType,
            'day_type' => $dayType,
            'comp_off_source_date' => null
        ];
        
        error_log("Parameters: " . print_r($params, true));
        
        // Try to execute and log any errors
        try {
            $result = $stmt->execute($params);
            error_log("Execute result: " . ($result ? "true" : "false"));
            if (!$result) {
                $error = $stmt->errorInfo();
                error_log("Statement error: " . print_r($error, true));
            }
        } catch (PDOException $e) {
            error_log("PDO Exception: " . $e->getMessage());
            error_log("Error code: " . $e->getCode());
            throw $e;
        }
    }
    
    // Commit transaction
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Test completed successfully']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Test error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => 'Test failed',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
?>
