-- SQL command to add resubmit_count column to overtime_requests table
ALTER TABLE `overtime_requests` 
ADD COLUMN `resubmit_count` INT(11) DEFAULT 0 AFTER `status`;