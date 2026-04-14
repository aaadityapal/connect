-- ============================================================
-- travel_approver_day_schedules
-- Per-approver, per-day start/end time configuration
-- Replaces the single start_time/end_time in travel_approver_schedules
-- ============================================================

CREATE TABLE IF NOT EXISTS `travel_approver_day_schedules` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `approver_id` INT(11)      NOT NULL,
  `day_name`    VARCHAR(10)  NOT NULL COMMENT 'Monday, Tuesday, ..., Sunday',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 0,
  `start_time`  TIME         NOT NULL DEFAULT '09:00:00',
  `end_time`    TIME         NOT NULL DEFAULT '18:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_approver_day` (`approver_id`, `day_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Seed default Mon-Fri 09:00-18:00 for all existing approvers
-- (only those already in travel_expense_mapping)
-- ============================================================

INSERT IGNORE INTO `travel_approver_day_schedules`
    (`approver_id`, `day_name`, `is_active`, `start_time`, `end_time`)
SELECT DISTINCT u.id, d.day_name,
       CASE WHEN d.day_name IN ('Saturday','Sunday') THEN 0 ELSE 1 END,
       '09:00:00', '18:00:00'
FROM (
    SELECT manager_id AS uid FROM travel_expense_mapping WHERE manager_id IS NOT NULL
    UNION
    SELECT hr_id FROM travel_expense_mapping WHERE hr_id IS NOT NULL
    UNION
    SELECT senior_manager_id FROM travel_expense_mapping WHERE senior_manager_id IS NOT NULL
) ids
JOIN users u ON u.id = ids.uid
CROSS JOIN (
    SELECT 'Monday'    AS day_name UNION ALL
    SELECT 'Tuesday'              UNION ALL
    SELECT 'Wednesday'            UNION ALL
    SELECT 'Thursday'             UNION ALL
    SELECT 'Friday'               UNION ALL
    SELECT 'Saturday'             UNION ALL
    SELECT 'Sunday'
) d;
