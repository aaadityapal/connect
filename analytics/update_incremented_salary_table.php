<?php
// Script to update the existing incremented salary analytics table with new columns
require_once '../config/db_connect.php';

try {
    echo "🔄 Updating incremented salary analytics table...\n\n";
    
    // Check if the table exists
    $check_table_query = "SHOW TABLES LIKE 'incremented_salary_analytics'";
    $result = $pdo->query($check_table_query);
    
    if ($result->rowCount() == 0) {
        echo "⚠️  Table 'incremented_salary_analytics' doesn't exist. Please run setup_incremented_salary_table.php first.\n";
        exit(1);
    }
    
    $pdo->beginTransaction();
    
    // Check if previous_incremented_salary column exists
    $check_column_query = "SHOW COLUMNS FROM incremented_salary_analytics LIKE 'previous_incremented_salary'";
    $column_result = $pdo->query($check_column_query);
    
    if ($column_result->rowCount() == 0) {
        echo "1. Adding 'previous_incremented_salary' column...\n";
        $pdo->exec("ALTER TABLE incremented_salary_analytics 
                   ADD COLUMN previous_incremented_salary DECIMAL(10,2) DEFAULT NULL 
                   AFTER base_salary");
        echo "   ✅ Added successfully\n";
    } else {
        echo "1. Column 'previous_incremented_salary' already exists\n";
    }
    
    // Check if actual_change_amount column exists
    $check_actual_change_query = "SHOW COLUMNS FROM incremented_salary_analytics LIKE 'actual_change_amount'";
    $actual_change_result = $pdo->query($check_actual_change_query);
    
    if ($actual_change_result->rowCount() == 0) {
        echo "2. Adding 'actual_change_amount' computed column...\n";
        $pdo->exec("ALTER TABLE incremented_salary_analytics 
                   ADD COLUMN actual_change_amount DECIMAL(10,2) GENERATED ALWAYS AS (
                       CASE 
                           WHEN previous_incremented_salary IS NOT NULL 
                           THEN (incremented_salary - previous_incremented_salary)
                           ELSE (incremented_salary - base_salary)
                       END
                   ) STORED 
                   AFTER increment_amount");
        echo "   ✅ Added successfully\n";
    } else {
        echo "2. Column 'actual_change_amount' already exists\n";
    }
    
    // Check if actual_change_percentage column exists
    $check_actual_pct_query = "SHOW COLUMNS FROM incremented_salary_analytics LIKE 'actual_change_percentage'";
    $actual_pct_result = $pdo->query($check_actual_pct_query);
    
    if ($actual_pct_result->rowCount() == 0) {
        echo "3. Adding 'actual_change_percentage' computed column...\n";
        $pdo->exec("ALTER TABLE incremented_salary_analytics 
                   ADD COLUMN actual_change_percentage DECIMAL(5,2) GENERATED ALWAYS AS (
                       CASE 
                           WHEN previous_incremented_salary IS NOT NULL AND previous_incremented_salary > 0 
                           THEN ((incremented_salary - previous_incremented_salary) / previous_incremented_salary * 100)
                           WHEN base_salary > 0 
                           THEN ((incremented_salary - base_salary) / base_salary * 100)
                           ELSE 0 
                       END
                   ) STORED 
                   AFTER increment_percentage");
        echo "   ✅ Added successfully\n";
    } else {
        echo "3. Column 'actual_change_percentage' already exists\n";
    }
    
    // Update the view to use actual_change_amount
    echo "4. Updating the analytics view...\n";
    try {
        $pdo->exec("DROP VIEW IF EXISTS v_incremented_salary_analytics");
        $pdo->exec("CREATE VIEW v_incremented_salary_analytics AS
                   SELECT 
                       isa.*,
                       u.username,
                       u.employee_id,
                       u.email,
                       u.department,
                       u.designation,
                       creator.username as created_by_username,
                       CONCAT(u.username, ' (', u.employee_id, ')') as user_display_name,
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
                   ORDER BY isa.filter_month DESC, u.username ASC");
        echo "   ✅ View updated successfully\n";
    } catch (PDOException $e) {
        echo "   ⚠️ Warning: View update failed - " . $e->getMessage() . "\n";
    }
    
    $pdo->commit();
    
    echo "\n🎉 Successfully updated the incremented salary analytics table!\n";
    echo "📊 New features:\n";
    echo "   • Previous incremented salary tracking\n";
    echo "   • Actual change amount calculation\n";
    echo "   • Actual change percentage calculation\n";
    echo "   • Updated analytics view\n\n";
    echo "🔗 You can now view enhanced analytics at: incremented_salary_analytics_table.php\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "\n❌ Error updating table: " . $e->getMessage() . "\n";
    exit(1);
}
?>