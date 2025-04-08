-- Fix updated_at column in projects table
ALTER TABLE projects MODIFY updated_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE projects MODIFY created_at TIMESTAMP NULL DEFAULT NULL;

-- Fix updated_at column in project_stages table
ALTER TABLE project_stages MODIFY updated_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE project_stages MODIFY created_at TIMESTAMP NULL DEFAULT NULL;

-- Fix updated_at column in project_substages table
ALTER TABLE project_substages MODIFY updated_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE project_substages MODIFY created_at TIMESTAMP NULL DEFAULT NULL; 