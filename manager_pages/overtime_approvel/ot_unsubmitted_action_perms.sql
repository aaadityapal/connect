-- 
-- Table structure for table `ot_unsubmitted_action_perms`
-- 
-- This table stores granular permissions for managers to action overtime:
-- 1. can_action_unsubmitted: Approve/Reject even if the employee hasn't submitted a report.
-- 2. can_action_expired: Approve/Reject overtime older than 15 days.
-- 3. can_action_completed: Modify or Re-action already approved/rejected overtime.
--

CREATE TABLE IF NOT EXISTS `ot_unsubmitted_action_perms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `can_action_unsubmitted` tinyint(1) NOT NULL DEFAULT 1,
  `can_action_expired` tinyint(1) NOT NULL DEFAULT 0,
  `can_action_completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
