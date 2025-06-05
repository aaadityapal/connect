-- Add new columns to travel_expenses table
ALTER TABLE travel_expenses
ADD COLUMN updated_by INT(11) NULL COMMENT 'User ID of the person who last updated the record',
ADD COLUMN manager_reason TEXT NULL COMMENT 'Reason provided by manager for approval/rejection',
ADD COLUMN accountant_reason TEXT NULL COMMENT 'Reason provided by accountant for approval/rejection',
ADD COLUMN hr_reason TEXT NULL COMMENT 'Reason provided by HR for approval/rejection',
ADD COLUMN manager_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' COMMENT 'Approval status from manager',
ADD COLUMN accountant_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' COMMENT 'Approval status from accountant',
ADD COLUMN hr_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' COMMENT 'Approval status from HR';

-- Add foreign key constraint for updated_by
ALTER TABLE travel_expenses
ADD CONSTRAINT fk_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

-- Create a trigger to automatically update the main status field based on the individual status fields
DELIMITER //

CREATE TRIGGER update_travel_expense_status
BEFORE UPDATE ON travel_expenses
FOR EACH ROW
BEGIN
    -- If any status is rejected, the main status becomes rejected
    IF NEW.manager_status = 'rejected' OR NEW.accountant_status = 'rejected' OR NEW.hr_status = 'rejected' THEN
        SET NEW.status = 'rejected';
    -- If any status is pending, the main status becomes pending
    ELSEIF NEW.manager_status = 'pending' OR NEW.accountant_status = 'pending' OR NEW.hr_status = 'pending' THEN
        SET NEW.status = 'pending';
    -- If all statuses are approved, the main status becomes approved
    ELSE
        SET NEW.status = 'approved';
    END IF;
    
    -- Set updated_at to current timestamp
    SET NEW.updated_at = CURRENT_TIMESTAMP();
END//

DELIMITER ;

-- Create a trigger for new records to set the initial status
DELIMITER //

CREATE TRIGGER set_initial_travel_expense_status
BEFORE INSERT ON travel_expenses
FOR EACH ROW
BEGIN
    -- Set the main status based on the individual status fields (which default to pending)
    SET NEW.status = 'pending';
END//

DELIMITER ; 