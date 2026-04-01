-- ============================================================
-- Table: studio_task_completion_approvals
-- Purpose: Stores creator approvals for completed assigned tasks
-- Notes:
-- - Used by: studio_users/api/check_pending_task_approvals.php
--           studio_users/api/approve_task_completion.php
-- - This avoids altering studio_assigned_tasks schema.
-- ============================================================

CREATE TABLE IF NOT EXISTS `studio_task_completion_approvals` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `task_id` INT(11) NOT NULL,
  `approved_by` INT(11) NOT NULL,
  `approved_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_id` (`task_id`),
  KEY `idx_approved_by` (`approved_by`),
  KEY `idx_approved_at` (`approved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
