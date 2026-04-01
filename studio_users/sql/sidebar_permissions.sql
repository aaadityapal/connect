-- Create the sidebar permissions table
CREATE TABLE IF NOT EXISTS `sidebar_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `menu_id` VARCHAR(50) NOT NULL,
    `role` VARCHAR(100) NOT NULL,
    `can_access` TINYINT(1) DEFAULT 1,
    UNIQUE KEY `unique_menu_role` (`menu_id`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial seeding for known menu items:
-- (This ensures defaults are set for core functionalities)

-- Dashboard
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'index', `role`, 1 FROM `users`;

-- Profile
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'profile', `role`, 1 FROM `users`;

-- Leave & Expenses
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'apply-leave', `role`, 1 FROM `users`;
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'travel-expenses', `role`, 1 FROM `users`;
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'overtime', `role`, 1 FROM `users`;

-- Work
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'projects', `role`, 1 FROM `users`;
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'site-updates', `role`, 1 FROM `users`;
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'my-tasks', `role`, 1 FROM `users`;
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'worksheet', `role`, 1 FROM `users`;
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'analytics', `role`, 1 FROM `users`;

-- HR & Admin (Default Restricted to admin, hr, relationship manager)
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'hr-corner', `role`, 
       IF(LOWER(`role`) IN ('admin', 'hr', 'relationship manager'), 1, 0) 
FROM `users`;

-- Management (Default Restricted to admin, hr, relationship manager)
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'hierarchy', `role`, 
       IF(LOWER(`role`) IN ('admin', 'hr', 'relationship manager'), 1, 0) 
FROM `users`;
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'manager-mapping', `role`, 
       IF(LOWER(`role`) IN ('admin', 'hr', 'relationship manager'), 1, 0) 
FROM `users`;
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'overtime-mapping', `role`, 
       IF(LOWER(`role`) IN ('admin', 'hr', 'relationship manager'), 1, 0) 
FROM `users`;

-- System
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'settings', `role`, 1 FROM `users`;
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'help', `role`, 1 FROM `users`;
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'logout', `role`, 1 FROM `users`;

-- Restricted: Sidebar Role Access (Admin Only)
INSERT IGNORE INTO `sidebar_permissions` (`menu_id`, `role`, `can_access`)
SELECT DISTINCT 'sidebar-role-access', `role`, 
       IF(LOWER(`role`) = 'admin', 1, 0) 
FROM `users`;
