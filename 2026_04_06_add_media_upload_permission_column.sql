-- Add substage media upload permission to project_permissions
-- Run this if project_permissions already exists.

ALTER TABLE project_permissions
    ADD COLUMN can_upload_substage_media TINYINT(1) NOT NULL DEFAULT 0 AFTER can_create_project;

ALTER TABLE project_permissions
    ADD KEY idx_project_permissions_media_upload (can_upload_substage_media);
