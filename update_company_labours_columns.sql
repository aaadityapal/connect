-- Script to add morning_attendance and afternoon_attendance columns to company_labours table
-- This allows for more precise attendance tracking

-- Check if the table has the old structure before making changes
DELIMITER //
CREATE PROCEDURE update_company_labours_columns()
BEGIN
    DECLARE table_exists INT;
    DECLARE attendance_column_exists INT;
    DECLARE morning_column_exists INT;
    
    -- Check if table exists
    SELECT COUNT(1) INTO table_exists 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'company_labours';
    
    IF table_exists > 0 THEN
        -- Check if we have the old structure
        SELECT COUNT(1) INTO attendance_column_exists
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = 'company_labours'
        AND column_name = 'attendance';
        
        SELECT COUNT(1) INTO morning_column_exists
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = 'company_labours'
        AND column_name = 'morning_attendance';
        
        -- If we have the old structure, migrate the data
        IF attendance_column_exists > 0 AND morning_column_exists = 0 THEN
            -- Add temporary columns
            ALTER TABLE `company_labours`
            ADD COLUMN `morning_attendance_temp` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present',
            ADD COLUMN `afternoon_attendance_temp` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present',
            ADD COLUMN `ot_wages` decimal(10,2) NOT NULL DEFAULT '0.00';
            
            -- Copy attendance to both morning and afternoon
            UPDATE `company_labours` SET 
            `morning_attendance_temp` = `attendance`,
            `afternoon_attendance_temp` = `attendance`;
            
            -- Drop old column and rename new ones
            ALTER TABLE `company_labours`
            DROP COLUMN `attendance`,
            CHANGE COLUMN `morning_attendance_temp` `morning_attendance` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present',
            CHANGE COLUMN `afternoon_attendance_temp` `afternoon_attendance` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present';
            
            SELECT 'Company labours table updated with morning_attendance and afternoon_attendance columns' AS message;
        ELSE
            IF morning_column_exists > 0 THEN
                SELECT 'Company labours table already has morning_attendance and afternoon_attendance columns' AS message;
            ELSE
                SELECT 'Company labours table structure is unexpected' AS message;
            END IF;
        END IF;
    ELSE
        SELECT 'Company labours table does not exist' AS message;
    END IF;
END //
DELIMITER ;

CALL update_company_labours_columns();
DROP PROCEDURE IF EXISTS update_company_labours_columns; 