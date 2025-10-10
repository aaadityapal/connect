<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get POST data
$data = isset($_POST['data']) ? json_decode($_POST['data'], true) : [];

// Validate required fields
if (empty($data)) {
    echo json_encode(['success' => false, 'error' => 'No data provided']);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Prepare insert statement
    $insertQuery = "INSERT INTO salary_payments (
        user_id, month, employee_id, employee_name, role, base_salary, 
        working_days, present_days, leave_taken, leave_deduction, 
        short_leave, late_days, late_deduction, one_hour_late, 
        one_hour_late_deduction, fourth_saturday_missing, 
        salary_days_calculated, penalty, net_salary, excess_day_salary
    ) VALUES (
        :user_id, :month, :employee_id, :employee_name, :role, :base_salary, 
        :working_days, :present_days, :leave_taken, :leave_deduction, 
        :short_leave, :late_days, :late_deduction, :one_hour_late, 
        :one_hour_late_deduction, :fourth_saturday_missing, 
        :salary_days_calculated, :penalty, :net_salary, :excess_day_salary
    )";
    
    $insertStmt = $pdo->prepare($insertQuery);
    
    // Insert each employee's data
    foreach ($data as $employee) {
        $insertStmt->execute([
            ':user_id' => $employee['user_id'],
            ':month' => $employee['month'],
            ':employee_id' => $employee['employee_id'],
            ':employee_name' => $employee['employee_name'],
            ':role' => $employee['role'],
            ':base_salary' => $employee['base_salary'],
            ':working_days' => $employee['working_days'],
            ':present_days' => $employee['present_days'],
            ':leave_taken' => $employee['leave_taken'],
            ':leave_deduction' => $employee['leave_deduction'],
            ':short_leave' => $employee['short_leave'],
            ':late_days' => $employee['late_days'],
            ':late_deduction' => $employee['late_deduction'],
            ':one_hour_late' => $employee['one_hour_late'],
            ':one_hour_late_deduction' => $employee['one_hour_late_deduction'],
            ':fourth_saturday_missing' => $employee['fourth_saturday_missing'],
            ':salary_days_calculated' => $employee['salary_days_calculated'],
            ':penalty' => $employee['penalty'],
            ':net_salary' => $employee['net_salary'],
            ':excess_day_salary' => $employee['excess_day_salary']
        ]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Salary payments saved successfully']);
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    error_log("Error saving salary payments: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>