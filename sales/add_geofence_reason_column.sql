-- Add geofence_outside_reason column to attendance table
ALTER TABLE attendance ADD COLUMN geofence_outside_reason TEXT NULL AFTER work_report;
