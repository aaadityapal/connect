-- SQL script to add resubmission tracking to travel_expenses table
-- This adds columns to track resubmission history and limits

ALTER TABLE travel_expenses 
ADD COLUMN original_expense_id INT DEFAULT NULL COMMENT 'ID of the original expense if this is a resubmission',
ADD COLUMN resubmission_count INT DEFAULT 0 COMMENT 'Number of times this expense has been resubmitted',
ADD COLUMN is_resubmitted TINYINT(1) DEFAULT 0 COMMENT 'Whether this expense is a resubmission of another',
ADD COLUMN resubmitted_from INT DEFAULT NULL COMMENT 'ID of the expense this was resubmitted from',
ADD COLUMN resubmission_date TIMESTAMP NULL DEFAULT NULL COMMENT 'When this expense was resubmitted',
ADD COLUMN max_resubmissions INT DEFAULT 3 COMMENT 'Maximum allowed resubmissions for this expense';

-- Add foreign key constraint for original_expense_id
ALTER TABLE travel_expenses 
ADD CONSTRAINT fk_original_expense 
FOREIGN KEY (original_expense_id) REFERENCES travel_expenses(id) ON DELETE SET NULL;

-- Add foreign key constraint for resubmitted_from
ALTER TABLE travel_expenses 
ADD CONSTRAINT fk_resubmitted_from 
FOREIGN KEY (resubmitted_from) REFERENCES travel_expenses(id) ON DELETE SET NULL;

-- Add index for better query performance
CREATE INDEX idx_original_expense_id ON travel_expenses(original_expense_id);
CREATE INDEX idx_resubmitted_from ON travel_expenses(resubmitted_from);
CREATE INDEX idx_is_resubmitted ON travel_expenses(is_resubmitted);

-- Update existing expenses to set default values
UPDATE travel_expenses 
SET resubmission_count = 0, 
    is_resubmitted = 0, 
    max_resubmissions = 3 
WHERE resubmission_count IS NULL;

-- Optional: Create a view to easily get resubmission chain
CREATE VIEW v_expense_resubmission_chain AS
SELECT 
    e.id,
    e.user_id,
    e.purpose,
    e.amount,
    e.status,
    e.created_at,
    e.resubmission_count,
    e.is_resubmitted,
    e.original_expense_id,
    e.resubmitted_from,
    e.resubmission_date,
    e.max_resubmissions,
    CASE 
        WHEN e.resubmission_count >= e.max_resubmissions THEN 1 
        ELSE 0 
    END as max_resubmissions_reached,
    orig.id as original_id,
    orig.created_at as original_created_at,
    orig.status as original_status
FROM travel_expenses e
LEFT JOIN travel_expenses orig ON e.original_expense_id = orig.id;

-- Create a function to get the root expense ID (the very first expense in chain)
DELIMITER //
CREATE FUNCTION GetRootExpenseId(expense_id INT) 
RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE root_id INT DEFAULT expense_id;
    DECLARE parent_id INT;
    
    -- Follow the chain back to the root
    expense_loop: LOOP
        SELECT original_expense_id INTO parent_id 
        FROM travel_expenses 
        WHERE id = root_id;
        
        IF parent_id IS NULL THEN
            LEAVE expense_loop;
        END IF;
        
        SET root_id = parent_id;
    END LOOP;
    
    RETURN root_id;
END//
DELIMITER ;

-- Create a procedure to get resubmission history
DELIMITER //
CREATE PROCEDURE GetResubmissionHistory(IN expense_id INT)
BEGIN
    DECLARE root_id INT;
    
    -- Get the root expense ID
    SET root_id = GetRootExpenseId(expense_id);
    
    -- Return the complete resubmission chain
    SELECT 
        e.id,
        e.purpose,
        e.amount,
        e.status,
        e.created_at,
        e.resubmission_count,
        e.is_resubmitted,
        e.resubmission_date,
        CASE 
            WHEN e.id = root_id THEN 'Original'
            ELSE CONCAT('Resubmission #', e.resubmission_count)
        END as submission_type
    FROM travel_expenses e
    WHERE e.original_expense_id = root_id 
       OR e.id = root_id
    ORDER BY e.created_at ASC;
END//
DELIMITER ;

-- Sample queries to test the new functionality:

-- Get all resubmitted expenses
-- SELECT * FROM travel_expenses WHERE is_resubmitted = 1;

-- Get expenses that have reached max resubmissions
-- SELECT * FROM v_expense_resubmission_chain WHERE max_resubmissions_reached = 1;

-- Get resubmission history for a specific expense
-- CALL GetResubmissionHistory(1);

-- Get count of resubmissions for each original expense
-- SELECT 
--     original_expense_id,
--     COUNT(*) as total_resubmissions
-- FROM travel_expenses 
-- WHERE is_resubmitted = 1
-- GROUP BY original_expense_id;