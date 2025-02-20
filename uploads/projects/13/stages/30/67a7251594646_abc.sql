-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 06, 2025 at 08:02 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `crm`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_apply_leave` (IN `p_user_id` INT, IN `p_leave_type_id` INT, IN `p_start_date` DATE, IN `p_end_date` DATE, IN `p_reason` TEXT, IN `p_half_day` BOOLEAN)   BEGIN
    INSERT INTO leaves (
        user_id, 
        leave_type_id, 
        start_date, 
        end_date, 
        reason, 
        half_day
    ) VALUES (
        p_user_id,
        p_leave_type_id,
        p_start_date,
        p_end_date,
        p_reason,
        p_half_day
    );
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_leave_status` (IN `p_leave_id` INT, IN `p_status` ENUM('pending','approved','rejected'), IN `p_comments` TEXT, IN `p_modified_by` INT)   BEGIN
    DECLARE current_status VARCHAR(20);
    
    -- Get current status
    SELECT status INTO current_status 
    FROM leaves 
    WHERE id = p_leave_id;
    
    -- Update leave status
    UPDATE leaves 
    SET 
        status = p_status,
        modified_by = p_modified_by,
        modified_at = CURRENT_TIMESTAMP
    WHERE id = p_leave_id;
    
    -- Record in history
    INSERT INTO leave_history (
        leave_id,
        status_from,
        status_to,
        comments,
        created_by
    ) VALUES (
        p_leave_id,
        current_status,
        p_status,
        p_comments,
        p_modified_by
    );
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `activity_type`, `description`, `timestamp`) VALUES
(1, 21, 'password_change', 'Password updated', '2024-12-23 13:07:13'),
(2, 21, 'profile_update', 'Personal information updated', '2024-12-23 13:14:22'),
(3, 21, 'profile_update', 'Personal information updated', '2024-12-23 13:16:10'),
(4, 21, 'profile_update', 'Profile information updated', '2024-12-23 13:20:56'),
(5, 21, 'profile_update', 'Profile information updated', '2024-12-23 13:23:59'),
(6, 15, 'profile_update', 'Profile information updated', '2024-12-23 13:40:17'),
(7, 2, 'profile_update', 'Profile information updated', '2024-12-27 07:16:08'),
(8, 21, 'profile_update', 'Profile information updated', '2024-12-27 07:33:23'),
(9, 2, 'profile_update', 'Profile information updated', '2024-12-27 07:33:51'),
(10, 2, 'profile_update', 'Profile information updated', '2024-12-27 07:36:04'),
(11, 21, 'profile_update', 'Profile information updated', '2025-01-07 08:23:07'),
(12, 21, 'profile_update', 'Profile information updated', '2025-01-09 13:46:47'),
(13, 15, 'profile_update', 'Profile information updated', '2025-01-09 13:47:58'),
(14, 15, 'profile_update', 'Profile information updated', '2025-01-10 13:36:13'),
(15, 21, 'profile_update', 'Profile information updated', '2025-01-12 06:54:17'),
(16, 21, 'profile_update', 'Profile information updated', '2025-01-12 07:02:57');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','normal','high') DEFAULT 'normal',
  `display_until` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `content` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `punch_in` datetime DEFAULT NULL,
  `punch_out` datetime DEFAULT NULL,
  `work_report` text DEFAULT NULL,
  `auto_punch_out` tinyint(1) DEFAULT 0,
  `location` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `overtime` int(11) DEFAULT 0,
  `status` enum('present','absent','half-day') DEFAULT 'present',
  `remarks` text DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `working_hours` time DEFAULT NULL,
  `overtime_hours` time DEFAULT NULL,
  `shift_time` time DEFAULT '09:00:00',
  `weekly_offs` varchar(255) DEFAULT NULL,
  `is_weekly_off` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL,
  `attendance_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_groups`
--

CREATE TABLE `chat_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_groups`
--

INSERT INTO `chat_groups` (`id`, `name`, `created_by`, `created_at`) VALUES
(1, 'abc', 21, '2025-01-07 10:06:43'),
(2, 'abc', 21, '2025-01-07 10:09:35'),
(3, 'fdg', 21, '2025-01-07 10:16:30'),
(4, 'yhgtr', 21, '2025-01-07 10:22:28');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_file` tinyint(1) DEFAULT 0,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `sender_id`, `receiver_id`, `message`, `is_read`, `created_at`, `is_file`, `file_name`, `file_path`) VALUES
(1, 21, 15, 'hello', 1, '2025-01-07 09:54:35', 0, NULL, NULL),
(2, 15, 21, 'hii kya haal hai?', 1, '2025-01-07 09:57:01', 0, NULL, NULL),
(3, 21, 15, 'Bhadhiya', 1, '2025-01-07 10:00:22', 0, NULL, NULL),
(4, 15, 21, ':)', 1, '2025-01-07 10:02:19', 0, NULL, NULL),
(5, 21, 15, ':(', 1, '2025-01-07 10:03:12', 0, NULL, NULL),
(6, 15, 21, 'heheheh', 1, '2025-01-07 10:03:30', 0, NULL, NULL),
(7, 21, 15, 'efaef', 1, '2025-01-07 10:03:59', 0, NULL, NULL),
(8, 15, 21, 'awdawdaw', 1, '2025-01-07 10:04:18', 0, NULL, NULL),
(9, 21, 15, 'fdghj', 1, '2025-01-07 10:26:33', 0, NULL, NULL),
(10, 15, 21, 'djhkjlk', 1, '2025-01-07 10:26:52', 0, NULL, NULL),
(11, 21, 15, 'trdghkjlk;', 1, '2025-01-07 10:27:15', 0, NULL, NULL),
(12, 21, 15, 'yea lo', 1, '2025-01-07 11:13:55', 0, NULL, NULL),
(13, 21, 15, 'dfgdfg', 1, '2025-01-07 11:17:04', 0, NULL, NULL),
(14, 21, 15, 'ds', 1, '2025-01-07 11:26:12', 0, NULL, NULL),
(15, 21, 15, 'dtdrg', 1, '2025-01-07 11:26:21', 0, NULL, NULL),
(16, 21, 15, 'szczs', 1, '2025-01-07 11:26:32', 0, NULL, NULL),
(17, 21, 15, 'sfdgn', 1, '2025-01-07 11:27:46', 0, NULL, NULL),
(18, 21, 15, 'sadf', 1, '2025-01-07 11:28:04', 0, NULL, NULL),
(19, 21, 15, 'erty', 1, '2025-01-07 11:30:23', 0, NULL, NULL),
(20, 21, 15, 'sd', 1, '2025-01-07 11:33:13', 0, NULL, NULL),
(21, 21, 15, 'a-hive logo.png', 1, '2025-01-07 11:47:11', 1, 'uploads/chat_files/chat_677d143f003397.86978569.png', 'a-hive logo.png'),
(22, 21, 15, 'fhm,', 1, '2025-01-07 11:47:13', 0, NULL, NULL),
(23, 21, 15, 'a-hive logo.png', 1, '2025-01-07 11:58:53', 1, 'uploads/chat_files/chat_677d16fde1db35.50123834.png', 'a-hive logo.png'),
(24, 21, 15, 'sdfghk', 1, '2025-01-07 11:58:56', 0, NULL, NULL),
(25, 15, 21, 'a-hive logo.png', 1, '2025-01-07 12:15:56', 1, 'uploads/chat_files/chat_677d1afc7909d4.56898719.png', 'a-hive logo.png'),
(26, 15, 21, 'sdfgh', 1, '2025-01-07 12:15:59', 0, NULL, NULL),
(27, 21, 15, 'a-hive logo.png', 1, '2025-01-07 12:20:21', 1, 'uploads/chat_files/chat_677d1c0531ee09.24981759.png', 'a-hive logo.png'),
(28, 21, 15, 'fghj', 1, '2025-01-07 12:20:23', 0, NULL, NULL),
(29, 21, 15, 'or bhai kaam ho gaya', 1, '2025-01-08 13:08:53', 0, NULL, NULL),
(30, 15, 21, 'hola', 1, '2025-01-10 13:30:41', 0, NULL, NULL),
(31, 21, 15, ':)', 1, '2025-01-10 13:30:58', 0, NULL, NULL),
(32, 21, 15, 'hello there', 1, '2025-01-10 15:27:24', 0, NULL, NULL),
(33, 15, 21, 'My Profile Pic.png', 1, '2025-01-10 15:28:16', 1, 'uploads/chat_files/chat_67813c90d7c816.79585610.png', 'My Profile Pic.png'),
(34, 15, 21, 'check this', 1, '2025-01-10 15:28:21', 0, NULL, NULL),
(35, 3, 15, 'hii', 1, '2025-01-13 06:41:03', 0, NULL, NULL),
(36, 3, 15, 'hola', 1, '2025-01-13 11:49:23', 0, NULL, NULL),
(37, 15, 3, 'hehehehe', 1, '2025-01-13 11:49:44', 0, NULL, NULL),
(38, 15, 21, 'ghg', 1, '2025-01-29 13:50:12', 0, NULL, NULL),
(39, 21, 15, 'scsa', 1, '2025-01-29 14:03:25', 0, NULL, NULL),
(40, 15, 21, 'asdsa', 1, '2025-01-31 06:02:05', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `circulars`
--

CREATE TABLE `circulars` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `stage_number` int(11) NOT NULL,
  `substage_number` int(11) DEFAULT NULL,
  `comment_type` enum('stage','substage') NOT NULL,
  `comment_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `construction_sites`
--

