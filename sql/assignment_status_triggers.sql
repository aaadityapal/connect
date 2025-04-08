DELIMITER //

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS before_project_insert//
DROP TRIGGER IF EXISTS before_project_update//
DROP TRIGGER IF EXISTS before_stage_insert//
DROP TRIGGER IF EXISTS before_stage_update//
DROP TRIGGER IF EXISTS before_substage_insert//
DROP TRIGGER IF EXISTS before_substage_update//

-- Trigger for projects table - INSERT
CREATE TRIGGER before_project_insert
BEFORE INSERT ON projects
FOR EACH ROW
BEGIN
    IF NEW.assigned_to IS NULL THEN
        SET NEW.assignment_status = 'unassigned';
    ELSE
        SET NEW.assignment_status = 'assigned';
    END IF;
END//

-- Trigger for projects table - UPDATE
CREATE TRIGGER before_project_update
BEFORE UPDATE ON projects
FOR EACH ROW
BEGIN
    IF NEW.assigned_to IS NULL THEN
        SET NEW.assignment_status = 'unassigned';
    ELSE
        SET NEW.assignment_status = 'assigned';
    END IF;
END//

-- Trigger for project_stages table - INSERT
CREATE TRIGGER before_stage_insert
BEFORE INSERT ON project_stages
FOR EACH ROW
BEGIN
    IF NEW.assigned_to IS NULL THEN
        SET NEW.assignment_status = 'unassigned';
    ELSE
        SET NEW.assignment_status = 'assigned';
    END IF;
END//

-- Trigger for project_stages table - UPDATE
CREATE TRIGGER before_stage_update
BEFORE UPDATE ON project_stages
FOR EACH ROW
BEGIN
    IF NEW.assigned_to IS NULL THEN
        SET NEW.assignment_status = 'unassigned';
    ELSE
        SET NEW.assignment_status = 'assigned';
    END IF;
END//

-- Trigger for project_substages table - INSERT
CREATE TRIGGER before_substage_insert
BEFORE INSERT ON project_substages
FOR EACH ROW
BEGIN
    IF NEW.assigned_to IS NULL THEN
        SET NEW.assignment_status = 'unassigned';
    ELSE
        SET NEW.assignment_status = 'assigned';
    END IF;
END//

-- Trigger for project_substages table - UPDATE
CREATE TRIGGER before_substage_update
BEFORE UPDATE ON project_substages
FOR EACH ROW
BEGIN
    IF NEW.assigned_to IS NULL THEN
        SET NEW.assignment_status = 'unassigned';
    ELSE
        SET NEW.assignment_status = 'assigned';
    END IF;
END//

DELIMITER ; 