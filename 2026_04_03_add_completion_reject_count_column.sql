-- Add rejection-tracking column for task completion workflow
-- Table: studio_assigned_tasks

ALTER TABLE studio_assigned_tasks
ADD COLUMN completion_reject_count INT NOT NULL DEFAULT 0;
