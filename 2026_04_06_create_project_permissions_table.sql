-- Project create permission control
-- Run this once in your database.

CREATE TABLE IF NOT EXISTS project_permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    can_create_project TINYINT(1) NOT NULL DEFAULT 0,
    can_upload_substage_media TINYINT(1) NOT NULL DEFAULT 0,
    granted_by INT NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY ux_project_permissions_user (user_id),
    KEY idx_project_permissions_create (can_create_project),
    KEY idx_project_permissions_media_upload (can_upload_substage_media),
    CONSTRAINT fk_project_permissions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_project_permissions_granted_by
        FOREIGN KEY (granted_by) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example grants (edit user ids as needed):
-- INSERT INTO project_permissions (user_id, can_create_project, can_upload_substage_media, granted_by, notes)
-- VALUES
-- (1, 1, 1, 1, 'Admin can create projects and upload media'),
-- (5, 1, 1, 1, 'Manager can create projects and upload media')
-- ON DUPLICATE KEY UPDATE
--     can_create_project = VALUES(can_create_project),
--     can_upload_substage_media = VALUES(can_upload_substage_media),
--     granted_by = VALUES(granted_by),
--     notes = VALUES(notes);
