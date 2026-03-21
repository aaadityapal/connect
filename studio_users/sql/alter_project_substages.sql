ALTER TABLE project_substages ADD COLUMN is_task_created TINYINT(1) DEFAULT 0;


ALTER TABLE global_activity_logs ADD COLUMN is_dismissed TINYINT(1) DEFAULT 0;