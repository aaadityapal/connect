-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 15, 2025 at 03:53 PM
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

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `date`, `punch_in`, `punch_out`, `auto_punch_out`, `location`, `ip_address`, `device_info`, `overtime`, `status`, `remarks`, `modified_by`, `created_at`, `modified_at`, `working_hours`, `overtime_hours`, `shift_time`, `weekly_offs`, `is_weekly_off`) VALUES
(1, 3, '0000-00-00', '2024-12-17 16:24:18', NULL, 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2024-12-17 10:54:18', '2024-12-17 10:54:18', NULL, NULL, '09:00:00', NULL, 0),
(2, 11, '0000-00-00', '2024-12-18 12:21:03', '2024-12-18 18:07:16', 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2024-12-18 06:51:03', '2024-12-18 12:37:16', NULL, NULL, '09:00:00', NULL, 0),
(6, 3, '2024-12-18', '2024-12-18 11:42:47', '2024-12-18 16:13:53', 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2024-12-18 10:42:47', '2024-12-18 10:43:53', NULL, NULL, '09:00:00', NULL, 0),
(7, 11, '2024-12-18', '2024-12-18 11:44:22', NULL, 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2024-12-18 10:44:22', '2024-12-18 10:44:22', NULL, NULL, '09:00:00', NULL, 0),
(14, 21, '2024-12-18', '2024-12-18 19:31:23', '2024-12-18 19:43:01', 0, '28.6217025,77.2836039', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2024-12-18 14:01:23', '2024-12-18 14:13:01', '00:00:00', NULL, '09:00:00', NULL, 0),
(15, 21, '2024-12-19', '2024-12-19 10:59:23', NULL, 0, '28.6369683,77.3028426', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2024-12-19 05:29:23', '2024-12-19 05:29:23', NULL, NULL, '09:00:00', NULL, 0),
(16, 3, '2024-12-19', '2024-12-19 07:16:33', '2024-12-19 15:51:59', 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2024-12-19 06:16:33', '2024-12-19 10:21:59', NULL, NULL, '09:00:00', NULL, 0),
(17, 15, '2024-12-19', '2024-12-19 14:48:50', '2024-12-19 14:50:03', 0, '28.6369545,77.3028091', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2024-12-19 09:18:50', '2024-12-19 09:20:03', '00:00:00', NULL, '09:00:00', NULL, 0),
(18, 21, '2024-12-20', '2024-12-20 11:11:15', NULL, 0, '28.6369572,77.3028153', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2024-12-20 05:41:15', '2024-12-20 05:41:15', NULL, NULL, '09:00:00', NULL, 0),
(19, 3, '2024-12-20', '2024-12-20 11:12:00', NULL, 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2024-12-20 05:42:00', '2024-12-20 05:42:00', NULL, NULL, '09:00:00', NULL, 0),
(20, 15, '2024-12-20', '2024-12-20 11:56:35', '2024-12-20 20:32:51', 0, '28.639232,77.299712', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2024-12-20 06:26:35', '2024-12-20 15:02:51', '00:00:08', NULL, '09:00:00', NULL, 0),
(21, 3, '2024-12-21', '2024-12-21 11:17:26', NULL, 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2024-12-21 05:47:26', '2024-12-21 05:47:26', NULL, NULL, '09:00:00', NULL, 0),
(22, 21, '2024-12-21', '2024-12-21 12:54:23', NULL, 0, '28.639232,77.299712', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2024-12-21 07:24:23', '2024-12-21 07:24:23', NULL, NULL, '09:00:00', NULL, 0),
(23, 21, '2024-12-23', '2024-12-23 11:15:43', NULL, 0, '28.6229302,77.297967', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2024-12-23 05:45:43', '2024-12-23 05:45:43', NULL, NULL, '09:00:00', NULL, 0),
(24, 3, '2024-12-23', '2024-12-23 11:16:39', NULL, 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2024-12-23 05:46:39', '2024-12-23 05:46:39', NULL, NULL, '09:00:00', NULL, 0),
(25, 15, '2024-12-23', '2024-12-23 19:10:28', '2024-12-23 19:10:53', 0, '28.6369769,77.3027996', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2024-12-23 13:40:28', '2024-12-23 13:40:53', '00:00:00', NULL, '09:00:00', NULL, 0),
(26, 21, '2024-12-26', '2024-12-26 11:35:36', NULL, 0, '28.6369727,77.3028416', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2024-12-26 06:05:36', '2024-12-26 06:05:36', NULL, NULL, '09:00:00', NULL, 0),
(27, 3, '2024-12-26', '2024-12-26 13:07:08', NULL, 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2024-12-26 07:37:08', '2024-12-26 07:37:08', NULL, NULL, '09:00:00', NULL, 0),
(28, 21, '2024-12-27', '2024-12-27 12:31:46', '2024-12-27 18:15:12', 0, '28.6282626,77.297967', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2024-12-27 07:01:46', '2024-12-27 12:45:12', '00:00:05', NULL, '09:00:00', NULL, 0),
(29, 15, '2024-12-27', '2024-12-27 12:32:21', '2024-12-27 18:16:51', 0, '28.6282626,77.297967', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2024-12-27 07:02:21', '2024-12-27 12:46:51', '00:00:05', NULL, '09:00:00', NULL, 0),
(30, 2, '2024-12-27', '2024-12-27 13:28:33', '2024-12-27 18:14:49', 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2024-12-27 07:58:33', '2024-12-27 12:44:49', NULL, NULL, '09:00:00', NULL, 0),
(31, 2, '2024-12-28', '2024-12-28 11:43:14', NULL, 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2024-12-28 06:13:14', '2024-12-28 06:13:14', NULL, NULL, '09:00:00', NULL, 0),
(32, 21, '2024-12-28', '2024-12-28 11:43:43', NULL, 0, '28.6369578,77.3027414', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2024-12-28 06:13:43', '2024-12-28 06:13:43', NULL, NULL, '09:00:00', NULL, 0),
(33, 3, '2024-12-28', '2024-12-28 14:30:59', NULL, 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2024-12-28 09:00:59', '2024-12-28 09:00:59', NULL, NULL, '09:00:00', NULL, 0),
(34, 15, '2024-12-28', '2024-12-28 14:51:51', NULL, 0, '28.6282626,77.297967', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2024-12-28 09:21:51', '2024-12-28 09:21:51', NULL, NULL, '09:00:00', NULL, 0),
(40, 15, '2025-01-07', '2025-01-07 13:06:58', NULL, 0, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2025-01-07 07:36:58', '2025-01-07 07:36:58', NULL, NULL, '09:00:00', 'Friday', 0),
(43, 21, '2025-01-07', '2025-01-07 13:47:48', '2025-01-07 19:06:43', 0, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, 21, '2025-01-07 08:17:48', '2025-01-07 13:36:43', '05:18:55', NULL, '09:00:00', 'Tuesday', 1),
(44, 21, '2025-01-08', '2025-01-08 12:16:51', '2025-01-08 20:43:48', 0, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, 21, '2025-01-08 06:46:51', '2025-01-08 15:13:48', '08:26:57', NULL, '09:00:00', 'Tuesday', 0),
(45, 15, '2025-01-08', '2025-01-08 12:20:38', '2025-01-08 20:44:23', 0, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, 15, '2025-01-08 06:50:38', '2025-01-08 15:14:23', '08:23:45', NULL, '09:00:00', 'Friday', 0),
(46, 21, '2025-01-09', '2025-01-09 11:36:04', NULL, 0, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2025-01-09 06:06:04', '2025-01-09 06:06:04', NULL, NULL, '09:00:00', 'Tuesday', 0),
(47, 15, '2025-01-09', '2025-01-09 11:38:42', NULL, 0, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2025-01-09 06:08:42', '2025-01-09 06:08:42', NULL, NULL, '09:00:00', 'Friday', 0),
(48, 21, '2025-01-10', '2025-01-10 11:38:53', NULL, 0, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2025-01-10 06:08:53', '2025-01-10 06:08:53', NULL, NULL, '09:00:00', 'Tuesday', 0),
(49, 15, '2025-01-10', '2025-01-10 18:59:08', NULL, 0, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2025-01-10 13:29:08', '2025-01-10 13:29:09', NULL, NULL, '09:00:00', 'Friday', 1),
(50, 2, '2025-01-10', '2025-01-10 21:11:20', NULL, 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2025-01-10 15:41:20', '2025-01-10 15:41:20', NULL, NULL, '09:00:00', NULL, 0),
(51, 15, '2025-01-11', '2025-01-11 11:50:38', '2025-01-11 18:41:51', 0, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, 15, '2025-01-11 06:20:38', '2025-01-11 13:11:51', '06:51:13', NULL, '09:00:00', 'Friday', 0),
(52, 3, '2025-01-11', '2025-01-11 11:51:10', '2025-01-11 12:55:12', 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2025-01-11 06:21:10', '2025-01-11 07:25:12', NULL, NULL, '09:00:00', NULL, 0),
(53, 21, '2025-01-11', '2025-01-11 11:51:51', '2025-01-11 18:41:28', 0, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, 21, '2025-01-11 06:21:51', '2025-01-11 13:11:28', '06:49:37', NULL, '09:00:00', 'Tuesday', 0),
(54, 2, '2025-01-11', '2025-01-11 11:55:16', '2025-01-11 18:42:28', 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2025-01-11 06:25:16', '2025-01-11 13:12:28', NULL, NULL, '09:00:00', NULL, 0),
(55, 21, '2025-01-12', '2025-01-12 12:18:06', NULL, 0, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2025-01-12 06:48:06', '2025-01-12 06:48:06', NULL, NULL, '09:00:00', 'Tuesday', 0),
(56, 2, '2025-01-12', '2025-01-12 12:19:10', NULL, 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2025-01-12 06:49:10', '2025-01-12 06:49:10', NULL, NULL, '09:00:00', NULL, 0),
(57, 3, '2025-01-12', '2025-01-12 15:01:56', '2025-01-12 18:11:14', 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2025-01-12 09:31:56', '2025-01-12 12:41:14', NULL, NULL, '09:00:00', NULL, 0),
(58, 3, '2025-01-13', '2025-01-13 11:39:13', '2025-01-13 19:51:19', 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2025-01-13 06:09:13', '2025-01-13 14:21:19', NULL, NULL, '09:00:00', NULL, 0),
(59, 15, '2025-01-13', '2025-01-13 11:40:08', NULL, 0, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2025-01-13 06:10:08', '2025-01-13 06:10:08', NULL, NULL, '09:00:00', 'Friday', 0),
(60, 15, '2025-01-14', '2025-01-14 11:24:15', NULL, 0, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, NULL, '2025-01-14 05:54:15', '2025-01-14 05:54:15', NULL, NULL, '09:00:00', 'Friday', 0),
(61, 21, '2025-01-14', '2025-01-14 12:12:52', '2025-01-14 19:44:55', 0, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, 21, '2025-01-14 06:42:52', '2025-01-14 14:14:55', '07:32:03', '01:44:55', '09:00:00', 'Tuesday', 1),
(62, 3, '2025-01-14', '2025-01-14 12:33:14', NULL, 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2025-01-14 07:03:14', '2025-01-14 07:03:14', NULL, NULL, '09:00:00', NULL, 0),
(63, 15, '2025-01-15', '2025-01-15 12:43:46', '2025-01-15 20:02:57', 127, '', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 0, 'present', NULL, 15, '2025-01-15 07:13:46', '2025-01-15 14:32:57', '07:19:11', '02:02:57', '09:00:00', 'Friday', 0),
(64, 2, '2025-01-15', '2025-01-15 13:11:50', '2025-01-15 20:02:06', 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2025-01-15 07:41:50', '2025-01-15 14:32:06', NULL, NULL, '09:00:00', NULL, 0),
(65, 3, '2025-01-15', '2025-01-15 16:03:38', '2025-01-15 20:02:27', 0, NULL, NULL, NULL, 0, 'present', NULL, NULL, '2025-01-15 10:33:39', '2025-01-15 14:32:27', NULL, NULL, '09:00:00', NULL, 0);

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
(37, 15, 3, 'hehehehe', 1, '2025-01-13 11:49:44', 0, NULL, NULL);

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
  `leave_type` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) DEFAULT NULL,
  `days` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `manager_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `hr_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `attachment` varchar(255) DEFAULT NULL,
  `half_day` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `modified_by` int(11) DEFAULT NULL,
  `pending_leaves` int(11) DEFAULT 0,
  `manager_id` int(11) DEFAULT NULL,
  `manager_comment` text DEFAULT NULL,
  `manager_action_date` datetime DEFAULT NULL,
  `hr_comment` text DEFAULT NULL,
  `hr_action_date` datetime DEFAULT NULL,
  `reporting_manager` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leaves`
--

INSERT INTO `leaves` (`id`, `user_id`, `leave_type_id`, `leave_type`, `start_date`, `end_date`, `total_days`, `days`, `reason`, `status`, `manager_status`, `hr_status`, `attachment`, `half_day`, `created_at`, `modified_at`, `modified_by`, `pending_leaves`, `manager_id`, `manager_comment`, `manager_action_date`, `hr_comment`, `hr_action_date`, `reporting_manager`) VALUES
(3, 21, 3, NULL, '2025-01-10', '2025-01-11', 2, NULL, 'testing', 'pending', 'pending', 'pending', NULL, 0, '2025-01-10 15:24:02', '2025-01-10 15:24:02', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 21, 4, NULL, '2025-01-11', '2025-01-12', 2, NULL, 'tetst', 'pending', 'pending', 'pending', NULL, 0, '2025-01-11 06:35:39', '2025-01-11 06:35:39', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 21, 3, NULL, '2025-01-12', '2025-01-13', 2, NULL, 'wda', 'pending', 'pending', 'pending', NULL, 0, '2025-01-12 10:19:54', '2025-01-12 10:19:54', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Triggers `leaves`
--
DELIMITER $$
CREATE TRIGGER `trg_after_leave_approval` AFTER UPDATE ON `leaves` FOR EACH ROW BEGIN
    IF NEW.status = 'approved' AND OLD.status != 'approved' THEN
        UPDATE leave_balance
        SET used_leaves = used_leaves + 
            CASE 
                WHEN NEW.half_day = 1 THEN 0.5
                ELSE DATEDIFF(NEW.end_date, NEW.start_date) + 1
            END
        WHERE user_id = NEW.user_id 
        AND leave_type_id = NEW.leave_type_id
        AND year = YEAR(NEW.start_date);
    END IF;
END
$$
DELIMITER ;

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

--
-- Dumping data for table `leave_request`
--

INSERT INTO `leave_request` (`id`, `user_id`, `leave_type`, `start_date`, `end_date`, `reason`, `duration`, `status`, `created_at`, `action_reason`, `action_by`, `action_at`, `updated_at`, `updated_by`, `action_comments`, `manager_approval`, `manager_action_reason`, `manager_action_by`, `manager_action_at`, `hr_approval`, `hr_action_reason`, `hr_action_by`, `hr_action_at`) VALUES
(4, 21, 3, '2025-01-12', '2025-01-12', 'Testing ', 1, 'approved', '2025-01-12 13:07:12', 'testing approved\n', 3, '2025-01-12 13:13:51', '2025-01-12 13:13:51', 3, 'testing approved\n', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 21, 3, '2025-01-12', '2025-01-14', 'sdgh', 3, 'approved', '2025-01-12 13:22:53', 'wery', 3, '2025-01-12 13:23:03', '2025-01-12 13:23:03', 3, 'wery', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 21, 11, '2025-01-12', '2025-01-13', 'wqefr', 2, 'approved', '2025-01-12 13:26:22', 'weert', 3, '2025-01-12 13:27:05', '2025-01-12 13:27:05', 3, 'weert', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 15, 11, '2025-01-14', '2025-01-14', 'dsfd', 1, 'approved', '2025-01-13 07:30:58', 'efe', 3, '2025-01-13 07:31:15', '2025-01-13 07:31:15', 3, 'efe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 15, 11, '2025-01-13', '2025-01-13', 'esfse', 1, 'approved', '2025-01-13 07:32:15', 'efe', 3, '2025-01-13 07:32:24', '2025-01-13 07:32:24', 3, 'efe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 15, 2, '2025-01-13', '2025-01-15', 'esfsef', 3, 'pending', '2025-01-13 11:08:58', NULL, NULL, NULL, '2025-01-13 11:08:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `leave_type` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `action_reason` text DEFAULT NULL,
  `action_by` int(11) DEFAULT NULL,
  `action_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `action_comments` text DEFAULT NULL
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
(3, 1, 'stage_forward', 29, 'A stage has been forwarded to you', NULL, '2025-01-10 15:25:49');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `contract_number` varchar(50) NOT NULL,
  `project_type` enum('Architecture','Interior','Construction') NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `client_guardian_name` varchar(100) DEFAULT NULL,
  `client_email` varchar(100) DEFAULT NULL,
  `client_mobile` varchar(20) NOT NULL,
  `got_project_from` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `contract_number`, `project_type`, `project_name`, `client_name`, `client_guardian_name`, `client_email`, `client_mobile`, `got_project_from`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'AH/DEC/001', 'Architecture', 'Villa at G.N.', 'ABc', 'BCD', 'abc@gmail.com', '9988776655', 3, 3, '2024-12-17 13:11:36', '2024-12-17 13:11:36'),
(2, 'AH/DEC/002', 'Interior', 'Test', 'hbc', 'hbds', 'ncs@gmail.com', '9955112233', 7, 3, '2024-12-18 06:09:53', '2024-12-18 06:09:53'),
(5, 'sefsef', 'Construction', 'sefse', 'sefse', 'esfes', 'info.architectshive@gmail.com', '', 2, 3, '2024-12-18 06:11:17', '2024-12-18 06:11:17'),
(6, 'awdaw', 'Architecture', 'wadaw', 'wadaw', 'awdaw', 'info.architectshive@gmail.com', '7788996655', 8, 3, '2024-12-18 06:15:56', '2024-12-18 06:15:56'),
(7, 'AH/DEC/003', 'Architecture', 'abc', 'abc', 'abc', 'info.architectshive@gmail.com', '9988774455', 7, 3, '2024-12-18 06:41:28', '2024-12-18 06:41:28');

-- --------------------------------------------------------

--
-- Table structure for table `project_stages`
--

CREATE TABLE `project_stages` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_stages`
--

INSERT INTO `project_stages` (`id`, `project_id`, `name`, `assigned_to`, `due_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'ABC', 2, '2024-12-17', 'in_progress', '2024-12-17 13:11:36', '2024-12-17 13:11:36'),
(2, 2, 'Hella ', 4, '2024-12-18', 'in_progress', '2024-12-18 06:09:53', '2024-12-18 06:09:53'),
(3, 5, 'sefs', 4, '2024-12-18', 'in_progress', '2024-12-18 06:11:17', '2024-12-18 06:11:17'),
(4, 6, 'awdaw', 7, '2024-12-18', 'in_progress', '2024-12-18 06:15:56', '2024-12-18 06:15:56'),
(5, 7, 'test', 3, '2024-12-19', 'in_progress', '2024-12-18 06:41:28', '2024-12-18 06:41:28');

-- --------------------------------------------------------

--
-- Table structure for table `project_sub_stages`
--

CREATE TABLE `project_sub_stages` (
  `id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_sub_stages`
--

INSERT INTO `project_sub_stages` (`id`, `stage_id`, `name`, `assigned_to`, `due_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'abc', 2, '2024-12-18', 'in_progress', '2024-12-17 13:11:36', '2024-12-17 13:11:36'),
(2, 2, 'sda', 3, '2024-12-18', 'in_progress', '2024-12-18 06:09:53', '2024-12-18 06:09:53'),
(3, 3, 'sefse', 1, '2024-12-18', '', '2024-12-18 06:11:17', '2024-12-18 06:11:17'),
(4, 3, 'sef', 2, '2024-12-18', 'in_progress', '2024-12-18 06:11:17', '2024-12-18 06:11:17'),
(5, 5, 'da', 3, '2024-12-19', 'completed', '2024-12-18 06:41:28', '2024-12-18 06:41:28');

-- --------------------------------------------------------

--
-- Table structure for table `project_team_members`
--

CREATE TABLE `project_team_members` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_team_members`
--

INSERT INTO `project_team_members` (`id`, `project_id`, `user_id`, `role`, `created_at`) VALUES
(1, 1, 1, 'Interior Designer', '2024-12-17 13:11:36'),
(2, 2, 3, 'Team Lead', '2024-12-18 06:09:53'),
(3, 5, 1, 'Architect', '2024-12-18 06:11:17'),
(4, 6, 1, 'Architect', '2024-12-18 06:15:56'),
(5, 7, 3, 'Architect', '2024-12-18 06:41:28');

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
(4, 'Winter Morning Shift', '09:00:00', '18:00:00', '2024-12-27 08:29:43', '2024-12-27 08:29:43');

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
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stage_files`
--

INSERT INTO `stage_files` (`id`, `stage_id`, `file_name`, `file_path`, `original_name`, `file_type`, `file_size`, `uploaded_by`, `uploaded_at`) VALUES
(1, 22, '676fbf7a5f8c0_My Profile Pic.png', 'uploads/stage_22/676fbf7a5f8c0_My Profile Pic.png', 'My Profile Pic.png', 'image/png', 242475, 3, '2024-12-28 09:06:02'),
(2, 23, '676fc1cfc8bfc_WhatsApp Image 2024-12-08 at 12.58.42 PM.jpeg', 'uploads/stage_23/676fc1cfc8bfc_WhatsApp Image 2024-12-08 at 12.58.42 PM.jpeg', 'WhatsApp Image 2024-12-08 at 12.58.42 PM.jpeg', 'image/jpeg', 186567, 3, '2024-12-28 09:15:59'),
(3, 24, '676fc2681e38e_WhatsApp Image 2024-11-29 at 12.01.29 PM.jpeg', 'uploads/stage_24/676fc2681e38e_WhatsApp Image 2024-11-29 at 12.01.29 PM.jpeg', 'WhatsApp Image 2024-11-29 at 12.01.29 PM.jpeg', 'image/jpeg', 1425534, 3, '2024-12-28 09:18:32'),
(4, 24, '676fc2681e7ea_WhatsApp Image 2024-11-29 at 12.01.29 PM (1).jpeg', 'uploads/stage_24/676fc2681e7ea_WhatsApp Image 2024-11-29 at 12.01.29 PM (1).jpeg', 'WhatsApp Image 2024-11-29 at 12.01.29 PM (1).jpeg', 'image/jpeg', 1362068, 3, '2024-12-28 09:18:32'),
(5, 26, '676fccc74542c_My Profile Pic.png', 'uploads/stage_26/676fccc74542c_My Profile Pic.png', 'My Profile Pic.png', 'image/png', 242475, 3, '2024-12-28 10:02:47'),
(6, 27, '677a754ddf0dc_arch (1).jpeg', 'uploads/stage_27/677a754ddf0dc_arch (1).jpeg', 'arch (1).jpeg', 'image/jpeg', 10566, 3, '2025-01-05 12:04:29'),
(7, 30, '6780d4c979677_my signature.jpg', 'uploads/stage_30/6780d4c979677_my signature.jpg', 'my signature.jpg', 'image/jpeg', 31834, 3, '2025-01-10 08:05:29'),
(8, 30, '6780d4c97cdff_my sign.jpg', 'uploads/stage_30/6780d4c97cdff_my sign.jpg', 'my sign.jpg', 'image/jpeg', 657058, 3, '2025-01-10 08:05:29'),
(9, 30, '6780d4c97d419_My Profile Pic.png', 'uploads/stage_30/6780d4c97d419_My Profile Pic.png', 'My Profile Pic.png', 'image/png', 242475, 3, '2025-01-10 08:05:29'),
(10, 33, '6780f99c41cd0_my sign.jpg', 'uploads/stage_33/6780f99c41cd0_my sign.jpg', 'my sign.jpg', 'image/jpeg', 657058, 3, '2025-01-10 10:42:36'),
(11, 33, '6780f99c430d7_my sign.jpg', 'uploads/stage_33/6780f99c430d7_my sign.jpg', 'my sign.jpg', 'image/jpeg', 657058, 3, '2025-01-10 10:42:36'),
(12, 33, '6780f99c43508_my sign.jpg', 'uploads/stage_33/6780f99c43508_my sign.jpg', 'my sign.jpg', 'image/jpeg', 657058, 3, '2025-01-10 10:42:36');

-- --------------------------------------------------------

--
-- Table structure for table `substage_files`
--

CREATE TABLE `substage_files` (
  `id` int(11) NOT NULL,
  `substage_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `substage_files`
--

INSERT INTO `substage_files` (`id`, `substage_id`, `file_name`, `file_path`, `original_name`, `file_type`, `file_size`, `uploaded_by`, `uploaded_at`) VALUES
(1, 22, '676fbf7a60b8e_my signature.jpg', 'uploads/substage_22/676fbf7a60b8e_my signature.jpg', 'my signature.jpg', 'image/jpeg', 31834, 3, '2024-12-28 09:06:02'),
(2, 23, '676fc1cfcb7a9_RACHANA PAL_CV (1).pdf', 'uploads/substage_23/676fc1cfcb7a9_RACHANA PAL_CV (1).pdf', 'RACHANA PAL_CV (1).pdf', 'application/pdf', 183690, 3, '2024-12-28 09:15:59'),
(3, 24, '676fc2681fde1_WhatsApp Image 2024-12-08 at 12.58.42 PM.jpeg', 'uploads/substage_24/676fc2681fde1_WhatsApp Image 2024-12-08 at 12.58.42 PM.jpeg', 'WhatsApp Image 2024-12-08 at 12.58.42 PM.jpeg', 'image/jpeg', 186567, 3, '2024-12-28 09:18:32'),
(4, 24, '676fc2682016e_WhatsApp Image 2024-11-29 at 12.01.29 PM (1).jpeg', 'uploads/substage_24/676fc2682016e_WhatsApp Image 2024-11-29 at 12.01.29 PM (1).jpeg', 'WhatsApp Image 2024-11-29 at 12.01.29 PM (1).jpeg', 'image/jpeg', 1362068, 3, '2024-12-28 09:18:32'),
(5, 26, '676fccc747c91_My Profile Pic.png', 'uploads/substage_26/676fccc747c91_My Profile Pic.png', 'My Profile Pic.png', 'image/png', 242475, 3, '2024-12-28 10:02:47'),
(6, 27, '677a754de67c6_arch (1).jpeg', 'uploads/substage_27/677a754de67c6_arch (1).jpeg', 'arch (1).jpeg', 'image/jpeg', 10566, 3, '2025-01-05 12:04:29'),
(7, 34, '6780d4c97f3a4_my signature.jpg', 'uploads/substage_34/6780d4c97f3a4_my signature.jpg', 'my signature.jpg', 'image/jpeg', 31834, 3, '2025-01-10 08:05:29'),
(8, 34, '6780d4c980970_my signature.jpg', 'uploads/substage_34/6780d4c980970_my signature.jpg', 'my signature.jpg', 'image/jpeg', 31834, 3, '2025-01-10 08:05:29'),
(9, 34, '6780d4c980e04_my signature.jpg', 'uploads/substage_34/6780d4c980e04_my signature.jpg', 'my signature.jpg', 'image/jpeg', 31834, 3, '2025-01-10 08:05:29'),
(10, 34, '6780d4c982144_my sign.jpg', 'uploads/substage_34/6780d4c982144_my sign.jpg', 'my sign.jpg', 'image/jpeg', 657058, 3, '2025-01-10 08:05:29'),
(11, 34, '6780d4c98263b_my signature.jpg', 'uploads/substage_34/6780d4c98263b_my signature.jpg', 'my signature.jpg', 'image/jpeg', 31834, 3, '2025-01-10 08:05:29'),
(12, 34, '6780d4c982a5e_my signature.jpg', 'uploads/substage_34/6780d4c982a5e_my signature.jpg', 'my signature.jpg', 'image/jpeg', 31834, 3, '2025-01-10 08:05:29'),
(13, 34, '6780d4c982e6f_my sign.jpg', 'uploads/substage_34/6780d4c982e6f_my sign.jpg', 'my sign.jpg', 'image/jpeg', 657058, 3, '2025-01-10 08:05:29'),
(14, 34, '6780d4c9832cb_my signature.jpg', 'uploads/substage_34/6780d4c9832cb_my signature.jpg', 'my signature.jpg', 'image/jpeg', 31834, 3, '2025-01-10 08:05:29'),
(15, 42, '6780f99c44b06_my sign.jpg', 'uploads/substage_42/6780f99c44b06_my sign.jpg', 'my sign.jpg', 'image/jpeg', 657058, 3, '2025-01-10 10:42:36'),
(16, 42, '6780f99c44f30_my sign.jpg', 'uploads/substage_42/6780f99c44f30_my sign.jpg', 'my sign.jpg', 'image/jpeg', 657058, 3, '2025-01-10 10:42:36'),
(17, 42, '6780f99c452ac_my sign.jpg', 'uploads/substage_42/6780f99c452ac_my sign.jpg', 'my sign.jpg', 'image/jpeg', 657058, 3, '2025-01-10 10:42:36'),
(18, 42, '6780f99c45644_My Profile Pic.png', 'uploads/substage_42/6780f99c45644_My Profile Pic.png', 'My Profile Pic.png', 'image/png', 242475, 3, '2025-01-10 10:42:36'),
(19, 42, '6780f99c4657c_My Profile Pic.jpg', 'uploads/substage_42/6780f99c4657c_My Profile Pic.jpg', 'My Profile Pic.jpg', 'image/jpeg', 243840, 3, '2025-01-10 10:42:36'),
(20, 42, '6780f99c468ce_my sign.jpg', 'uploads/substage_42/6780f99c468ce_my sign.jpg', 'my sign.jpg', 'image/jpeg', 657058, 3, '2025-01-10 10:42:36');

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
  `status` enum('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  `due_date` date NOT NULL,
  `priority_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `title`, `description`, `created_by`, `category_id`, `priority`, `status`, `due_date`, `priority_id`, `status_id`, `created_at`, `updated_at`) VALUES
(19, 'test', 'Testing Project', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2024-12-23 08:11:48', '2024-12-23 08:11:48'),
(20, 'test', 'testing dude', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2024-12-26 08:06:44', '2024-12-26 08:06:44'),
(26, 'sda', 'sadasd', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2024-12-26 08:24:33', '2024-12-26 08:24:33'),
(29, 'szd', 'zsdzsdzdzs', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2024-12-26 08:32:18', '2024-12-26 08:32:18'),
(30, 'sfsdfsdfdsfds', 'sdfsdfergththytrhrbyytry', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2024-12-26 08:38:30', '2024-12-26 08:38:30'),
(31, 'efsefsefse', 'sefsefs', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2024-12-26 08:44:30', '2024-12-26 08:44:30'),
(32, 'sada', 'asdasfsdfd', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2024-12-26 09:18:20', '2024-12-26 09:18:20'),
(33, 'dwdwd', 'wdwdwdwd', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2024-12-28 09:06:02', '2024-12-28 09:06:02'),
(34, 'hello world', 'heel nah', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2024-12-28 09:15:59', '2024-12-28 09:15:59'),
(35, 'hell yeah', 'hfsh', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2024-12-28 09:18:32', '2024-12-28 09:18:32'),
(36, 'task test', 'uwduhd', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2024-12-28 10:02:47', '2024-12-28 10:02:47'),
(37, 'testing in jan', 'jan testing', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-05 12:04:29', '2025-01-05 12:04:29'),
(38, 'Real Testing', 'Testing For the two substages', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-09 08:29:11', '2025-01-09 08:29:11'),
(39, 'Abc Testing', 'On testing', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-10 08:05:29', '2025-01-10 08:05:29'),
(40, 'sequence 1', 'sequence check', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-10 10:42:36', '2025-01-10 10:42:36'),
(41, 'wd', 'awd', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-11 07:19:41', '2025-01-11 07:19:41'),
(42, 'sa', 'sd', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-11 07:24:11', '2025-01-11 07:24:11'),
(43, 'sadas', 'sadas', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-11 07:47:25', '2025-01-11 07:47:25'),
(44, 'sdf', 'sdf', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-11 10:11:31', '2025-01-11 10:11:31'),
(45, 'aS', 'SAD', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-12 09:28:21', '2025-01-12 09:28:21'),
(46, 'sad', 'asd', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-12 09:39:11', '2025-01-12 09:39:11'),
(47, 'sd', 'asdas', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-12 11:26:47', '2025-01-12 11:26:47'),
(48, 'drg', 'rdgd', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-12 15:56:53', '2025-01-12 15:56:53'),
(49, 'adw', 'awdaw', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-13 11:01:04', '2025-01-13 11:01:04'),
(50, 'abc d', 'jfsen', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-15 10:55:24', '2025-01-15 10:55:24'),
(51, 'ewrew', 'erwer', 3, 1, 'medium', 'pending', '0000-00-00', 0, 1, '2025-01-15 11:11:39', '2025-01-15 11:11:39');

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

--
-- Dumping data for table `task_comments`
--

INSERT INTO `task_comments` (`id`, `task_id`, `stage_number`, `substage_id`, `comment_text`, `created_by`, `created_at`) VALUES
(1, 35, 1, 24, 'hello there it is a testing message', 21, '2024-12-28 13:19:32'),
(2, 20, 1, NULL, 'hola', 21, '2024-12-31 09:28:53'),
(3, 35, NULL, NULL, 'test again', 21, '2025-01-02 12:49:31'),
(4, 35, NULL, NULL, 'test again', 21, '2025-01-02 12:50:09'),
(5, 35, NULL, NULL, 'testing', 21, '2025-01-04 07:29:27'),
(6, 35, NULL, NULL, 'kepe', 21, '2025-01-04 07:50:06'),
(7, 35, NULL, NULL, 'hola', 21, '2025-01-04 08:03:26'),
(8, 35, NULL, NULL, 'hola', 21, '2025-01-04 08:09:05'),
(9, 35, NULL, NULL, 'wallah', 21, '2025-01-04 08:10:40'),
(10, 35, NULL, NULL, 'well well', 21, '2025-01-04 08:13:25'),
(11, 35, NULL, NULL, 'keke', 21, '2025-01-04 08:19:04'),
(12, 35, NULL, NULL, 'wew', 21, '2025-01-04 08:19:31'),
(13, 34, NULL, NULL, 'hello', 21, '2025-01-04 08:29:04'),
(15, 37, NULL, NULL, 'dwd', 21, '2025-01-05 12:43:22');

-- --------------------------------------------------------

--
-- Table structure for table `task_history`
--

CREATE TABLE `task_history` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime NOT NULL
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
  `status` enum('not_started','in_progress','completed','delayed') DEFAULT 'not_started',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `start_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_stages`
--

INSERT INTO `task_stages` (`id`, `task_id`, `stage_number`, `assigned_to`, `due_date`, `status`, `created_at`, `updated_at`, `priority`, `start_date`) VALUES
(14, 19, 1, 21, '2024-12-23', 'not_started', '2024-12-23 08:11:48', '2024-12-23 08:11:48', 'high', NULL),
(15, 20, 1, 21, '2024-12-27', 'not_started', '2024-12-26 08:06:44', '2024-12-26 08:06:44', 'high', NULL),
(18, 29, 0, 21, '2024-12-28', 'not_started', '2024-12-26 08:32:18', '2024-12-26 08:32:18', 'high', '2024-12-26 14:01:00'),
(19, 30, 0, 21, '2024-12-29', 'not_started', '2024-12-26 08:38:30', '2024-12-26 08:38:30', 'high', '2024-12-26 14:08:00'),
(20, 31, 0, 21, '2024-12-28', 'not_started', '2024-12-26 08:44:30', '2024-12-26 08:44:30', 'high', '2024-12-26 14:14:00'),
(21, 32, 0, 21, '2024-12-28', 'not_started', '2024-12-26 09:18:20', '2024-12-26 09:18:20', 'high', '2024-12-26 14:47:00'),
(22, 33, 0, 21, '2024-12-28', 'not_started', '2024-12-28 09:06:02', '2024-12-28 09:06:02', 'high', '2024-12-28 14:35:00'),
(23, 34, 1, 21, '2024-12-31', 'not_started', '2024-12-28 09:15:59', '2024-12-28 09:15:59', 'high', '2024-12-28 14:45:00'),
(24, 35, 1, 21, '2024-12-31', 'not_started', '2024-12-28 09:18:32', '2024-12-28 09:18:32', 'high', '2024-12-28 14:47:00'),
(25, 35, 2, 15, '2024-12-31', 'not_started', '2024-12-28 09:18:32', '2024-12-28 09:18:32', 'high', '2024-12-28 14:48:00'),
(26, 36, 1, 21, '2024-12-31', 'not_started', '2024-12-28 10:02:47', '2024-12-28 10:02:47', 'low', '2024-12-28 15:32:00'),
(27, 37, 1, 21, '2025-01-08', 'completed', '2025-01-05 12:04:29', '2025-01-14 07:02:16', 'high', '2025-01-05 17:33:00'),
(28, 38, 1, 21, '2025-01-12', 'completed', '2025-01-09 08:29:11', '2025-01-10 07:32:28', 'high', '2025-01-09 13:56:00'),
(29, 38, 2, 1, '2025-01-12', 'completed', '2025-01-09 08:29:11', '2025-01-10 15:25:49', 'medium', '2025-01-09 13:58:00'),
(30, 39, 1, 21, '2025-01-12', 'completed', '2025-01-10 08:05:29', '2025-01-10 12:05:49', 'low', '2025-01-10 13:32:00'),
(31, 39, 2, 15, '2025-01-12', 'not_started', '2025-01-10 08:05:29', '2025-01-10 08:05:29', 'high', '2025-01-10 13:33:00'),
(32, 39, 3, 21, '2025-01-12', 'not_started', '2025-01-10 08:05:29', '2025-01-10 08:05:29', 'high', '2025-01-10 13:34:00'),
(33, 40, 1, 21, '2025-01-12', 'completed', '2025-01-10 10:42:36', '2025-01-10 11:25:33', 'high', '2025-01-10 16:10:00'),
(34, 40, 2, 21, '2025-01-12', 'not_started', '2025-01-10 10:42:36', '2025-01-10 10:42:36', 'medium', '2025-01-10 16:10:00'),
(35, 40, 3, 15, '2025-01-12', 'not_started', '2025-01-10 10:42:36', '2025-01-10 10:42:36', 'high', '2025-01-10 16:11:00'),
(36, 41, 1, 21, '2025-01-12', 'not_started', '2025-01-11 07:19:41', '2025-01-11 07:19:41', 'high', '2025-01-11 12:49:00'),
(37, 42, 1, 21, '2025-01-12', 'not_started', '2025-01-11 07:24:11', '2025-01-11 07:24:11', 'high', '2025-01-11 12:53:00'),
(38, 43, 1, 21, '2025-01-12', 'not_started', '2025-01-11 07:47:25', '2025-01-11 07:47:25', 'high', '2025-01-11 19:23:00'),
(39, 44, 1, 21, '2025-01-12', 'not_started', '2025-01-11 10:11:31', '2025-01-11 10:11:31', 'high', '2025-01-11 15:41:00'),
(40, 45, 1, 21, '2025-01-19', 'not_started', '2025-01-12 09:28:21', '2025-01-12 09:28:21', 'high', '2025-01-12 14:58:00'),
(41, 46, 1, 21, '2025-01-13', 'not_started', '2025-01-12 09:39:11', '2025-01-12 09:39:11', 'high', '2025-01-12 15:08:00'),
(42, 47, 1, 21, '2025-01-13', 'not_started', '2025-01-12 11:26:47', '2025-01-12 11:26:47', 'high', '2025-01-12 16:56:00'),
(43, 48, 1, 15, '2025-01-13', 'not_started', '2025-01-12 15:56:53', '2025-01-12 15:56:53', 'high', '2025-01-12 21:26:00'),
(44, 49, 1, 21, '2025-01-14', 'not_started', '2025-01-13 11:01:04', '2025-01-13 11:01:04', 'high', '2025-01-13 16:30:00'),
(45, 50, 1, 15, '2025-01-17', 'not_started', '2025-01-15 10:55:24', '2025-01-15 10:55:24', 'high', '2025-01-15 16:25:00'),
(46, 51, 1, 21, '2025-01-16', 'not_started', '2025-01-15 11:11:39', '2025-01-15 11:11:39', 'high', '2025-01-15 16:40:00');

-- --------------------------------------------------------

--
-- Table structure for table `task_stage_history`
--

CREATE TABLE `task_stage_history` (
  `id` int(11) NOT NULL,
  `stage_id` int(11) NOT NULL,
  `status` enum('pending','in_progress','completed') NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` datetime DEFAULT current_timestamp()
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
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_status_history`
--

INSERT INTO `task_status_history` (`id`, `entity_type`, `entity_id`, `old_status`, `new_status`, `changed_by`, `changed_at`) VALUES
(1, 'stage', 30, 'in_progress', 'completed', 21, '2025-01-10 12:05:49'),
(2, 'substage', 34, 'pending', 'in_progress', 21, '2025-01-10 12:05:59'),
(3, 'substage', 34, '', 'completed', 21, '2025-01-10 12:06:08'),
(4, 'substage', 34, 'completed', 'pending', 21, '2025-01-10 12:08:40'),
(5, 'substage', 34, 'pending', 'completed', 21, '2025-01-10 12:09:15'),
(6, 'substage', 34, 'completed', 'in_progress', 21, '2025-01-10 12:10:29'),
(7, 'substage', 34, '', 'in_progress', 21, '2025-01-10 12:13:21'),
(8, 'substage', 34, '', 'in_progress', 21, '2025-01-10 12:13:48'),
(9, 'substage', 34, '', 'pending', 21, '2025-01-10 12:14:03'),
(10, 'substage', 34, 'pending', 'completed', 21, '2025-01-10 12:14:16'),
(11, 'substage', 34, 'completed', 'in_progress', 21, '2025-01-10 12:14:35'),
(12, 'substage', 34, '', 'completed', 21, '2025-01-10 12:15:34'),
(13, 'substage', 34, 'completed', 'pending', 21, '2025-01-10 12:15:50'),
(14, 'substage', 27, 'completed', 'pending', 21, '2025-01-10 12:22:21'),
(16, 'substage', 27, 'pending', 'completed', 21, '2025-01-10 12:42:27'),
(17, 'substage', 27, 'completed', 'pending', 21, '2025-01-10 12:44:42'),
(18, 'substage', 27, 'pending', 'pending', 21, '2025-01-10 12:47:34'),
(19, 'substage', 27, 'pending', 'in_progress', 21, '2025-01-10 12:51:15'),
(20, 'substage', 27, '', 'completed', 21, '2025-01-10 12:59:14'),
(21, 'substage', 34, 'pending', 'completed', 21, '2025-01-10 15:26:05'),
(22, 'stage', 27, 'completed', 'pending', 21, '2025-01-10 15:37:04'),
(23, 'stage', 27, '', 'completed', 2, '2025-01-14 07:00:07'),
(24, 'substage', 27, 'completed', 'in_progress', 21, '2025-01-14 07:01:18'),
(25, 'substage', 27, '', 'in_progress', 21, '2025-01-14 07:01:28'),
(26, 'substage', 27, '', 'completed', 21, '2025-01-14 07:01:37'),
(27, 'stage', 27, 'completed', 'pending', 21, '2025-01-14 07:01:48'),
(28, 'stage', 27, '', 'in_progress', 21, '2025-01-14 07:02:03'),
(29, 'stage', 27, 'in_progress', 'completed', 21, '2025-01-14 07:02:16'),
(30, 'substage', 27, 'completed', 'in_progress', 21, '2025-01-14 07:29:09'),
(31, 'substage', 27, '', 'completed', 21, '2025-01-14 07:29:33'),
(32, 'substage', 27, 'completed', 'in_progress', 21, '2025-01-14 13:38:26'),
(33, 'substage', 27, '', 'completed', 21, '2025-01-14 13:39:05');

-- --------------------------------------------------------

--
-- Table structure for table `task_substages`
--

CREATE TABLE `task_substages` (
  `id` int(11) NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_substages`
--

INSERT INTO `task_substages` (`id`, `stage_id`, `description`, `status`, `created_at`, `updated_at`, `priority`, `start_date`, `end_date`) VALUES
(16, 14, 'Abc Drwaings', 'pending', '2024-12-23 08:11:48', '2024-12-23 08:11:48', 'high', NULL, NULL),
(17, 15, 'testing', 'pending', '2024-12-26 08:06:44', '2024-12-26 08:06:44', 'high', NULL, NULL),
(18, 18, 'zsd', 'pending', '2024-12-26 08:32:18', '2024-12-26 08:32:18', 'high', '2024-12-26 14:02:00', '2024-12-28 14:02:00'),
(19, 19, 'safsgghdg', 'pending', '2024-12-26 08:38:30', '2024-12-26 08:38:30', 'high', '2024-12-26 14:08:00', '2024-12-29 20:14:00'),
(20, 20, 'esfgrsgrs', 'pending', '2024-12-26 08:44:30', '2024-12-26 08:44:30', 'high', '2024-12-26 14:14:00', '2024-12-28 20:20:00'),
(21, 21, 'sdfsdf', 'pending', '2024-12-26 09:18:20', '2024-12-26 09:18:20', 'high', '2024-12-26 14:48:00', '2024-12-28 20:48:00'),
(22, 22, 'wdwdw', 'pending', '2024-12-28 09:06:02', '2024-12-28 09:06:02', 'high', '2024-12-28 14:35:00', '2024-12-31 20:41:00'),
(23, 23, 'dfsfsd', 'pending', '2024-12-28 09:15:59', '2024-12-28 09:15:59', 'low', '2024-12-28 14:45:00', '2024-12-31 20:51:00'),
(24, 24, 'efesf', 'pending', '2024-12-28 09:18:32', '2024-12-28 09:18:32', 'low', '2024-12-28 14:47:00', '2024-12-31 20:53:00'),
(25, 25, 'dadw', 'pending', '2024-12-28 09:18:32', '2024-12-28 09:18:32', 'low', '2024-12-28 14:48:00', '2024-12-31 14:48:00'),
(26, 26, 'gvjhbknl', 'pending', '2024-12-28 10:02:47', '2024-12-28 10:02:47', 'high', '2024-12-28 15:32:00', '2024-12-31 21:38:00'),
(27, 27, 'hola amigo', 'completed', '2025-01-05 12:04:29', '2025-01-14 13:39:05', 'high', '2025-01-05 17:34:00', '2025-01-07 23:40:00'),
(28, 28, 'ss1', 'completed', '2025-01-09 08:29:11', '2025-01-10 07:19:51', 'low', '2025-01-09 13:57:00', '2025-01-12 16:00:00'),
(29, 28, 'ss2', 'completed', '2025-01-09 08:29:11', '2025-01-09 10:06:19', 'medium', '2025-01-09 13:57:00', '2025-01-12 16:00:00'),
(30, 28, 'ss3', 'completed', '2025-01-09 08:29:11', '2025-01-09 12:25:10', 'high', '2025-01-09 13:57:00', '2025-01-12 16:00:00'),
(31, 29, 'ss1', 'completed', '2025-01-09 08:29:11', '2025-01-09 11:38:29', 'low', '2025-01-09 13:58:00', '2025-01-12 17:58:00'),
(32, 29, 'ss2', 'completed', '2025-01-09 08:29:11', '2025-01-09 11:54:57', 'medium', '2025-01-09 13:58:00', '2025-01-12 19:03:00'),
(33, 29, 'ss3', 'completed', '2025-01-09 08:29:11', '2025-01-09 11:55:08', 'high', '2025-01-09 13:59:00', '2025-01-12 19:04:00'),
(34, 30, 'ss1', 'completed', '2025-01-10 08:05:29', '2025-01-10 15:26:05', 'high', '2025-01-10 13:32:00', '2025-01-12 19:38:00'),
(35, 30, 'ss2', 'pending', '2025-01-10 08:05:29', '2025-01-10 08:05:29', 'medium', '2025-01-10 13:32:00', '2025-01-12 19:38:00'),
(36, 30, 'ss3', 'pending', '2025-01-10 08:05:29', '2025-01-10 08:05:29', 'low', '2025-01-10 13:33:00', '2025-01-10 19:39:00'),
(37, 31, 'sss1', 'pending', '2025-01-10 08:05:29', '2025-01-10 08:05:29', 'high', '2025-01-10 13:33:00', '2025-01-12 19:39:00'),
(38, 31, 'ss2', 'pending', '2025-01-10 08:05:29', '2025-01-10 08:05:29', 'medium', '2025-01-10 13:34:00', '2025-01-12 19:40:00'),
(39, 31, 'ss3', 'pending', '2025-01-10 08:05:29', '2025-01-10 08:05:29', 'low', '2025-01-10 13:34:00', '2025-01-17 19:40:00'),
(40, 32, 'ss13', 'pending', '2025-01-10 08:05:29', '2025-01-10 08:05:29', 'high', '2025-01-10 13:34:00', '2025-01-10 19:41:00'),
(41, 32, 'ss14', 'pending', '2025-01-10 08:05:29', '2025-01-10 08:05:29', 'medium', '2025-01-10 13:35:00', '2025-01-10 19:41:00'),
(42, 33, 'se1', 'pending', '2025-01-10 10:42:36', '2025-01-10 10:42:36', 'high', '2025-01-10 16:10:00', '2025-01-12 22:16:00'),
(43, 33, 'se2', 'pending', '2025-01-10 10:42:36', '2025-01-10 10:42:36', 'medium', '2025-01-10 16:10:00', '2025-01-12 22:16:00'),
(44, 34, 'sse1', 'pending', '2025-01-10 10:42:36', '2025-01-10 10:42:36', 'high', '2025-01-10 16:11:00', '2025-01-15 16:11:00'),
(45, 34, 'sse2', 'pending', '2025-01-10 10:42:36', '2025-01-10 10:42:36', 'medium', '2025-01-10 16:11:00', '2025-01-12 22:17:00'),
(46, 35, 'sse1', 'completed', '2025-01-10 10:42:36', '2025-01-10 11:17:32', 'low', '2025-01-10 16:12:00', '2025-01-12 21:17:00'),
(47, 35, 'sse2', 'pending', '2025-01-10 10:42:36', '2025-01-10 10:42:36', 'medium', '2025-01-10 16:12:00', '2025-01-12 22:18:00'),
(48, 36, 'wd', 'pending', '2025-01-11 07:19:41', '2025-01-11 07:19:41', 'high', '2025-01-11 12:49:00', '2025-01-12 18:49:00'),
(49, 37, 's', 'pending', '2025-01-11 07:24:11', '2025-01-11 07:24:11', 'high', '2025-01-11 19:54:00', '2025-01-12 18:00:00'),
(50, 38, 'sadas', 'pending', '2025-01-11 07:47:25', '2025-01-11 07:47:25', 'high', '2025-01-11 13:17:00', '2025-01-12 19:23:00'),
(51, 39, 'ds', 'pending', '2025-01-11 10:11:31', '2025-01-11 10:11:31', 'high', '2025-01-11 15:41:00', '2025-01-12 21:47:00'),
(52, 40, 'SADAS', 'pending', '2025-01-12 09:28:21', '2025-01-12 09:28:21', 'medium', '2025-01-12 14:58:00', '2025-01-19 18:58:00'),
(53, 41, 'asd', 'pending', '2025-01-12 09:39:11', '2025-01-12 09:39:11', 'high', '2025-01-12 15:09:00', '2025-01-18 18:12:00'),
(54, 42, 'dasdas', 'pending', '2025-01-12 11:26:47', '2025-01-12 11:26:47', 'high', '2025-01-12 16:56:00', '2025-01-13 19:59:00'),
(55, 43, 'drgdr', 'pending', '2025-01-12 15:56:53', '2025-01-12 15:56:53', 'high', '2025-01-12 21:26:00', '2025-01-13 00:26:00'),
(56, 44, 'awdaw', 'pending', '2025-01-13 11:01:04', '2025-01-13 11:01:04', 'high', '2025-01-13 16:30:00', '2025-01-14 16:30:00'),
(57, 45, 'efesf', 'pending', '2025-01-15 10:55:24', '2025-01-15 10:55:24', 'medium', '2025-01-15 16:25:00', '2025-01-16 18:27:00'),
(58, 46, 'fsdd', 'pending', '2025-01-15 11:11:39', '2025-01-15 11:11:39', 'high', '2025-01-15 16:41:00', '2025-01-16 19:44:00'),
(59, 46, 'reter', 'pending', '2025-01-15 11:11:39', '2025-01-15 11:11:39', 'high', '2025-01-15 16:41:00', '2025-01-16 19:41:00');

-- --------------------------------------------------------

--
-- Table structure for table `task_substage_history`
--

CREATE TABLE `task_substage_history` (
  `id` int(11) NOT NULL,
  `substage_id` int(11) NOT NULL,
  `status` enum('pending','in_progress','completed') NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `task_timeline`
--

CREATE TABLE `task_timeline` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `substage_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `changes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`changes`)),
  `comments` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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
  `education_background` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`education_background`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `position`, `email`, `employee_id`, `phone_number`, `designation`, `department`, `password`, `role`, `unique_id`, `reporting_manager`, `created_at`, `updated_at`, `deleted_at`, `status`, `last_login`, `profile_image`, `address`, `emergency_contact`, `joining_date`, `modified_at`, `city`, `state`, `country`, `postal_code`, `emergency_contact_name`, `emergency_contact_phone`, `phone`, `dob`, `profile_picture`, `bio`, `gender`, `marital_status`, `nationality`, `languages`, `social_media`, `skills`, `interests`, `blood_group`, `education`, `work_experience`, `bank_details`, `shift_id`, `documents`, `work_experiences`, `education_background`) VALUES
(1, 'Aditya Pal', 'Principal Architect', 'aaadityapal69@gmail.com', 'EMP0001', NULL, 'IT Support', NULL, '$2y$10$TkB2JPXzRLntod.z0/118ecgqbgYey8Vy7zbJb0ityfZJ8/27yiU.', 'admin', 'ADM001', 'Sr. Manager (Studio)', '2024-12-16 12:38:03', '2025-01-11 13:06:27', NULL, 'Active', '2024-12-27 12:33:00', NULL, 'dsa', NULL, '2025-01-11', '2025-01-11 13:06:27', '', '', '', '', '', '', '7224864553', '2001-10-24', NULL, NULL, 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '{\"account_holder\":\"\",\"bank_name\":\"\",\"account_number\":\"\",\"ifsc_code\":\"\",\"branch_name\":\"\",\"account_type\":\"savings\"}', NULL, '{\"aadhar\":{\"filename\":\"1_aadhar_67826cd3662c5.pdf\",\"original_name\":\"RACHANA PAL_CV (1).pdf\",\"uploaded_at\":\"2025-01-11 18:36:27\"}}', NULL, NULL),
(2, 'Gunjan Sehgal', 'HR', 'hr.architectshive@gmail.com', NULL, NULL, 'Human Resources', NULL, '$2y$10$IJ14sW0I9TEJYaVCxf9.yuFZZlET3kH8vwpQUGkNg5cUu0FQa7GRG', 'HR', 'HR001', 'Sr. Manager (Studio)', '2024-12-16 13:07:28', '2025-01-15 14:33:14', NULL, 'Active', '2025-01-15 20:03:14', '676e5805148ee.png', '', NULL, '2025-01-15', '2025-01-15 14:33:14', '', '', '', '', '', '', '7224864553', '2024-12-23', 'uploads/profile_pictures/profile_67814597a299c.png', NULL, 'female', '', '', '', 'null', '', NULL, '', 'null', 'null', 'null', NULL, NULL, NULL, NULL),
(3, 'Yojna Sharma', NULL, 'yojnasharma2009@gmail.com', NULL, NULL, '', NULL, '$2y$10$rdsbXtR/qSCAJHC0rlbMYOCcMdaq6U5NHmV2FeKemXUXejGXUap1e', 'Senior Manager (Studio)', 'SMS001', '', '2024-12-17 06:41:36', '2025-01-15 14:32:25', NULL, 'Active', '2025-01-15 20:02:25', NULL, NULL, NULL, NULL, '2025-01-15 14:32:25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0000-00-00', 'uploads/profile_pictures/6783e006aec5c_wallpaperflare.com_wallpaper.jpg', '', '', 'married', 'Indian', '', NULL, '', '', 'A+', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'John Doe', NULL, '', NULL, NULL, '', NULL, 'password123', 'Senior Manager (Studio)', '', NULL, '2024-12-17 06:49:44', '2024-12-17 06:56:05', NULL, 'Active', NULL, NULL, NULL, NULL, NULL, '2024-12-17 06:49:44', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'Preeti', 'Junior Architect', 'arch.preeti@gmail.com', NULL, NULL, 'Architecture Design', NULL, '$2y$10$pE9NEK7/IbfSc.m2o7f0.eSbR8/YF6rMPjqecM9agUSyA28f7T1Lu', 'Working Team', 'WT001', 'Sr. Manager (Studio)', '2024-12-17 09:04:26', '2025-01-12 09:14:47', NULL, 'Active', NULL, NULL, NULL, NULL, '2024-04-26', '2025-01-12 09:14:47', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1999-08-05', NULL, NULL, 'female', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'test', NULL, 'test@gmail.com', NULL, NULL, '', NULL, '$2y$10$ByGbUoLCdWMpB/XSG2Mhju88hD09bDY4Czue2Ll/9l7C2QkKLzDCy', '3D Designing Team', '3DT001', 'Sr. Manager (Studio)', '2024-12-17 10:42:24', '2024-12-17 10:42:24', NULL, 'Active', NULL, NULL, NULL, NULL, NULL, '2024-12-17 10:42:24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'Rachana Pal', NULL, 'arch.rachanapal@gmail.com', NULL, NULL, '', NULL, '$2y$10$fcFm76vgXeXSCu.52PhM9OGaCZzkwOYjkLxxZqLx0Q0QKMUR1T5Yi', 'Senior Manager (Studio)', 'SMS002', '', '2024-12-18 06:50:47', '2024-12-18 12:37:12', NULL, 'Active', '2024-12-18 13:37:12', NULL, NULL, NULL, NULL, '2024-12-18 12:37:12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'Manish Prajapati', 'Junior Architect', 'manish@architectshive.com', NULL, NULL, 'Interior Design', NULL, '$2y$10$PNldyXlZaPlzXxYRYr6Z9OZa/vo/y1fRMDru6ESac77CLZXbTvjca', 'Design Team', 'DT001', 'Sr. Manager (Studio)', '2024-12-18 07:45:15', '2025-01-15 14:32:54', NULL, 'Active', '2025-01-15 20:02:54', NULL, NULL, NULL, NULL, '2025-01-15 14:32:54', NULL, NULL, NULL, NULL, NULL, NULL, '8855223322', '2001-02-22', 'uploads/profile_pictures/6781224d207ab_wallpaperflare.com_wallpaper.jpg', '', 'female', '', '', '', '{\"linkedin\":\"\",\"twitter\":\"\",\"facebook\":\"\",\"instagram\":\"\",\"github\":\"\",\"youtube\":\"\"}', '', '', '', '{\"highest_degree\":\"\",\"institution\":\"\",\"field_of_study\":\"\",\"graduation_year\":\"\"}', '{\"current_company\":\"\",\"job_title\":\"\",\"experience_years\":\"\",\"responsibilities\":\"\"}', '{\"bank_name\":\"\",\"account_holder\":\"\",\"account_number\":\"\",\"ifsc_code\":\"\"}', 4, NULL, NULL, NULL),
(21, 'Aditya Kumar Pal', 'Office Manager', 'aaadityapal69@icloud.com', NULL, NULL, 'IT Support', NULL, '$2y$10$9iAXnlBQEP0rpiW82X2jfO.7F9A7VpHzPmt/2C2L/2sxZDwjuQcVa', 'Design Team', 'DT002', 'Sr. Manager (Studio)', '2024-12-18 09:29:49', '2025-01-15 12:53:37', NULL, 'Active', '2025-01-15 18:23:37', NULL, 'F-48 Street Number 16, Madhu Vihar', NULL, '2025-01-01', '2025-01-15 12:53:37', 'Delhi NCR', 'Delhi', 'India', '110092', 'Aditya Pal', '07224864553', '1122334455', '2001-10-24', 'uploads/profile_pictures/67837303da8d8_photo_2024-12-31_16-34-42.jpg', '', 'male', 'single', 'Indian', 'English', '{\"linkedin\":\"\",\"twitter\":\"\",\"facebook\":\"\",\"instagram\":\"\",\"github\":\"\",\"youtube\":\"\"}', '', '', 'O+', '{\"highest_degree\":\"high_school\",\"institution\":\"\",\"field_of_study\":\"\",\"graduation_year\":\"\"}', '{\"current_company\":\"ArchitectsHive\",\"job_title\":\"Senior Manager\",\"experience_years\":\"2\",\"responsibilities\":\"Working there as a senior manager for 2 years\\n\"}', '{\"account_holder\":\"Aditya Kumar Pal\",\"bank_name\":\"State Bank Of India\",\"account_number\":\"6969696969\",\"ifsc_code\":\"SBIN0003965\",\"branch_name\":\"hola amigo\",\"account_type\":\"savings\"}', 4, '[{\"type\":\"aadhar\",\"filename\":\"RACHANA PAL_CV.pdf\",\"file_path\":\"uploads\\/documents\\/67837548c5957_RACHANA PAL_CV.pdf\",\"upload_date\":\"2025-01-12 13:24:48\"},{\"type\":\"pan\",\"filename\":\"Agreement_2024-12-02_100746.pdf\",\"file_path\":\"uploads\\/documents\\/67837e6651576_Agreement_2024-12-02_100746.pdf\",\"upload_date\":\"2025-01-12 14:03:42\"},{\"type\":\"passport\",\"filename\":\"salary_report_2024-11 (2).pdf\",\"file_path\":\"uploads\\/documents\\/678381c3b089b_salary_report_2024-11 (2).pdf\",\"upload_date\":\"2025-01-12 14:18:03\"}]', '[{\"current_company\":\"ArchitectsHive\",\"job_title\":\"Senior IT manager\",\"experience_years\":\"2\",\"responsibilities\":\"Managing all the technical Stuff and work as a Full stack Developer\\r\\n \\r\\n\"}]', '[]');

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
(3, 15, 4, 'Friday', '2024-12-27', NULL, '2024-12-27 10:20:31', '2024-12-27 10:20:31');

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
`id` int(11)
,`user_id` int(11)
,`username` varchar(255)
,`employee_id` varchar(50)
,`leave_type` varchar(50)
,`start_date` date
,`end_date` date
,`reason` text
,`status` enum('pending','approved','rejected')
,`manager_status` enum('pending','approved','rejected')
,`hr_status` enum('pending','approved','rejected')
,`created_at` timestamp
,`modified_at` timestamp
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
  ADD UNIQUE KEY `unique_attendance` (`user_id`,`date`),
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
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `leave_type_id` (`leave_type_id`),
  ADD KEY `modified_by` (`modified_by`),
  ADD KEY `idx_status_combined` (`status`,`manager_status`,`hr_status`),
  ADD KEY `idx_dates` (`start_date`,`end_date`),
  ADD KEY `idx_leaves_dates` (`start_date`,`end_date`),
  ADD KEY `manager_id` (`manager_id`),
  ADD KEY `reporting_manager` (`reporting_manager`);

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
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `action_by` (`action_by`),
  ADD KEY `updated_by` (`updated_by`);

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
  ADD UNIQUE KEY `contract_number` (`contract_number`),
  ADD KEY `got_project_from` (`got_project_from`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_project_contract` (`contract_number`),
  ADD KEY `idx_project_client` (`client_name`),
  ADD KEY `idx_project_type` (`project_type`);

--
-- Indexes for table `project_stages`
--
ALTER TABLE `project_stages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `idx_stage_status` (`status`);

--
-- Indexes for table `project_sub_stages`
--
ALTER TABLE `project_sub_stages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stage_id` (`stage_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `idx_substage_status` (`status`);

--
-- Indexes for table `project_team_members`
--
ALTER TABLE `project_team_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_team_member` (`project_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_team_member_role` (`role`);

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
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `substage_files`
--
ALTER TABLE `substage_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `substage_id` (`substage_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

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
-- Indexes for table `task_history`
--
ALTER TABLE `task_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `user_id` (`user_id`);

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
-- Indexes for table `task_stage_history`
--
ALTER TABLE `task_stage_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stage_id` (`stage_id`),
  ADD KEY `idx_changed_by` (`changed_by`);

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
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `task_substages`
--
ALTER TABLE `task_substages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stage_id` (`stage_id`);

--
-- Indexes for table `task_substage_history`
--
ALTER TABLE `task_substage_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_substage_id` (`substage_id`),
  ADD KEY `idx_changed_by` (`changed_by`);

--
-- Indexes for table `task_timeline`
--
ALTER TABLE `task_timeline`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `stage_id` (`stage_id`),
  ADD KEY `substage_id` (`substage_id`),
  ADD KEY `created_by` (`created_by`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `project_stages`
--
ALTER TABLE `project_stages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `project_sub_stages`
--
ALTER TABLE `project_sub_stages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `project_team_members`
--
ALTER TABLE `project_team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
-- AUTO_INCREMENT for table `salary_records`
--
ALTER TABLE `salary_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `salary_structures`
--
ALTER TABLE `salary_structures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `substage_files`
--
ALTER TABLE `substage_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

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
-- AUTO_INCREMENT for table `task_history`
--
ALTER TABLE `task_history`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `task_stage_history`
--
ALTER TABLE `task_stage_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_status`
--
ALTER TABLE `task_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `task_status_history`
--
ALTER TABLE `task_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `task_substages`
--
ALTER TABLE `task_substages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `task_substage_history`
--
ALTER TABLE `task_substage_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_timeline`
--
ALTER TABLE `task_timeline`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `travel_expenses`
--
ALTER TABLE `travel_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_shifts`
--
ALTER TABLE `user_shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- Constraints for table `file_attachments`
--
ALTER TABLE `file_attachments`
  ADD CONSTRAINT `file_attachments_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`);

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
-- Constraints for table `leaves`
--
ALTER TABLE `leaves`
  ADD CONSTRAINT `leaves_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `leaves_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`),
  ADD CONSTRAINT `leaves_ibfk_3` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `leaves_ibfk_4` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `leaves_ibfk_5` FOREIGN KEY (`reporting_manager`) REFERENCES `users` (`id`);

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
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `leave_requests_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

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
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`got_project_from`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `project_stages`
--
ALTER TABLE `project_stages`
  ADD CONSTRAINT `project_stages_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_stages_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`);

--
-- Constraints for table `project_sub_stages`
--
ALTER TABLE `project_sub_stages`
  ADD CONSTRAINT `project_sub_stages_ibfk_1` FOREIGN KEY (`stage_id`) REFERENCES `project_stages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_sub_stages_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`);

--
-- Constraints for table `project_team_members`
--
ALTER TABLE `project_team_members`
  ADD CONSTRAINT `project_team_members_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_team_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `stage_files_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `substage_files`
--
ALTER TABLE `substage_files`
  ADD CONSTRAINT `substage_files_ibfk_1` FOREIGN KEY (`substage_id`) REFERENCES `task_substages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `substage_files_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

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
-- Constraints for table `task_history`
--
ALTER TABLE `task_history`
  ADD CONSTRAINT `task_history_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`),
  ADD CONSTRAINT `task_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_stages`
--
ALTER TABLE `task_stages`
  ADD CONSTRAINT `task_stages_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_stages_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_stage_history`
--
ALTER TABLE `task_stage_history`
  ADD CONSTRAINT `task_stage_history_ibfk_1` FOREIGN KEY (`stage_id`) REFERENCES `task_stages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_stage_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_status_history`
--
ALTER TABLE `task_status_history`
  ADD CONSTRAINT `task_status_history_ibfk_1` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_substages`
--
ALTER TABLE `task_substages`
  ADD CONSTRAINT `task_substages_ibfk_1` FOREIGN KEY (`stage_id`) REFERENCES `task_stages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_substage_history`
--
ALTER TABLE `task_substage_history`
  ADD CONSTRAINT `task_substage_history_ibfk_1` FOREIGN KEY (`substage_id`) REFERENCES `task_substages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_substage_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `task_timeline`
--
ALTER TABLE `task_timeline`
  ADD CONSTRAINT `task_timeline_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_timeline_ibfk_2` FOREIGN KEY (`stage_id`) REFERENCES `task_stages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `task_timeline_ibfk_3` FOREIGN KEY (`substage_id`) REFERENCES `task_substages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `task_timeline_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
