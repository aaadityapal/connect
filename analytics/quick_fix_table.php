<?php
// Quick fix script to create the table if it doesn't exist
require_once '../config/db_connect.php';

header('Content-Type: application/json');

try {
    // Check if table exists
    $check_query = "SHOW TABLES LIKE 'incremented_salary_analytics'";
    $result = $pdo->query($check_query);
    
    if ($result->rowCount() == 0) {
        // Table doesn't exist, create it
        $create_sql = "CREATE TABLE incremented_salary_analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            filter_month VARCHAR(7) NOT NULL,
            base_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            previous_incremented_salary DECIMAL(10,2) DEFAULT NULL,
            incremented_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            increment_amount DECIMAL(10,2) DEFAULT 0.00,
            actual_change_amount DECIMAL(10,2) DEFAULT 0.00,
            increment_percentage DECIMAL(5,2) DEFAULT 0.00,
            actual_change_percentage DECIMAL(5,2) DEFAULT 0.00,
            working_days INT DEFAULT 0,
            present_days INT DEFAULT 0,
            excess_days INT DEFAULT 0,
            late_punch_in_days INT DEFAULT 0,
            late_deduction_amount DECIMAL(10,2) DEFAULT 0.00,
            leave_taken_days DECIMAL(4,1) DEFAULT 0.0,
            leave_deduction_amount DECIMAL(10,2) DEFAULT 0.00,
            one_hour_late_days INT DEFAULT 0,
            one_hour_late_deduction_amount DECIMAL(10,2) DEFAULT 0.00,
            fourth_saturday_penalty_amount DECIMAL(10,2) DEFAULT 0.00,
            total_deductions DECIMAL(10,2) DEFAULT 0.00,
            monthly_salary_after_deductions DECIMAL(10,2) DEFAULT 0.00,
            final_salary_percentage DECIMAL(5,2) DEFAULT 0.00,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            notes TEXT DEFAULT NULL,
            status ENUM('active', 'archived', 'cancelled') DEFAULT 'active',
            UNIQUE KEY unique_user_month (user_id, filter_month)
        )";
        
        $pdo->exec($create_sql);
        echo json_encode(['success' => true, 'message' => 'Table created successfully']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Table already exists']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>