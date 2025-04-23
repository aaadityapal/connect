-- SQL to update project_status_history table to add 'not_started' to the ENUM columns
-- Run this file to modify the table structure

-- First, check the current structure
SHOW CREATE TABLE project_status_history;

-- Modify the old_status column to include 'not_started'
ALTER TABLE project_status_history 
MODIFY COLUMN old_status ENUM('pending','in_progress','completed','on_hold','cancelled','not_started','sent_for_approval');

-- Modify the new_status column to include 'not_started'
ALTER TABLE project_status_history 
MODIFY COLUMN new_status ENUM('pending','in_progress','completed','on_hold','cancelled','not_started','sent_for_approval');

-- Verify the changes
SHOW CREATE TABLE project_status_history; 