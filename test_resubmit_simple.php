<?php
// Detailed test file to debug the resubmit functionality step by step
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response = array(
        'success' => false,
        'message' => 'User not logged in'
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Check if database connection is successful
if ($conn->connect_error) {
    $response = array(
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$test_results = array();

// Test 1: Check if there are any rejected expenses for this user
$test_results['step1'] = 'Checking for rejected expenses...';
try {
    $stmt = $conn->prepare("SELECT * FROM travel_expenses WHERE user_id = ? AND status = 'rejected' LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $rejected_expense = $result->fetch_assoc();
        $test_results['step1_result'] = 'Found rejected expense';
        $test_results['rejected_expense_id'] = $rejected_expense['id'];
        $test_results['rejected_expense_purpose'] = $rejected_expense['purpose'];
        
        // Test 2: Try to create a new expense (simulation)
        $test_results['step2'] = 'Testing INSERT statement preparation...';
        try {
            $insert_stmt = $conn->prepare("
                INSERT INTO travel_expenses (
                    user_id, purpose, mode_of_transport, from_location, 
                    to_location, travel_date, distance, amount, status, notes, 
                    bill_file_path, manager_status, accountant_status, hr_status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, 'pending', 'pending', 'pending', NOW())
            ");
            
            if ($insert_stmt) {
                $test_results['step2_result'] = 'INSERT statement prepared successfully';
                
                // Test 3: Try binding parameters
                $test_results['step3'] = 'Testing parameter binding...';
                try {
                    $notes = isset($rejected_expense['notes']) ? $rejected_expense['notes'] : '';
                    $bill_path = isset($rejected_expense['bill_file_path']) ? $rejected_expense['bill_file_path'] : '';
                    
                    $bind_result = $insert_stmt->bind_param(
                        "isssssddss",
                        $rejected_expense['user_id'],
                        $rejected_expense['purpose'],
                        $rejected_expense['mode_of_transport'],
                        $rejected_expense['from_location'],
                        $rejected_expense['to_location'],
                        $rejected_expense['travel_date'],
                        $rejected_expense['distance'],
                        $rejected_expense['amount'],
                        $notes,
                        $bill_path
                    );
                    
                    if ($bind_result) {
                        $test_results['step3_result'] = 'Parameters bound successfully';
                        
                        // Test 4: Try executing (but don't commit)
                        $test_results['step4'] = 'Testing INSERT execution...';
                        $conn->begin_transaction();
                        try {
                            $execute_result = $insert_stmt->execute();
                            if ($execute_result) {
                                $new_id = $conn->insert_id;
                                $test_results['step4_result'] = 'INSERT executed successfully';
                                $test_results['new_expense_id'] = $new_id;
                                
                                // Rollback to not actually create the record
                                $conn->rollback();
                                $test_results['rollback'] = 'Transaction rolled back (test mode)';
                            } else {
                                $test_results['step4_result'] = 'INSERT execution failed: ' . $insert_stmt->error;
                            }
                        } catch (Exception $e) {
                            $conn->rollback();
                            $test_results['step4_result'] = 'INSERT execution exception: ' . $e->getMessage();
                        }
                    } else {
                        $test_results['step3_result'] = 'Parameter binding failed: ' . $insert_stmt->error;
                    }
                } catch (Exception $e) {
                    $test_results['step3_result'] = 'Parameter binding exception: ' . $e->getMessage();
                }
                
                $insert_stmt->close();
            } else {
                $test_results['step2_result'] = 'INSERT statement preparation failed: ' . $conn->error;
            }
        } catch (Exception $e) {
            $test_results['step2_result'] = 'INSERT statement exception: ' . $e->getMessage();
        }
    } else {
        $test_results['step1_result'] = 'No rejected expenses found for this user';
        $test_results['suggestion'] = 'Create a rejected expense first to test resubmission';
    }
    
    $stmt->close();
} catch (Exception $e) {
    $test_results['step1_result'] = 'Query exception: ' . $e->getMessage();
}

// Test 5: Check table structure
$test_results['step5'] = 'Checking table structure...';
try {
    $structure_result = $conn->query("DESCRIBE travel_expenses");
    if ($structure_result) {
        $columns = array();
        while ($row = $structure_result->fetch_assoc()) {
            $columns[] = $row['Field'] . ' (' . $row['Type'] . ')';
        }
        $test_results['table_columns'] = $columns;
        $test_results['step5_result'] = 'Table structure retrieved successfully';
    } else {
        $test_results['step5_result'] = 'Failed to get table structure: ' . $conn->error;
    }
} catch (Exception $e) {
    $test_results['step5_result'] = 'Table structure exception: ' . $e->getMessage();
}

// Final response
$response = array(
    'success' => true,
    'message' => 'Resubmit functionality test completed',
    'user_id' => $user_id,
    'timestamp' => date('Y-m-d H:i:s'),
    'test_results' => $test_results
);

header('Content-Type: application/json');
echo json_encode($response, JSON_PRETTY_PRINT);
exit();
?>