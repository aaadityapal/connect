<?php
// Simplified table creation script
require_once '../config/db_connect.php';

echo "<h2>Creating Incremented Salary Analytics Table</h2>";

try {
    $pdo->beginTransaction();
    
    echo "<p>1. Creating 'salary_change_log' table...</p>";
    $log_table_sql = "CREATE TABLE IF NOT EXISTS salary_change_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        filter_month VARCHAR(7) NOT NULL,
        old_salary DECIMAL(10,2) NOT NULL,
        new_salary DECIMAL(10,2) NOT NULL,
        change_type VARCHAR(50) NOT NULL,
        changed_by INT DEFAULT NULL,
        change_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        notes TEXT DEFAULT NULL,
        
        INDEX idx_user_id (user_id),
        INDEX idx_filter_month (filter_month),
        INDEX idx_change_date (change_date)
    )";
    $pdo->exec($log_table_sql);
    echo "<p style='color: green;'>✅ Log table created successfully</p>";
    
    echo "<p>2. Creating 'incremented_salary_analytics' table...</p>";
    $main_table_sql = "CREATE TABLE IF NOT EXISTS incremented_salary_analytics (
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
        
        INDEX idx_user_month (user_id, filter_month),
        INDEX idx_filter_month (filter_month),
        INDEX idx_created_at (created_at),
        INDEX idx_status (status),
        
        UNIQUE KEY unique_user_month (user_id, filter_month)
    )";
    $pdo->exec($main_table_sql);
    echo "<p style='color: green;'>✅ Main analytics table created successfully</p>";
    
    echo "<p>3. Creating analytics view...</p>";
    $view_sql = "CREATE OR REPLACE VIEW v_incremented_salary_analytics AS
    SELECT 
        isa.*,
        u.username,
        u.employee_id,
        u.email,
        u.department,
        u.designation,
        creator.username as created_by_username,
        CONCAT(u.username, ' (', COALESCE(u.employee_id, 'N/A'), ')') as user_display_name,
        DATE_FORMAT(isa.filter_month, '%M %Y') as month_display,
        CASE 
            WHEN isa.actual_change_amount > 0 THEN 'Increment'
            WHEN isa.actual_change_amount < 0 THEN 'Decrement'
            ELSE 'No Change'
        END as increment_type,
        CASE 
            WHEN isa.final_salary_percentage >= 90 THEN 'Excellent'
            WHEN isa.final_salary_percentage >= 80 THEN 'Good'
            WHEN isa.final_salary_percentage >= 70 THEN 'Average'
            ELSE 'Needs Attention'
        END as performance_rating
    FROM incremented_salary_analytics isa
    LEFT JOIN users u ON isa.user_id = u.id
    LEFT JOIN users creator ON isa.created_by = creator.id
    WHERE isa.status = 'active'
    ORDER BY isa.filter_month DESC, u.username ASC";
    $pdo->exec($view_sql);
    echo "<p style='color: green;'>✅ Analytics view created successfully</p>";
    
    $pdo->commit();
    
    echo "<h3 style='color: green;'>🎉 Success! All tables and views created successfully!</h3>";
    echo "<p><a href='salary_analytics_dashboard.php' style='background: #007bff; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Go to Analytics Dashboard</a></p>";
    echo "<p><a href='incremented_salary_analytics_table.php' style='background: #28a745; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>View Analytics Table</a></p>";
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}
?>