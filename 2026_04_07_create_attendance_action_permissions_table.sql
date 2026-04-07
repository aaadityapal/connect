CREATE TABLE IF NOT EXISTS attendance_action_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    can_approve_attendance TINYINT(1) NOT NULL DEFAULT 0,
    can_reject_attendance TINYINT(1) NOT NULL DEFAULT 0,
    can_edit_attendance TINYINT(1) NOT NULL DEFAULT 0,
    granted_by INT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_action_permissions_user (user_id),
    KEY idx_attendance_action_permissions_granted_by (granted_by),
    CONSTRAINT fk_att_action_perm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_action_perm_granted_by FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
);
