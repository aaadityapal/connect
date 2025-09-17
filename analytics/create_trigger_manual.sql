-- Manual trigger creation script (run this in MySQL command line or phpMyAdmin)
-- This script creates the trigger for automatic salary change logging

DROP TRIGGER IF EXISTS tr_incremented_salary_analytics_log;

DELIMITER //
CREATE TRIGGER tr_incremented_salary_analytics_log
AFTER UPDATE ON incremented_salary_analytics
FOR EACH ROW
BEGIN
    IF OLD.incremented_salary != NEW.incremented_salary THEN
        INSERT INTO salary_change_log (
            user_id, 
            filter_month, 
            old_salary, 
            new_salary, 
            change_type, 
            changed_by, 
            change_date,
            notes
        ) VALUES (
            NEW.user_id,
            NEW.filter_month,
            OLD.incremented_salary,
            NEW.incremented_salary,
            'analytics_dashboard_update',
            NEW.created_by,
            NOW(),
            CONCAT('Salary changed from ₹', FORMAT(OLD.incremented_salary, 2), ' to ₹', FORMAT(NEW.incremented_salary, 2))
        );
    END IF;
END//
DELIMITER ;