CREATE TABLE `construction_sites` (
  `id` int(11) NOT NULL,
  `site_name` varchar(255) NOT NULL,
  `location` text NOT NULL,
  `project_id` int(11) NOT NULL,
  `site_manager_id` int(11) DEFAULT NULL,
  `site_engineer_id` int(11) DEFAULT NULL,
  `supervisor_count` int(11) DEFAULT 0,
  `labor_count` int(11) DEFAULT 0,
  `completion_percentage` decimal(5,2) DEFAULT 0.00,
  `status` enum('planning','active','completed','on_hold') DEFAULT 'planning',
  `start_date` date DEFAULT NULL,
  `expected_completion_date` date DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` enum('individual','group') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversation_participants`
--

CREATE TABLE `conversation_participants` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('member','admin') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entity_status_history`
--

CREATE TABLE `entity_status_history` (
  `id` int(11) NOT NULL,
  `entity_type` enum('task','stage','substage') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by_user_id` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `event_date` date NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `event_type` varchar(50) DEFAULT 'other',
  `created_by` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `file_attachments`
--

CREATE TABLE `file_attachments` (
  `id` int(11) NOT NULL,
  `comment_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `file_comments`
--

CREATE TABLE `file_comments` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `file_status_logs`
--

CREATE TABLE `file_status_logs` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL,
  `action_by` int(11) NOT NULL,
  `action_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forward_tasks`
--

CREATE TABLE `forward_tasks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `substage_id` int(11) DEFAULT NULL,
  `forwarded_by` int(11) NOT NULL,
  `forwarded_to` int(11) NOT NULL,
  `type` enum('stage','substage') NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('admin','member') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_members`
--

INSERT INTO `group_members` (`group_id`, `user_id`, `role`, `joined_at`) VALUES
(1, 7, 'member', '2025-01-07 10:06:43'),
(1, 15, 'member', '2025-01-07 10:06:43'),
(1, 21, 'admin', '2025-01-07 10:06:43'),
(2, 7, 'member', '2025-01-07 10:09:35'),
(2, 15, 'member', '2025-01-07 10:09:35'),
(2, 21, 'admin', '2025-01-07 10:09:35'),
(3, 1, 'member', '2025-01-07 10:16:30'),
(3, 7, 'member', '2025-01-07 10:16:30'),
(3, 15, 'member', '2025-01-07 10:16:30'),
(3, 21, 'admin', '2025-01-07 10:16:30'),
(4, 1, 'member', '2025-01-07 10:22:28'),
(4, 2, 'member', '2025-01-07 10:22:28'),
(4, 3, 'member', '2025-01-07 10:22:28'),
(4, 4, 'member', '2025-01-07 10:22:28'),
(4, 15, 'member', '2025-01-07 10:22:28'),
(4, 21, 'admin', '2025-01-07 10:22:28');

-- --------------------------------------------------------

--
-- Table structure for table `group_messages`
--

CREATE TABLE `group_messages` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_messages`
--

INSERT INTO `group_messages` (`id`, `group_id`, `sender_id`, `message`, `created_at`) VALUES
(1, 4, 21, 'rtyjlk', '2025-01-07 10:46:47'),
(2, 4, 15, 'weartyui', '2025-01-07 10:47:12'),
(3, 4, 3, 'kaddu kuch nahi hota', '2025-01-13 06:41:50');

-- --------------------------------------------------------

--
-- Table structure for table `group_message_status`
--

CREATE TABLE `group_message_status` (
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `holiday_date` date NOT NULL,
  `holiday_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leaves`
--

CREATE TABLE `leaves` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `half_day` tinyint(1) DEFAULT 0,
  `status` varchar(20) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_balance`
--

CREATE TABLE `leave_balance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` year(4) NOT NULL,
  `total_leaves` decimal(5,1) NOT NULL,
  `used_leaves` decimal(5,1) DEFAULT 0.0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `month` int(2) NOT NULL,
  `year` int(4) NOT NULL,
  `casual_leaves` decimal(4,1) NOT NULL DEFAULT 2.0,
  `medical_leaves` decimal(4,1) NOT NULL DEFAULT 6.0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_history`
--

CREATE TABLE `leave_history` (
  `id` int(11) NOT NULL,
  `leave_id` int(11) NOT NULL,
  `status_from` enum('pending','approved','rejected') DEFAULT NULL,
  `status_to` enum('pending','approved','rejected') DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_request`
--

CREATE TABLE `leave_request` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text NOT NULL,
  `duration` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `action_reason` text DEFAULT NULL,
  `action_by` int(11) DEFAULT NULL,
  `action_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `action_comments` text DEFAULT NULL,
  `manager_approval` enum('approved','rejected') DEFAULT NULL,
  `manager_action_reason` text DEFAULT NULL,
  `manager_action_by` int(11) DEFAULT NULL,
  `manager_action_at` datetime DEFAULT NULL,
  `hr_approval` enum('approved','rejected') DEFAULT NULL,
  `hr_action_reason` text DEFAULT NULL,
  `hr_action_by` int(11) DEFAULT NULL,
  `hr_action_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `max_days` int(11) NOT NULL,
  `carry_forward` tinyint(1) DEFAULT 0,
  `paid` tinyint(1) DEFAULT 1,
  `color_code` varchar(7) DEFAULT '#000000',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `name`, `description`, `max_days`, `carry_forward`, `paid`, `color_code`, `status`, `created_by`, `created_at`, `modified_at`) VALUES
(2, 'Sick Leave', 'Medical and health related leave (6 days per month)', 6, 0, 1, '#F44336', 'active', NULL, '2024-12-16 12:59:41', '2025-01-10 14:37:12'),
(3, 'Casual Leave', 'Short notice personal leave (12 days per year)', 12, 0, 1, '#4CAF50', 'active', NULL, '2024-12-16 12:59:41', '2025-01-10 14:37:12'),
(4, 'Emergency Leave', 'Urgent personal matters', 3, 0, 1, '#FF9800', 'active', NULL, '2024-12-16 12:59:41', '2025-01-10 14:37:12'),
(5, 'Maternity Leave', 'Leave for expecting mothers (60 days)', 60, 0, 1, '#E91E63', 'active', NULL, '2024-12-16 12:59:41', '2025-01-10 14:37:12'),
(6, 'Paternity Leave', 'Leave for new fathers (7 days)', 7, 0, 1, '#9C27B0', 'active', NULL, '2024-12-16 12:59:41', '2025-01-10 14:37:12'),
(11, 'Short Leave', 'Short duration leave (2 days per month)', 2, 0, 1, '#2196F3', 'active', NULL, '2025-01-10 14:25:28', '2025-01-10 14:37:12'),
(12, 'Compensate Leave', 'Leave in lieu of working on holidays/weekends', 0, 0, 1, '#607D8B', 'active', NULL, '2025-01-10 14:25:28', '2025-01-10 14:37:12');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message_type` enum('text','image','file') DEFAULT 'text',
  `content` text NOT NULL,
  `file_url` varchar(255) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `edited_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `original_filename` varchar(255) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_status`
--

CREATE TABLE `message_status` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('delivered','read') DEFAULT 'delivered',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `reference_id`, `message`, `status`, `created_at`) VALUES
(1, 15, 'stage_forward', 27, 'A stage has been forwarded to you', NULL, '2025-01-10 13:14:00'),
(2, 21, 'stage_forward', 27, 'A stage has been forwarded to you', NULL, '2025-01-10 13:14:37'),
(3, 1, 'stage_forward', 29, 'A stage has been forwarded to you', NULL, '2025-01-10 15:25:49'),
(4, 3, 'leave_action', 14, 'Your leave request from 23 Jan 2025 to 25 Jan 2025 has been approved by the manager.', 'unread', '2025-01-23 10:13:32'),
(5, 3, 'leave_action', 15, 'Your leave request from 23 Jan 2025 to 24 Jan 2025 has been rejected by the manager.', 'unread', '2025-01-23 10:42:18'),
(6, 21, 'leave_action', 16, 'Your leave request from 30 Jan 2025 to 01 Feb 2025 has been approved by the manager.', 'unread', '2025-02-03 12:48:59'),
(7, 3, 'leave_action', 17, 'Your leave request from 03 Feb 2025 to 06 Feb 2025 has been approved by the manager.', 'unread', '2025-02-03 12:54:10'),
(8, 15, 'leave_action', 18, 'Your leave request from 03 Feb 2025 to 06 Feb 2025 has been approved by the manager.', 'unread', '2025-02-04 05:42:01'),
(9, 21, 'leave_action', 19, 'Your leave request from 04 Feb 2025 to 06 Feb 2025 has been approved by the manager.', 'unread', '2025-02-04 05:44:01'),
(10, 3, 'leave_action', 20, 'Your leave request from 04 Feb 2025 to 06 Feb 2025 has been approved by the manager.', 'unread', '2025-02-04 05:46:20'),
(11, 3, 'leave_action', 21, 'Your leave request from 06 Feb 2025 to 06 Feb 2025 has been rejected by the manager.', 'unread', '2025-02-04 05:47:07'),
(12, 3, 'leave_action', 22, 'Your leave request from 04 Feb 2025 to 05 Feb 2025 has been approved by the manager.', 'unread', '2025-02-04 05:55:29'),
(13, 3, 'leave_action', 23, 'Your leave request from 04 Feb 2025 to 06 Feb 2025 has been approved by the manager.', 'unread', '2025-02-04 06:05:48'),
(14, 15, 'leave_action', 25, 'Your leave request from 05 Feb 2025 to 06 Feb 2025 has been approved by the manager.', 'unread', '2025-02-05 13:10:00');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `project_type` enum('architecture','interior','construction') NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `created_by` int(11) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `status` enum('not_started','pending','in_progress','in_review','completed','on_hold','cancelled','blocked') NOT NULL DEFAULT 'not_started',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `title`, `description`, `project_type`, `category_id`, `start_date`, `end_date`, `created_by`, `assigned_to`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(13, 'Test', 'Testing', 'architecture', 9, '2025-02-06 12:30:00', '2025-02-07 12:30:00', 3, 15, 'not_started', '2025-02-06 12:32:08', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `project_activity_log`
--

CREATE TABLE `project_activity_log` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `substage_id` int(11) DEFAULT NULL,
  `activity_type` enum('file_upload','comment_added','member_added','member_removed','deadline_changed','description_updated','other') NOT NULL,
  `description` text NOT NULL,
  `performed_by` int(11) NOT NULL,
  `performed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_activity_log`
--

INSERT INTO `project_activity_log` (`id`, `project_id`, `stage_id`, `substage_id`, `activity_type`, `description`, `performed_by`, `performed_at`) VALUES
(141, 13, NULL, NULL, 'other', 'Project created with 2 stages', 3, '2025-02-06 12:32:08'),
(142, 13, 21, NULL, 'other', 'Stage created with 2 substages', 3, '2025-02-06 12:32:08'),
(143, 13, 21, 28, 'other', 'Substage created', 3, '2025-02-06 12:32:08'),
(144, 13, 21, 29, 'other', 'Substage created', 3, '2025-02-06 12:32:08'),
(145, 13, 22, NULL, 'other', 'Stage created with 2 substages', 3, '2025-02-06 12:32:08'),
(146, 13, 22, 30, 'other', 'Substage created', 3, '2025-02-06 12:32:08'),
(147, 13, 22, 31, 'other', 'Substage created', 3, '2025-02-06 12:32:08');

-- --------------------------------------------------------

--
-- Table structure for table `project_categories`
--

CREATE TABLE `project_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_categories`
--

INSERT INTO `project_categories` (`id`, `name`, `description`, `created_at`, `updated_at`, `deleted_at`, `parent_id`) VALUES
(1, 'Architecture', 'Projects related to architectural design and planning', '2025-01-25 08:02:49', '2025-01-25 08:02:49', NULL, NULL),
(2, 'Interior', 'Interior design and decoration projects', '2025-01-25 08:02:49', '2025-01-25 08:02:49', NULL, NULL),
(3, 'Construction', 'Building and construction management projects', '2025-01-25 08:02:49', '2025-01-25 08:02:49', NULL, NULL),
(4, 'Residential Architecture', 'Single family homes, apartments, and residential complexes', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 1),
(5, 'Commercial Architecture', 'Office buildings, retail spaces, and commercial complexes', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 1),
(6, 'Institutional Architecture', 'Schools, hospitals, and public buildings', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 1),
(7, 'Industrial Architecture', 'Factories, warehouses, and industrial facilities', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 1),
(8, 'Landscape Architecture', 'Parks, gardens, and outdoor spaces', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 1),
(9, 'Residential Interior', 'Home interior design and decoration', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 2),
(10, 'Commercial Interior', 'Office and retail space interior design', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 2),
(11, 'Hospitality Interior', 'Hotels, restaurants, and entertainment venues', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 2),
(12, 'Corporate Interior', 'Corporate office and workplace design', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 2),
(13, 'Retail Interior', 'Retail stores and showroom design', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 2),
(14, 'Residential Construction', 'House building and residential development', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 3),
(15, 'Commercial Construction', 'Office and retail building construction', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 3),
(16, 'Industrial Construction', 'Factory and warehouse construction', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 3),
(17, 'Infrastructure Construction', 'Roads, bridges, and public infrastructure', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 3),
(18, 'Renovation Projects', 'Building renovation and remodeling', '2025-01-25 08:02:49', '2025-01-25 08:02:57', NULL, 3);

-- --------------------------------------------------------

--
-- Table structure for table `project_files`
--

CREATE TABLE `project_files` (
  `id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `substage_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_history`
--

CREATE TABLE `project_history` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `action_type` enum('created','updated','deleted','status_changed') NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_history`
--

INSERT INTO `project_history` (`id`, `project_id`, `action_type`, `old_value`, `new_value`, `changed_by`, `changed_at`) VALUES
(11, 13, 'created', NULL, '{\"title\":\"Test\",\"description\":\"Testing\",\"projectType\":\"architecture\",\"category\":\"9\",\"startDate\":\"2025-02-06T12:30\",\"dueDate\":\"2025-02-07T12:30\",\"assignee\":\"15\",\"stages\":[{\"description\":\"\",\"assignee\":\"21\",\"startDate\":\"2025-02-06T12:30\",\"dueDate\":\"2025-02-09T12:30\",\"substages\":[{\"title\":\"a\",\"assignee\":\"21\",\"startDate\":\"2025-02-06T12:31\",\"dueDate\":\"2025-02-09T12:31\"},{\"title\":\"b\",\"assignee\":\"21\",\"startDate\":\"2025-02-06T12:31\",\"dueDate\":\"2025-02-09T12:31\"}]},{\"description\":\"\",\"assignee\":\"21\",\"startDate\":\"2025-02-06T12:31\",\"dueDate\":\"2025-02-09T12:31\",\"substages\":[{\"title\":\"c\",\"assignee\":\"21\",\"startDate\":\"2025-02-06T12:31\",\"dueDate\":\"2025-02-09T12:31\"},{\"title\":\"d\",\"assignee\":\"21\",\"startDate\":\"2025-02-06T12:31\",\"dueDate\":\"2025-02-09T12:31\"}]}]}', 3, '2025-02-06 12:32:08');

-- --------------------------------------------------------

--
-- Table structure for table `project_stages`
--

CREATE TABLE `project_stages` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `stage_number` int(11) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('not_started','pending','in_progress','in_review','completed','on_hold','cancelled','blocked') NOT NULL DEFAULT 'not_started',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_stages`
--

INSERT INTO `project_stages` (`id`, `project_id`, `stage_number`, `assigned_to`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(21, 13, 1, 21, '2025-02-06 12:30:00', '2025-02-09 12:30:00', 'not_started', '2025-02-06 12:32:08', NULL, NULL),
(22, 13, 2, 21, '2025-02-06 12:31:00', '2025-02-09 12:31:00', 'not_started', '2025-02-06 12:32:08', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `project_status_history`
--

CREATE TABLE `project_status_history` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `substage_id` int(11) DEFAULT NULL,
  `old_status` enum('pending','in_progress','completed','on_hold','cancelled') NOT NULL,
  `new_status` enum('pending','in_progress','completed','on_hold','cancelled') NOT NULL,
  `remarks` text DEFAULT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_status_history`
--

INSERT INTO `project_status_history` (`id`, `project_id`, `stage_id`, `substage_id`, `old_status`, `new_status`, `remarks`, `changed_by`, `changed_at`) VALUES
(11, 13, NULL, NULL, '', 'pending', 'Project created', 3, '2025-02-06 12:32:08');

-- --------------------------------------------------------

--
-- Table structure for table `project_substages`
--

CREATE TABLE `project_substages` (
  `id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `substage_number` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('not_started','pending','in_progress','in_review','completed','on_hold','cancelled','blocked') NOT NULL DEFAULT 'not_started',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `substage_identifier` varchar(50) GENERATED ALWAYS AS (concat(`stage_id`,'_',`substage_number`)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_substages`
--

INSERT INTO `project_substages` (`id`, `stage_id`, `substage_number`, `title`, `assigned_to`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(28, 21, 1, 'a', 21, '2025-02-06 12:31:00', '2025-02-09 12:31:00', 'not_started', '2025-02-06 12:32:08', NULL, NULL),
(29, 21, 2, 'b', 21, '2025-02-06 12:31:00', '2025-02-09 12:31:00', 'not_started', '2025-02-06 12:32:08', NULL, NULL),
(30, 22, 1, 'c', 21, '2025-02-06 12:31:00', '2025-02-09 12:31:00', 'not_started', '2025-02-06 12:32:08', NULL, NULL),
(31, 22, 2, 'd', 21, '2025-02-06 12:31:00', '2025-02-09 12:31:00', 'not_started', '2025-02-06 12:32:08', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('manager','employee') DEFAULT NULL,
  `request_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resumes`
--

CREATE TABLE `resumes` (
  `id` int(11) NOT NULL,
  `candidate_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `position_applied` varchar(100) DEFAULT NULL,
  `resume_path` varchar(255) DEFAULT NULL,
  `status` enum('new','reviewing','shortlisted','rejected') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_details`
--

CREATE TABLE `salary_details` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `month_year` date NOT NULL,
  `total_working_days` int(11) DEFAULT 0,
  `present_days` int(11) DEFAULT 0,
  `leave_taken` int(11) DEFAULT 0,
  `short_leave` int(11) DEFAULT 0,
  `late_count` int(11) DEFAULT 0,
  `overtime_hours` decimal(10,2) DEFAULT 0.00,
  `travel_expenses` decimal(10,2) DEFAULT 0.00,
  `salary_amount` decimal(10,2) DEFAULT 0.00,
  `overtime_amount` decimal(10,2) DEFAULT 0.00,
  `travel_amount` decimal(10,2) DEFAULT 0.00,
  `misc_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','approved') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `salary_details`
--

INSERT INTO `salary_details` (`id`, `user_id`, `month_year`, `total_working_days`, `present_days`, `leave_taken`, `short_leave`, `late_count`, `overtime_hours`, `travel_expenses`, `salary_amount`, `overtime_amount`, `travel_amount`, `misc_amount`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:31', NULL),
(2, 2, '2025-01-01', 27, 5, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:31', '2025-01-16 14:07:09'),
(3, 3, '2025-01-01', 27, 6, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:31', '2025-01-16 14:07:09'),
(4, 4, '2025-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:31', NULL),
(5, 7, '2025-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:31', NULL),
(6, 8, '2025-01-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:31', NULL),
(7, 11, '2025-01-01', 27, 1, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:31', '2025-01-16 14:07:09'),
(8, 15, '2025-01-01', 27, 9, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:31', '2025-01-16 14:21:39'),
(9, 21, '2025-01-01', 27, 9, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:31', '2025-01-17 05:25:09'),
(16, 1, '2025-02-01', 24, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:38', NULL),
(17, 2, '2025-02-01', 24, 1, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:38', '2025-02-05 11:41:15'),
(18, 3, '2025-02-01', 24, 1, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:38', '2025-02-05 11:41:15'),
(19, 4, '2025-02-01', 24, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:38', NULL),
(20, 7, '2025-02-01', 24, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:38', NULL),
(21, 8, '2025-02-01', 24, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:38', NULL),
(22, 11, '2025-02-01', 24, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:38', NULL),
(23, 15, '2025-02-01', 24, 4, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:38', '2025-02-05 11:41:15'),
(24, 21, '2025-02-01', 24, 2, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:55:38', '2025-02-05 11:41:15'),
(38, 1, '2025-03-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:59:02', NULL),
(39, 2, '2025-03-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:59:02', NULL),
(40, 3, '2025-03-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:59:02', NULL),
(41, 4, '2025-03-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:59:02', NULL),
(42, 7, '2025-03-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:59:02', NULL),
(43, 8, '2025-03-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:59:02', NULL),
(44, 11, '2025-03-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:59:02', NULL),
(45, 15, '2025-03-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:59:02', NULL),
(46, 21, '2025-03-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 13:59:02', NULL),
(68, 1, '2025-04-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(69, 2, '2025-04-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(70, 3, '2025-04-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(71, 4, '2025-04-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(72, 7, '2025-04-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(73, 8, '2025-04-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(74, 11, '2025-04-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(75, 15, '2025-04-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(76, 21, '2025-04-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(84, 1, '2025-05-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(85, 2, '2025-05-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(86, 3, '2025-05-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(87, 4, '2025-05-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(88, 7, '2025-05-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(89, 8, '2025-05-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(90, 11, '2025-05-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(91, 15, '2025-05-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(92, 21, '2025-05-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:48', NULL),
(100, 1, '2025-06-01', 25, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:51', NULL),
(101, 2, '2025-06-01', 25, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:51', NULL),
(102, 3, '2025-06-01', 25, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:51', NULL),
(103, 4, '2025-06-01', 25, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:51', NULL),
(104, 7, '2025-06-01', 25, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:51', NULL),
(105, 8, '2025-06-01', 25, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:51', NULL),
(106, 11, '2025-06-01', 25, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:51', NULL),
(107, 15, '2025-06-01', 25, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:51', NULL),
(108, 21, '2025-06-01', 25, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:51', NULL),
(116, 1, '2025-07-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:52', NULL),
(117, 2, '2025-07-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:52', NULL),
(118, 3, '2025-07-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:52', NULL),
(119, 4, '2025-07-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:52', NULL),
(120, 7, '2025-07-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:52', NULL),
(121, 8, '2025-07-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:52', NULL),
(122, 11, '2025-07-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:52', NULL),
(123, 15, '2025-07-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:52', NULL),
(124, 21, '2025-07-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:00:52', NULL),
(470, 1, '2024-12-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:41:36', NULL),
(471, 2, '2024-12-01', 26, 2, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:41:36', '2025-01-16 14:41:36'),
(472, 3, '2024-12-01', 26, 7, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:41:36', '2025-01-16 14:41:36'),
(473, 4, '2024-12-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:41:36', NULL),
(474, 7, '2024-12-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:41:36', NULL),
(475, 8, '2024-12-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:41:36', NULL),
(476, 11, '2024-12-01', 26, 1, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:41:36', '2025-01-16 14:41:36'),
(477, 15, '2024-12-01', 26, 5, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:41:36', '2025-01-16 14:41:36'),
(478, 21, '2024-12-01', 26, 8, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-01-16 14:41:36', '2025-01-16 14:41:36'),
(631, 24, '2025-02-01', 24, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:41:15', '2025-02-05 11:41:15'),
(635, 22, '2025-02-01', 24, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:41:15', '2025-02-05 11:41:15'),
(636, 23, '2025-02-01', 24, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:41:15', '2025-02-05 11:41:15'),
(637, 25, '2025-02-01', 24, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:41:15', '2025-02-05 11:41:15'),
(640, 26, '2025-02-01', 24, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:41:15', '2025-02-05 11:41:15'),
(706, 22, '2025-03-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:54:06', NULL),
(707, 23, '2025-03-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:54:06', NULL),
(708, 24, '2025-03-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:54:06', NULL),
(709, 25, '2025-03-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:54:06', NULL),
(710, 26, '2025-03-01', 26, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:54:06', NULL),
(728, 24, '2025-07-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:54:11', '2025-02-05 11:54:11'),
(732, 22, '2025-07-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:54:11', NULL),
(733, 23, '2025-07-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:54:11', NULL),
(734, 25, '2025-07-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:54:11', NULL),
(735, 26, '2025-07-01', 27, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending', '2025-02-05 11:54:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `salary_history`
--

CREATE TABLE `salary_history` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `previous_salary` decimal(10,2) DEFAULT NULL,
  `new_salary` decimal(10,2) DEFAULT NULL,
  `previous_allowances` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_allowances`)),
  `new_allowances` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_allowances`)),
  `previous_deductions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_deductions`)),
  `new_deductions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_deductions`)),
  `effective_date` date DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_records`
--

CREATE TABLE `salary_records` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `month` date DEFAULT NULL,
  `working_days` int(11) DEFAULT NULL,
  `present_days` int(11) DEFAULT NULL,
  `leave_taken` int(11) DEFAULT NULL,
  `short_leave` int(11) DEFAULT NULL,
  `late_count` int(11) DEFAULT NULL,
  `overtime_hours` decimal(5,2) DEFAULT NULL,
  `earned_salary` decimal(10,2) DEFAULT NULL,
  `overtime_amount` decimal(10,2) DEFAULT NULL,
  `travel_amount` decimal(10,2) DEFAULT NULL,
  `misc_amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_structures`
--

CREATE TABLE `salary_structures` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `basic_salary` decimal(10,2) DEFAULT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `shift_name` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`id`, `shift_name`, `start_time`, `end_time`, `created_at`, `updated_at`) VALUES
(4, 'Winter Morning Shift', '09:00:00', '18:00:00', '2024-12-27 08:29:43', '2024-12-27 08:29:43'),
(5, '9 to 6 Mon', '09:00:00', '18:00:00', '2025-02-05 07:55:05', '2025-02-05 07:55:05'),
(6, '9 to 6 Tue', '09:00:00', '18:00:00', '2025-02-05 07:55:29', '2025-02-05 07:55:29'),
(7, '9 to 6 Wed', '09:00:00', '18:00:00', '2025-02-05 07:55:48', '2025-02-05 07:55:48'),
(8, '9 to 6 Thur', '09:00:00', '18:00:00', '2025-02-05 07:56:10', '2025-02-05 07:56:10'),
(9, '9 to 6 Fri', '09:00:00', '18:00:00', '2025-02-05 07:56:34', '2025-02-05 07:56:34'),
(10, '9 to 6 Sat', '09:00:00', '18:00:00', '2025-02-05 07:56:58', '2025-02-05 07:56:58'),
(11, '9 to 6 Sun', '09:00:00', '18:00:00', '2025-02-05 07:57:13', '2025-02-05 07:57:13'),
(12, '9 to 6 Wed', '09:00:00', '17:00:00', '2025-02-05 07:57:47', '2025-02-05 07:57:47');

-- --------------------------------------------------------

--
-- Table structure for table `site_attendance`
--

CREATE TABLE `site_attendance` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `overtime_hours` decimal(4,2) DEFAULT 0.00,
  `status` enum('present','absent','half_day') DEFAULT 'present',
  `location` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_progress`
--

CREATE TABLE `site_progress` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `progress_percentage` decimal(5,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `challenges` text DEFAULT NULL,
  `next_steps` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stage_files`
--

CREATE TABLE `stage_files` (
  `id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `task_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `substage_comments`
--

CREATE TABLE `substage_comments` (
  `id` int(11) NOT NULL,
  `substage_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `substage_files`
--

CREATE TABLE `substage_files` (
  `id` int(11) NOT NULL,
  `substage_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime NOT NULL,
  `status` enum('pending','sent_for_approval','approved','rejected') DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `last_modified_at` datetime DEFAULT NULL,
  `last_modified_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `status` enum('not_started','pending','in_progress','in_review','completed','on_hold','cancelled','blocked') NOT NULL DEFAULT 'not_started',
  `due_date` date NOT NULL,
  `priority_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `task_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `title`, `description`, `created_by`, `category_id`, `priority`, `status`, `due_date`, `priority_id`, `status_id`, `created_at`, `updated_at`, `task_type`) VALUES
(69, 'Project At Wave City', 'Project At Ghaziabad', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-20 13:18:37', '2025-01-20 13:18:37', 'architecture'),
(70, 'dsfsdf', 'sdfsd', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-22 10:38:23', '2025-01-22 10:38:23', 'architecture'),
(71, 'awdaw', 'wdawdawd', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-22 10:43:59', '2025-01-22 10:43:59', 'architecture'),
(81, 'awdaw', 'fsfd', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-24 11:58:04', '2025-01-24 11:58:04', NULL),
(82, 'test', 'tetet', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-24 12:03:08', '2025-01-24 12:03:08', NULL),
(83, 'sadas', 'sadasd', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-24 12:05:12', '2025-01-24 12:05:12', NULL),
(84, 'sadas', 'sadasd', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-24 12:07:46', '2025-01-24 12:07:46', NULL),
(85, 'sada', 'iesfie', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-24 12:09:32', '2025-01-24 12:09:32', NULL),
(86, 'sada', 'iesfie', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-24 12:09:38', '2025-01-24 12:09:38', NULL),
(87, 'hello', 'ueesfiusf', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-24 12:34:12', '2025-01-24 12:34:12', NULL),
(88, 'test', 'testing hsh', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-24 12:46:20', '2025-01-24 12:46:20', NULL),
(89, 'testtstts', 'dasddsad', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-24 12:52:04', '2025-01-24 12:52:04', NULL),
(90, 'testtstts', 'dasddsad', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-24 12:56:10', '2025-01-24 12:56:10', NULL),
(91, 'aSA', 'SADAS', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-24 12:57:05', '2025-01-24 12:57:05', NULL),
(92, 'awdw', 'dawdw', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-24 13:03:28', '2025-01-24 13:03:28', NULL),
(93, 'testing 1', 'hello there', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-24 13:20:32', '2025-01-24 13:20:32', 'Construction'),
(94, 'real testing', 'testing', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-25 05:53:05', '2025-01-25 05:53:05', 'construction'),
(95, 'aditya', 'aditya', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-25 06:10:07', '2025-01-25 06:10:07', NULL),
(96, 'aditya', 'aditya', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-25 06:14:46', '2025-01-25 06:14:46', NULL),
(97, 'aditya', 'aditya', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-25 06:21:28', '2025-01-25 06:21:28', NULL),
(98, 'aditya', 'aditya', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-25 06:27:31', '2025-01-25 06:27:31', NULL),
(99, 'aditya ', 'aditya', 3, 1, 'medium', 'pending', '2025-01-26', 0, 1, '2025-01-25 06:36:56', '2025-01-25 06:36:56', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `task_categories`
--

CREATE TABLE `task_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_categories`
--

INSERT INTO `task_categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Design', 'Design related tasks', '2024-12-18 07:49:51', '2024-12-18 07:49:51'),
(2, 'Development', 'Development and programming tasks', '2024-12-18 07:49:51', '2024-12-18 07:49:51'),
(3, 'Testing', 'Quality assurance and testing tasks', '2024-12-18 07:49:51', '2024-12-18 07:49:51'),
(4, 'Documentation', 'Documentation and reporting tasks', '2024-12-18 07:49:51', '2024-12-18 07:49:51'),
(5, 'Meeting', 'Team meetings and client calls', '2024-12-18 07:49:51', '2024-12-18 07:49:51'),
(6, 'Research', 'Research and analysis tasks', '2024-12-18 07:49:51', '2024-12-18 07:49:51'),
(7, 'Training', 'Training and learning activities', '2024-12-18 07:49:51', '2024-12-18 07:49:51'),
(8, 'Other', 'Miscellaneous tasks', '2024-12-18 07:49:51', '2024-12-18 07:49:51'),
(9, 'General', 'Default category for tasks', '2024-12-21 06:03:34', '2024-12-21 06:03:34'),
(10, 'General', 'Default category for tasks', '2024-12-21 06:04:07', '2024-12-21 06:04:07');

-- --------------------------------------------------------

--
-- Table structure for table `task_comments`
--

CREATE TABLE `task_comments` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `stage_number` int(11) DEFAULT NULL,
  `substage_id` int(11) DEFAULT NULL,
  `comment_text` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_forwarding_history`
--

CREATE TABLE `task_forwarding_history` (
  `id` int(11) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `forwarded_by` int(11) NOT NULL,
  `forwarded_to` int(11) NOT NULL,
  `forwarded_at` datetime NOT NULL,
  `previous_status` varchar(50) NOT NULL,
  `message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_priorities`
--

CREATE TABLE `task_priorities` (
  `id` int(11) NOT NULL,
  `priority_name` varchar(50) NOT NULL,
  `priority_color` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_priorities`
--

INSERT INTO `task_priorities` (`id`, `priority_name`, `priority_color`, `created_at`) VALUES
(1, 'High', '#e74c3c', '2024-12-19 12:05:21'),
(2, 'Medium', '#f1c40f', '2024-12-19 12:05:21'),
(3, 'Low', '#2ecc71', '2024-12-19 12:05:21'),
(4, 'High', '#e74c3c', '2024-12-19 12:08:57'),
(5, 'Medium', '#f1c40f', '2024-12-19 12:08:57'),
(6, 'Low', '#2ecc71', '2024-12-19 12:08:57'),
(7, 'High', '#e74c3c', '2024-12-19 12:10:26'),
(8, 'Medium', '#f1c40f', '2024-12-19 12:10:26'),
(9, 'Low', '#2ecc71', '2024-12-19 12:10:26'),
(10, 'High', '#ff4444', '2024-12-23 07:13:31'),
(11, 'Medium', '#ffbb33', '2024-12-23 07:13:31'),
(12, 'Low', '#00C851', '2024-12-23 07:13:31'),
(13, 'High', '#ff4444', '2024-12-23 07:14:50'),
(14, 'Medium', '#ffbb33', '2024-12-23 07:14:50'),
(15, 'Low', '#00C851', '2024-12-23 07:14:50');

-- --------------------------------------------------------

--
-- Table structure for table `task_stages`
--

CREATE TABLE `task_stages` (
  `id` int(11) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `stage_number` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('not_started','in_progress','completed','delayed','on_hold','freezed','for_approval','pending') DEFAULT 'not_started',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `start_date` datetime DEFAULT NULL,
  `assignee_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_status`
--

CREATE TABLE `task_status` (
  `id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `status_color` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_status`
--

INSERT INTO `task_status` (`id`, `status_name`, `status_color`, `created_at`) VALUES
(1, 'Pending', '#95a5a6', '2024-12-19 12:05:49'),
(2, 'In Progress', '#3498db', '2024-12-19 12:05:49'),
(3, 'Completed', '#2ecc71', '2024-12-19 12:05:49'),
(4, 'On Hold', '#e67e22', '2024-12-19 12:05:49'),
(5, 'Cancelled', '#e74c3c', '2024-12-19 12:05:49'),
(6, 'Pending', '#95a5a6', '2024-12-19 12:10:33'),
(7, 'In Progress', '#3498db', '2024-12-19 12:10:33'),
(8, 'Completed', '#2ecc71', '2024-12-19 12:10:33'),
(9, 'On Hold', '#e67e22', '2024-12-19 12:10:33'),
(10, 'Cancelled', '#e74c3c', '2024-12-19 12:10:33'),
(11, 'Pending', '#ffbb33', '2024-12-23 07:13:31'),
(12, 'In Progress', '#33b5e5', '2024-12-23 07:13:31'),
(13, 'Completed', '#00C851', '2024-12-23 07:13:31'),
(14, 'Pending', '#ffbb33', '2024-12-23 07:14:50'),
(15, 'In Progress', '#33b5e5', '2024-12-23 07:14:50'),
(16, 'Completed', '#00C851', '2024-12-23 07:14:50');

-- --------------------------------------------------------

--
-- Table structure for table `task_status_history`
--

CREATE TABLE `task_status_history` (
  `id` int(11) NOT NULL,
  `entity_type` enum('stage','substage') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `task_id` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_status_history`
--

INSERT INTO `task_status_history` (`id`, `entity_type`, `entity_id`, `old_status`, `new_status`, `changed_by`, `changed_at`, `task_id`, `comment`, `file_path`) VALUES
(215, 'substage', 87, 'comment', 'comment', 15, '2025-01-20 13:35:21', 69, 'this is the site recce txt file', '678e51193b2c7_1737380121.txt'),
(216, 'substage', 87, 'pending', 'in_progress', 15, '2025-01-20 13:35:59', 69, NULL, NULL),
(217, 'substage', 87, 'in_progress', 'completed', 15, '2025-01-20 13:48:44', 69, NULL, NULL),
(218, 'substage', 88, 'pending', 'Forwarded to 0', 15, '2025-01-20 13:56:32', 69, NULL, NULL),
(219, 'substage', 88, 'Forwarded to Aditya Kumar Pal', 'pending', 15, '2025-01-20 13:56:32', 69, NULL, NULL),
(220, 'substage', 88, 'comment', 'comment', 15, '2025-01-20 13:58:47', 69, 'uploaded concept ppt', '678e569756abf_1737381527.pptx'),
(221, 'substage', 87, 'comment', 'comment', 15, '2025-01-21 08:11:54', 69, 'dwawda', '678f56ca82d98_1737447114.pdf'),
(222, 'substage', 87, 'comment', 'comment', 15, '2025-01-21 08:14:16', 69, 'sdad', '678f57582c16f_1737447256.jpg'),
(223, 'substage', 88, 'pending', 'completed', 15, '2025-01-21 08:53:48', 69, NULL, NULL),
(224, 'substage', 88, 'comment', 'comment', 15, '2025-01-21 08:54:32', 69, 'sdasd', '678f60c8ccc45_1737449672.pdf'),
(225, 'substage', 87, 'PENDING', 'PENDING', 15, '2025-01-21 09:31:32', NULL, NULL, 'uploads/substage_files/678f6974055a4_1737451892.jpg'),
(226, 'substage', 87, 'comment', 'comment', 15, '2025-01-21 14:49:42', 69, 'jbnjknkjnjkb', NULL),
(227, 'substage', 87, 'comment', 'comment', 15, '2025-01-21 14:49:56', 69, 'knlknlkn', NULL),
(228, 'substage', 89, 'pending', 'in_progress', 15, '2025-01-22 13:44:02', 69, NULL, NULL),
(229, 'substage', 89, 'in_progress', 'not_started', 15, '2025-01-22 13:45:26', 69, NULL, NULL),
(230, 'substage', 88, 'completed', 'pending', 15, '2025-01-22 13:48:00', 69, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `task_substages`
--

CREATE TABLE `task_substages` (
  `id` int(11) NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('not_started','in_progress','completed','delayed','on_hold','freezed','for_approval','pending') DEFAULT 'not_started',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `assignee_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `travel_expenses`
--

CREATE TABLE `travel_expenses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `expense_date` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `approved_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `designation` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `unique_id` varchar(10) NOT NULL,
  `reporting_manager` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `last_login` datetime DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `marital_status` varchar(50) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `languages` text DEFAULT NULL,
  `social_media` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`social_media`)),
  `skills` text DEFAULT NULL,
  `interests` text DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `education` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`education`)),
  `work_experience` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`work_experience`)),
  `bank_details` text DEFAULT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `documents` text DEFAULT NULL,
  `work_experiences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`work_experiences`)),
  `education_background` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`education_background`)),
  `base_salary` decimal(10,2) DEFAULT 0.00,
  `allowances` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowances`)),
  `deductions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`deductions`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `position`, `email`, `employee_id`, `phone_number`, `designation`, `department`, `password`, `role`, `unique_id`, `reporting_manager`, `created_at`, `updated_at`, `deleted_at`, `status`, `last_login`, `profile_image`, `address`, `emergency_contact`, `joining_date`, `modified_at`, `city`, `state`, `country`, `postal_code`, `emergency_contact_name`, `emergency_contact_phone`, `phone`, `dob`, `profile_picture`, `bio`, `gender`, `marital_status`, `nationality`, `languages`, `social_media`, `skills`, `interests`, `blood_group`, `education`, `work_experience`, `bank_details`, `shift_id`, `documents`, `work_experiences`, `education_background`, `base_salary`, `allowances`, `deductions`) VALUES
(1, 'Aditya Pal', 'Principal Architect', 'aaadityapal69@gmail.com', 'EMP0001', NULL, 'IT Support', NULL, '$2y$10$TkB2JPXzRLntod.z0/118ecgqbgYey8Vy7zbJb0ityfZJ8/27yiU.', 'admin', 'ADM001', 'Sr. Manager (Studio)', '2024-12-16 12:38:03', '2025-01-11 13:06:27', NULL, 'Active', '2024-12-27 12:33:00', NULL, 'dsa', NULL, '2025-01-11', '2025-01-11 13:06:27', '', '', '', '', '', '', '7224864553', '2001-10-24', NULL, NULL, 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"account_holder\":\"\",\"bank_name\":\"\",\"account_number\":\"\",\"ifsc_code\":\"\",\"branch_name\":\"\",\"account_type\":\"savings\"}', NULL, '{\"aadhar\":{\"filename\":\"1_aadhar_67826cd3662c5.pdf\",\"original_name\":\"RACHANA PAL_CV (1).pdf\",\"uploaded_at\":\"2025-01-11 18:36:27\"}}', NULL, NULL, 0.00, NULL, NULL),
(2, 'Gunjan Sehgal', 'HR', 'hr.architectshive@gmail.com', NULL, NULL, 'Human Resources', NULL, '$2y$10$IJ14sW0I9TEJYaVCxf9.yuFZZlET3kH8vwpQUGkNg5cUu0FQa7GRG', 'HR', 'HR001', 'Sr. Manager (Studio)', '2024-12-16 13:07:28', '2025-02-05 12:53:26', NULL, 'Active', '2025-02-05 18:23:26', '676e5805148ee.png', '', NULL, '2025-01-15', '2025-02-05 12:53:26', '', '', '', '', '', '', '7224864553', '2024-12-17', 'uploads/profile_pictures/67a3557adbd86_default-avatar.png', '', 'female', '', '', '', 'null', '', '', '', 'null', 'null', 'null', NULL, NULL, NULL, NULL, 0.00, NULL, NULL),
(3, 'Yojna Sharma', NULL, 'yojna.sharma@architectshive.com', NULL, NULL, '', NULL, '$2y$10$rdsbXtR/qSCAJHC0rlbMYOCcMdaq6U5NHmV2FeKemXUXejGXUap1e', 'Senior Manager (Studio)', 'SMS001', '', '2024-12-17 06:41:36', '2025-02-06 07:00:15', NULL, 'Active', '2025-02-06 12:30:15', NULL, NULL, NULL, NULL, '2025-02-06 07:00:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0000-00-00', 'uploads/profile_pictures/67939abcd8f72_wallpaperflare.com_wallpaper (1).jpg', '', '', 'married', 'Indian', '', NULL, '', '', 'A+', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL),
(4, 'John Doe', NULL, '', NULL, NULL, '', NULL, 'password123', 'Senior Manager (Studio)', '', NULL, '2024-12-17 06:49:44', '2024-12-17 06:56:05', NULL, 'Active', NULL, NULL, NULL, NULL, NULL, '2024-12-17 06:49:44', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL),
(7, 'Preeti Choudhary', 'Junior Architect', 'arch.preeti@gmail.com', NULL, NULL, 'Architecture Design', NULL, '$2y$10$pE9NEK7/IbfSc.m2o7f0.eSbR8/YF6rMPjqecM9agUSyA28f7T1Lu', 'Working Team', 'WT001', 'Sr. Manager (Studio)', '2024-12-17 09:04:26', '2025-02-05 07:50:24', NULL, 'Active', '2025-02-05 13:20:09', NULL, NULL, NULL, '2024-04-26', '2025-02-05 07:50:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1999-08-05', NULL, '', 'female', '', '', '', NULL, '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL),
(8, 'test', NULL, 'test@gmail.com', NULL, NULL, '', NULL, '$2y$10$ByGbUoLCdWMpB/XSG2Mhju88hD09bDY4Czue2Ll/9l7C2QkKLzDCy', '3D Designing Team', '3DT001', 'Sr. Manager (Studio)', '2024-12-17 10:42:24', '2024-12-17 10:42:24', NULL, 'Active', NULL, NULL, NULL, NULL, NULL, '2024-12-17 10:42:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL),
(11, 'Rachana Pal', NULL, 'arch.rachanapal@gmail.com', NULL, NULL, '', NULL, '$2y$10$fcFm76vgXeXSCu.52PhM9OGaCZzkwOYjkLxxZqLx0Q0QKMUR1T5Yi', 'Senior Manager (Studio)', 'SMS002', '', '2024-12-18 06:50:47', '2025-01-16 11:58:37', NULL, 'Active', '2025-01-16 17:28:37', NULL, NULL, NULL, NULL, '2025-01-16 11:58:37', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL),
(15, 'Manish Prajapati', 'Junior Architect', 'manish@architectshive.com', NULL, NULL, 'Interior Design', NULL, '$2y$10$PNldyXlZaPlzXxYRYr6Z9OZa/vo/y1fRMDru6ESac77CLZXbTvjca', 'Design Team', 'DT001', 'Sr. Manager (Studio)', '2024-12-18 07:45:15', '2025-02-05 14:06:32', NULL, 'Active', '2025-02-05 19:36:32', NULL, NULL, NULL, NULL, '2025-02-05 14:06:32', NULL, NULL, NULL, NULL, NULL, NULL, '8855223322', '2001-02-22', 'uploads/profile_pictures/679c6707b6683_photo_2024-12-31_16-34-42.jpg', '', 'male', '', '', '', '{\"linkedin\":\"\",\"twitter\":\"\",\"facebook\":\"\",\"instagram\":\"\",\"github\":\"\",\"youtube\":\"\"}', '', '', '', '{\"highest_degree\":\"\",\"institution\":\"\",\"field_of_study\":\"\",\"graduation_year\":\"\"}', '{\"current_company\":\"\",\"job_title\":\"\",\"experience_years\":\"\",\"responsibilities\":\"\"}', '{\"bank_name\":\"\",\"account_holder\":\"\",\"account_number\":\"\",\"ifsc_code\":\"\"}', 4, '[]', NULL, NULL, 0.00, NULL, NULL),
(21, 'Aditya Kumar Pal', 'Office Manager', 'aaadityapal69@icloud.com', NULL, NULL, 'IT Support', NULL, '$2y$10$9iAXnlBQEP0rpiW82X2jfO.7F9A7VpHzPmt/2C2L/2sxZDwjuQcVa', 'Design Team', 'DT002', 'Sr. Manager (Studio)', '2024-12-18 09:29:49', '2025-02-05 11:43:40', NULL, 'Active', '2025-02-05 17:13:40', NULL, 'F-48 Street Number 16, Madhu Vihar', NULL, '2025-01-01', '2025-02-05 11:43:40', 'Delhi NCR', 'Delhi', 'India', '110092', 'Aditya Pal', '07224864553', '1122334455', '2001-10-24', 'uploads/profile_pictures/67837303da8d8_photo_2024-12-31_16-34-42.jpg', '', 'male', 'single', 'Indian', 'English', '{\"linkedin\":\"\",\"twitter\":\"\",\"facebook\":\"\",\"instagram\":\"\",\"github\":\"\",\"youtube\":\"\"}', '', '', 'O+', '{\"highest_degree\":\"high_school\",\"institution\":\"\",\"field_of_study\":\"\",\"graduation_year\":\"\"}', '{\"current_company\":\"ArchitectsHive\",\"job_title\":\"Senior Manager\",\"experience_years\":\"2\",\"responsibilities\":\"Working there as a senior manager for 2 years\\n\"}', '{\"account_holder\":\"Aditya Kumar Pal\",\"bank_name\":\"State Bank Of India\",\"account_number\":\"6969696969\",\"ifsc_code\":\"SBIN0003965\",\"branch_name\":\"hola amigo\",\"account_type\":\"savings\"}', 4, '[{\"type\":\"aadhar\",\"filename\":\"RACHANA PAL_CV.pdf\",\"file_path\":\"uploads\\/documents\\/67837548c5957_RACHANA PAL_CV.pdf\",\"upload_date\":\"2025-01-12 13:24:48\"},{\"type\":\"pan\",\"filename\":\"Agreement_2024-12-02_100746.pdf\",\"file_path\":\"uploads\\/documents\\/67837e6651576_Agreement_2024-12-02_100746.pdf\",\"upload_date\":\"2025-01-12 14:03:42\"},{\"type\":\"passport\",\"filename\":\"salary_report_2024-11 (2).pdf\",\"file_path\":\"uploads\\/documents\\/678381c3b089b_salary_report_2024-11 (2).pdf\",\"upload_date\":\"2025-01-12 14:18:03\"}]', '[{\"current_company\":\"ArchitectsHive\",\"job_title\":\"Senior IT manager\",\"experience_years\":\"2\",\"responsibilities\":\"Managing all the technical Stuff and work as a Full stack Developer\\r\\n \\r\\n\"}]', '[]', 50000.00, '{\"hra\":\"0\",\"da\":\"0\",\"ta\":\"0\",\"medical\":\"0\"}', '{\"pf\":\"0\",\"tax\":\"0\",\"insurance\":\"0\"}'),
(22, 'Meenakshi Kumari', NULL, 'arch.meenakshi@gmail.com', NULL, NULL, '', NULL, '$2y$10$8dbf3AO.fMPkLXfW9EUDNOCjvP9OcaSuQxYUxpP62RzEd4FRSkjN.', 'Design Team', 'DT003', 'Sr. Manager (Studio)', '2025-02-05 07:45:01', '2025-02-05 07:45:01', NULL, 'Active', '2025-02-05 13:15:01', NULL, NULL, NULL, NULL, '2025-02-05 07:45:01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL),
(23, 'Mohit Verma', NULL, 'arch.mohit@gmail.com', NULL, NULL, '', NULL, '$2y$10$orJE4O6qA6z4YIu.GDcWFeicE2WBvd5tHtDsGJuVGUX1d2alQxj2m', 'Studio Trainees', 'STR001', 'Sr. Manager (Studio)', '2025-02-05 07:46:33', '2025-02-05 07:46:33', NULL, 'Active', '2025-02-05 13:16:33', NULL, NULL, NULL, NULL, '2025-02-05 07:46:33', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL),
(24, 'Devikaa Rajput', NULL, 'arch.devikaa@gmail.com', NULL, NULL, '', NULL, '$2y$10$R3W9Y6MnpS.HoeImyAVvl.6pocRh7/6r2b4GSYK0Qz4qaTPTIf8Ri', 'Design Team', 'DT004', 'Sr. Manager (Studio)', '2025-02-05 07:47:30', '2025-02-05 07:47:30', NULL, 'Active', '2025-02-05 13:17:30', NULL, NULL, NULL, NULL, '2025-02-05 07:47:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL),
(25, 'Prabhat Arya', NULL, 'prabhat@architectshive.com', NULL, NULL, '', NULL, '$2y$10$G/qSSGMtU4YepzCsyRXnO.jtx1G1o1/Q12lrEeDQ88NPifhWpNaYO', 'Senior Manager (Studio)', 'SMS003', '', '2025-02-05 07:48:10', '2025-02-05 07:48:21', NULL, 'Active', '2025-02-05 13:18:21', NULL, NULL, NULL, NULL, '2025-02-05 07:48:21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL),
(26, 'Sarvesh Pathri', NULL, 'arch.sarvesh@gmail.com', NULL, NULL, '', NULL, '$2y$10$NzBS2WAl3wpRAUDElDdIieYntORhoHbgFF5pjvwvUlNxd/LiLs90O', 'Studio Trainees', 'STR002', 'Sr. Manager (Studio)', '2025-02-05 07:51:18', '2025-02-05 07:51:18', NULL, 'Active', '2025-02-05 13:21:18', NULL, NULL, NULL, NULL, '2025-02-05 07:51:18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `preference_key` varchar(50) NOT NULL,
  `preference_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_preferences`
--

INSERT INTO `user_preferences` (`id`, `user_id`, `preference_key`, `preference_value`, `updated_at`) VALUES
(1, 21, 'emailNotifications', '0', '2025-01-10 15:29:49'),
(6, 21, 'pushNotifications', '0', '2025-01-10 15:29:48');

-- --------------------------------------------------------

--
-- Table structure for table `user_shifts`
--

CREATE TABLE `user_shifts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `weekly_offs` varchar(255) DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_shifts`
--

INSERT INTO `user_shifts` (`id`, `user_id`, `shift_id`, `weekly_offs`, `effective_from`, `effective_to`, `created_at`, `updated_at`) VALUES
(1, 21, 4, 'Tuesday', '2024-12-27', NULL, '2024-12-27 08:30:03', '2024-12-27 08:30:03'),
(2, 2, 4, '', '2024-12-27', NULL, '2024-12-27 09:21:05', '2024-12-27 09:21:05'),
(3, 15, 4, 'Friday', '2024-12-27', '2025-02-04', '2024-12-27 10:20:31', '2025-02-05 07:58:44'),
(4, 3, 4, 'Sunday', '2025-01-23', NULL, '2025-01-23 07:00:24', '2025-01-23 07:00:24'),
(5, 7, 4, 'Tuesday', '2025-01-31', '2025-02-04', '2025-01-30 12:41:23', '2025-02-05 08:00:02'),
(6, 24, 12, 'Wednesday', '2025-02-05', NULL, '2025-02-05 07:58:13', '2025-02-05 07:58:13'),
(7, 15, 5, 'Monday', '2025-02-05', NULL, '2025-02-05 07:58:44', '2025-02-05 07:58:44'),
(8, 22, 11, 'Sunday', '2025-02-05', NULL, '2025-02-05 07:59:19', '2025-02-05 07:59:19'),
(9, 23, 11, 'Sunday', '2025-02-05', NULL, '2025-02-05 07:59:43', '2025-02-05 07:59:43'),
(10, 7, 6, 'Tuesday', '2025-02-05', NULL, '2025-02-05 08:00:02', '2025-02-05 08:00:02'),
(11, 26, 11, 'Sunday', '2025-02-05', NULL, '2025-02-05 08:00:22', '2025-02-05 08:00:22');

-- --------------------------------------------------------

--
-- Table structure for table `user_status`
--

CREATE TABLE `user_status` (
  `user_id` int(11) NOT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `last_seen` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_status`
--

INSERT INTO `user_status` (`user_id`, `is_online`, `last_seen`) VALUES
(1, 0, '2025-01-07 09:52:44'),
(2, 0, '2025-01-07 09:52:44'),
(3, 0, '2025-01-07 09:52:44'),
(4, 0, '2025-01-07 09:52:44'),
(7, 0, '2025-01-07 09:52:44'),
(8, 0, '2025-01-07 09:52:44'),
(11, 0, '2025-01-07 09:52:44'),
(15, 0, '2025-01-07 10:20:47'),
(21, 0, '2025-01-07 10:20:45');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_leave_applications`
-- (See below for the actual view)
--
CREATE TABLE `vw_leave_applications` (
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_leave_balances`
-- (See below for the actual view)
--
CREATE TABLE `vw_leave_balances` (
`id` int(11)
,`user_id` int(11)
,`username` varchar(255)
,`employee_id` varchar(50)
,`leave_type` varchar(50)
,`year` year(4)
,`total_leaves` decimal(5,1)
,`used_leaves` decimal(5,1)
,`remaining_leaves` decimal(6,1)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_leave_applications`
--
DROP TABLE IF EXISTS `vw_leave_applications`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_leave_applications`  AS SELECT `l`.`id` AS `id`, `l`.`user_id` AS `user_id`, `u`.`username` AS `username`, `u`.`employee_id` AS `employee_id`, `lt`.`name` AS `leave_type`, `l`.`start_date` AS `start_date`, `l`.`end_date` AS `end_date`, `l`.`reason` AS `reason`, `l`.`status` AS `status`, `l`.`manager_status` AS `manager_status`, `l`.`hr_status` AS `hr_status`, `l`.`created_at` AS `created_at`, `l`.`modified_at` AS `modified_at` FROM ((`leaves` `l` join `users` `u` on(`l`.`user_id` = `u`.`id`)) join `leave_types` `lt` on(`l`.`leave_type_id` = `lt`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_leave_balances`
--
DROP TABLE IF EXISTS `vw_leave_balances`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_leave_balances`  AS SELECT `lb`.`id` AS `id`, `lb`.`user_id` AS `user_id`, `u`.`username` AS `username`, `u`.`employee_id` AS `employee_id`, `lt`.`name` AS `leave_type`, `lb`.`year` AS `year`, `lb`.`total_leaves` AS `total_leaves`, `lb`.`used_leaves` AS `used_leaves`, `lb`.`total_leaves`- `lb`.`used_leaves` AS `remaining_leaves` FROM ((`leave_balance` `lb` join `users` `u` on(`lb`.`user_id` = `u`.`id`)) join `leave_types` `lt` on(`lb`.`leave_type_id` = `lt`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_announcements_status` (`status`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `modified_by` (`modified_by`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_attendance_date` (`date`),
  ADD KEY `idx_user_date` (`user_id`,`punch_in`);

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attendance_id` (`attendance_id`);

--
-- Indexes for table `chat_groups`
--
ALTER TABLE `chat_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat_sender` (`sender_id`),
  ADD KEY `idx_chat_receiver` (`receiver_id`),
  ADD KEY `idx_chat_created` (`created_at`);

--
-- Indexes for table `circulars`
--
ALTER TABLE `circulars`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `construction_sites`
--
ALTER TABLE `construction_sites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `site_manager_id` (`site_manager_id`),
  ADD KEY `site_engineer_id` (`site_engineer_id`),
  ADD KEY `modified_by` (`modified_by`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation_updated` (`updated_at`);

--
-- Indexes for table `conversation_participants`
--
ALTER TABLE `conversation_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_participant` (`conversation_id`,`user_id`),
  ADD KEY `idx_participant_user` (`user_id`);

--
-- Indexes for table `entity_status_history`
--
ALTER TABLE `entity_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changed_by_user_id` (`changed_by_user_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_events_date` (`event_date`);

--
-- Indexes for table `file_attachments`
--
ALTER TABLE `file_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comment_id` (`comment_id`);

--
-- Indexes for table `file_comments`
--
ALTER TABLE `file_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `file_status_logs`
--
ALTER TABLE `file_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `forward_tasks`
--
ALTER TABLE `forward_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `stage_id` (`stage_id`),
  ADD KEY `substage_id` (`substage_id`),
  ADD KEY `forwarded_by` (`forwarded_by`),
  ADD KEY `forwarded_to` (`forwarded_to`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `group_messages`
--
ALTER TABLE `group_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `group_message_status`
--
ALTER TABLE `group_message_status`
  ADD PRIMARY KEY (`message_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `leaves`
--
ALTER TABLE `leaves`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leave_balance`
--
ALTER TABLE `leave_balance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_balance` (`user_id`,`leave_type_id`,`year`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_month_year` (`user_id`,`month`,`year`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `leave_history`
--
ALTER TABLE `leave_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leave_id` (`leave_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `leave_request`
--
ALTER TABLE `leave_request`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_leave_request_user` (`user_id`),
  ADD KEY `fk_leave_request_leave_type` (`leave_type`),
  ADD KEY `idx_leave_request_status` (`status`),
  ADD KEY `idx_leave_request_dates` (`start_date`,`end_date`),
  ADD KEY `fk_action_by` (`action_by`),
  ADD KEY `fk_updated_by` (`updated_by`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_messages_conversation` (`conversation_id`,`sent_at`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `message_status`
--
ALTER TABLE `message_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_status` (`message_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `project_activity_log`
--
ALTER TABLE `project_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `stage_id` (`stage_id`),
  ADD KEY `substage_id` (`substage_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `project_categories`
--
ALTER TABLE `project_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_category_parent` (`parent_id`);

--
-- Indexes for table `project_files`
--
ALTER TABLE `project_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `stage_id` (`stage_id`),
  ADD KEY `substage_id` (`substage_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `project_history`
--
ALTER TABLE `project_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `project_stages`
--
ALTER TABLE `project_stages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `project_status_history`
--
ALTER TABLE `project_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `stage_id` (`stage_id`),
  ADD KEY `substage_id` (`substage_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `project_substages`
--
ALTER TABLE `project_substages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stage_id` (`stage_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `idx_substage_identifier` (`substage_identifier`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `resumes`
--
ALTER TABLE `resumes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `salary_details`
--
ALTER TABLE `salary_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_month` (`user_id`,`month_year`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `month_year` (`month_year`);

--
-- Indexes for table `salary_history`
--
ALTER TABLE `salary_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `salary_records`
--
ALTER TABLE `salary_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_salary_records_month` (`month`);

--
-- Indexes for table `salary_structures`
--
ALTER TABLE `salary_structures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_attendance`
--
ALTER TABLE `site_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_site_attendance` (`site_id`,`user_id`,`date`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `modified_by` (`modified_by`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `site_progress`
--
ALTER TABLE `site_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_site_date` (`site_id`,`date`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `modified_by` (`modified_by`);

--
-- Indexes for table `stage_files`
--
ALTER TABLE `stage_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stage_id` (`stage_id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `task_id` (`task_id`);

--
-- Indexes for table `substage_comments`
--
ALTER TABLE `substage_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `substage_id` (`substage_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `substage_files`
--
ALTER TABLE `substage_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `substage_id` (`substage_id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `task_categories`
--
ALTER TABLE `task_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `task_comments`
--
ALTER TABLE `task_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `task_forwarding_history`
--
ALTER TABLE `task_forwarding_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `forwarded_by` (`forwarded_by`),
  ADD KEY `forwarded_to` (`forwarded_to`);

--
-- Indexes for table `task_priorities`
--
ALTER TABLE `task_priorities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `task_stages`
--
ALTER TABLE `task_stages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `task_status`
--
ALTER TABLE `task_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `task_status_history`
--
ALTER TABLE `task_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `fk_task_status_history_task` (`task_id`);

--
-- Indexes for table `task_substages`
--
ALTER TABLE `task_substages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stage_id` (`stage_id`);

--
-- Indexes for table `travel_expenses`
--
ALTER TABLE `travel_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `unique_id` (`unique_id`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD UNIQUE KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user_status` (`status`),
  ADD KEY `idx_user_role` (`role`),
  ADD KEY `idx_user_deleted` (`deleted_at`),
  ADD KEY `shift_id` (`shift_id`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_preference_unique` (`user_id`,`preference_key`);

--
-- Indexes for table `user_shifts`
--
ALTER TABLE `user_shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `shift_id` (`shift_id`);

--
-- Indexes for table `user_status`
--
ALTER TABLE `user_status`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_groups`
--
ALTER TABLE `chat_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `circulars`
--
ALTER TABLE `circulars`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `construction_sites`
--
ALTER TABLE `construction_sites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversation_participants`
--
ALTER TABLE `conversation_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entity_status_history`
--
ALTER TABLE `entity_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `file_attachments`
--
ALTER TABLE `file_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `file_comments`
--
ALTER TABLE `file_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `file_status_logs`
--
ALTER TABLE `file_status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forward_tasks`
--
ALTER TABLE `forward_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `group_messages`
--
ALTER TABLE `group_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leaves`
--
ALTER TABLE `leaves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_balance`
--
ALTER TABLE `leave_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_history`
--
ALTER TABLE `leave_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_request`
--
ALTER TABLE `leave_request`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_status`
--
ALTER TABLE `message_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `project_activity_log`
--
ALTER TABLE `project_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

--
-- AUTO_INCREMENT for table `project_categories`
--
ALTER TABLE `project_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `project_files`
--
ALTER TABLE `project_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `project_history`
--
ALTER TABLE `project_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `project_stages`
--
ALTER TABLE `project_stages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `project_status_history`
--
ALTER TABLE `project_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `project_substages`
--
ALTER TABLE `project_substages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resumes`
--
ALTER TABLE `resumes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_details`
--
ALTER TABLE `salary_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=763;

--
-- AUTO_INCREMENT for table `salary_history`
--
ALTER TABLE `salary_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_records`
--
ALTER TABLE `salary_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `salary_structures`
--
ALTER TABLE `salary_structures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `site_attendance`
--
ALTER TABLE `site_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_progress`
--
ALTER TABLE `site_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stage_files`
--
ALTER TABLE `stage_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `substage_comments`
--
ALTER TABLE `substage_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `substage_files`
--
ALTER TABLE `substage_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `task_categories`
--
ALTER TABLE `task_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `task_comments`
--
ALTER TABLE `task_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `task_forwarding_history`
--
ALTER TABLE `task_forwarding_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_priorities`
--
ALTER TABLE `task_priorities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `task_stages`
--
ALTER TABLE `task_stages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `task_status`
--
ALTER TABLE `task_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `task_status_history`
--
ALTER TABLE `task_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=231;

--
-- AUTO_INCREMENT for table `task_substages`
--
ALTER TABLE `task_substages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `travel_expenses`
--
ALTER TABLE `travel_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_shifts`
--
ALTER TABLE `user_shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`);

--
-- Constraints for table `chat_groups`
--
ALTER TABLE `chat_groups`
  ADD CONSTRAINT `chat_groups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `circulars`
--
ALTER TABLE `circulars`
  ADD CONSTRAINT `circulars_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `construction_sites`
--
ALTER TABLE `construction_sites`
  ADD CONSTRAINT `construction_sites_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `construction_sites_ibfk_2` FOREIGN KEY (`site_manager_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `construction_sites_ibfk_3` FOREIGN KEY (`site_engineer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `construction_sites_ibfk_4` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `conversation_participants`
--
ALTER TABLE `conversation_participants`
  ADD CONSTRAINT `conversation_participants_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`),
  ADD CONSTRAINT `conversation_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `entity_status_history`
--
ALTER TABLE `entity_status_history`
  ADD CONSTRAINT `entity_status_history_ibfk_1` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `file_attachments`
--
ALTER TABLE `file_attachments`
  ADD CONSTRAINT `file_attachments_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`);

--
-- Constraints for table `file_comments`
--
ALTER TABLE `file_comments`
  ADD CONSTRAINT `file_comments_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `stage_files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `file_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `file_status_logs`
--
ALTER TABLE `file_status_logs`
  ADD CONSTRAINT `file_status_logs_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `substage_files` (`id`),
  ADD CONSTRAINT `file_status_logs_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `forward_tasks`
--
ALTER TABLE `forward_tasks`
  ADD CONSTRAINT `forward_tasks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `forward_tasks_ibfk_2` FOREIGN KEY (`stage_id`) REFERENCES `project_stages` (`id`),
  ADD CONSTRAINT `forward_tasks_ibfk_3` FOREIGN KEY (`substage_id`) REFERENCES `project_substages` (`id`),
  ADD CONSTRAINT `forward_tasks_ibfk_4` FOREIGN KEY (`forwarded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `forward_tasks_ibfk_5` FOREIGN KEY (`forwarded_to`) REFERENCES `users` (`id`);

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `chat_groups` (`id`),
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `group_messages`
--
ALTER TABLE `group_messages`
  ADD CONSTRAINT `group_messages_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `chat_groups` (`id`),
  ADD CONSTRAINT `group_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `group_message_status`
--
ALTER TABLE `group_message_status`
  ADD CONSTRAINT `group_message_status_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `group_messages` (`id`),
  ADD CONSTRAINT `group_message_status_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `holidays`
--
ALTER TABLE `holidays`
  ADD CONSTRAINT `holidays_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `leave_balance`
--
ALTER TABLE `leave_balance`
  ADD CONSTRAINT `leave_balance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `leave_balance_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`);

--
-- Constraints for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `leave_history`
--
ALTER TABLE `leave_history`
  ADD CONSTRAINT `leave_history_ibfk_1` FOREIGN KEY (`leave_id`) REFERENCES `leaves` (`id`),
  ADD CONSTRAINT `leave_history_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `leave_request`
--
ALTER TABLE `leave_request`
  ADD CONSTRAINT `fk_action_by` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_leave_request_action_by` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_leave_request_leave_type` FOREIGN KEY (`leave_type`) REFERENCES `leave_types` (`id`),
  ADD CONSTRAINT `fk_leave_request_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_leave_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD CONSTRAINT `leave_types_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `message_status`
--
ALTER TABLE `message_status`
  ADD CONSTRAINT `message_status_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`),
  ADD CONSTRAINT `message_status_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `projects_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `project_categories` (`id`);

--
-- Constraints for table `project_activity_log`
--
ALTER TABLE `project_activity_log`
  ADD CONSTRAINT `project_activity_log_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `project_activity_log_ibfk_2` FOREIGN KEY (`stage_id`) REFERENCES `project_stages` (`id`),
  ADD CONSTRAINT `project_activity_log_ibfk_3` FOREIGN KEY (`substage_id`) REFERENCES `project_substages` (`id`),
  ADD CONSTRAINT `project_activity_log_ibfk_4` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `project_categories`
--
ALTER TABLE `project_categories`
  ADD CONSTRAINT `fk_category_parent` FOREIGN KEY (`parent_id`) REFERENCES `project_categories` (`id`);

--
-- Constraints for table `project_files`
--
ALTER TABLE `project_files`
  ADD CONSTRAINT `project_files_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `project_files_ibfk_2` FOREIGN KEY (`stage_id`) REFERENCES `project_stages` (`id`),
  ADD CONSTRAINT `project_files_ibfk_3` FOREIGN KEY (`substage_id`) REFERENCES `project_substages` (`id`),
  ADD CONSTRAINT `project_files_ibfk_4` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `project_history`
--
ALTER TABLE `project_history`
  ADD CONSTRAINT `project_history_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `project_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `project_stages`
--
ALTER TABLE `project_stages`
  ADD CONSTRAINT `project_stages_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `project_stages_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`);

--
-- Constraints for table `project_status_history`
--
ALTER TABLE `project_status_history`
  ADD CONSTRAINT `project_status_history_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `project_status_history_ibfk_2` FOREIGN KEY (`stage_id`) REFERENCES `project_stages` (`id`),
  ADD CONSTRAINT `project_status_history_ibfk_3` FOREIGN KEY (`substage_id`) REFERENCES `project_substages` (`id`),
  ADD CONSTRAINT `project_status_history_ibfk_4` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `project_substages`
--
ALTER TABLE `project_substages`
  ADD CONSTRAINT `project_substages_ibfk_1` FOREIGN KEY (`stage_id`) REFERENCES `project_stages` (`id`),
  ADD CONSTRAINT `project_substages_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`);

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `salary_details`
--
ALTER TABLE `salary_details`
  ADD CONSTRAINT `salary_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `salary_history`
--
ALTER TABLE `salary_history`
  ADD CONSTRAINT `salary_history_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `salary_history_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `salary_records`
--
ALTER TABLE `salary_records`
  ADD CONSTRAINT `salary_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `salary_structures`
--
ALTER TABLE `salary_structures`
  ADD CONSTRAINT `salary_structures_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `site_attendance`
--
ALTER TABLE `site_attendance`
  ADD CONSTRAINT `site_attendance_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `construction_sites` (`id`),
  ADD CONSTRAINT `site_attendance_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `site_attendance_ibfk_3` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `site_progress`
--
ALTER TABLE `site_progress`
  ADD CONSTRAINT `site_progress_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `construction_sites` (`id`),
  ADD CONSTRAINT `site_progress_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `site_progress_ibfk_3` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `stage_files`
--
ALTER TABLE `stage_files`
  ADD CONSTRAINT `stage_files_ibfk_1` FOREIGN KEY (`stage_id`) REFERENCES `task_stages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stage_files_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stage_files_ibfk_3` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`);

--
-- Constraints for table `substage_comments`
--
ALTER TABLE `substage_comments`
  ADD CONSTRAINT `substage_comments_ibfk_1` FOREIGN KEY (`substage_id`) REFERENCES `task_substages` (`id`),
  ADD CONSTRAINT `substage_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `substage_files`
--
ALTER TABLE `substage_files`
  ADD CONSTRAINT `substage_files_ibfk_1` FOREIGN KEY (`substage_id`) REFERENCES `project_substages` (`id`),
  ADD CONSTRAINT `substage_files_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `substage_files_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `task_categories` (`id`),
  ADD CONSTRAINT `tasks_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_comments`
--
ALTER TABLE `task_comments`
  ADD CONSTRAINT `task_comments_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_comments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_forwarding_history`
--
ALTER TABLE `task_forwarding_history`
  ADD CONSTRAINT `task_forwarding_history_ibfk_1` FOREIGN KEY (`forwarded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `task_forwarding_history_ibfk_2` FOREIGN KEY (`forwarded_to`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_stages`
--
ALTER TABLE `task_stages`
  ADD CONSTRAINT `task_stages_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_stages_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_status_history`
--
ALTER TABLE `task_status_history`
  ADD CONSTRAINT `fk_task_status_history_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`),
  ADD CONSTRAINT `task_status_history_ibfk_1` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_substages`
--
ALTER TABLE `task_substages`
  ADD CONSTRAINT `task_substages_ibfk_1` FOREIGN KEY (`stage_id`) REFERENCES `task_stages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `travel_expenses`
--
ALTER TABLE `travel_expenses`
  ADD CONSTRAINT `travel_expenses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`);

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_shifts`
--
ALTER TABLE `user_shifts`
  ADD CONSTRAINT `user_shifts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `user_shifts_ibfk_2` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `user_status`
--
ALTER TABLE `user_status`
  ADD CONSTRAINT `user_status_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
