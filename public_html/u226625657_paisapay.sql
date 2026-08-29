-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 28, 2026 at 05:46 AM
-- Server version: 11.8.8-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u226625657_paisapay`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`u226625657_paisapay`@`127.0.0.1` PROCEDURE `sp_update_leaderboard` ()   BEGIN
    -- Update all_time
    INSERT INTO leaderboard (user_id, period, total_earnings, updated_at)
    SELECT 
        id, 
        'all_time', 
        total_earnings, 
        NOW()
    FROM users
    WHERE is_active = 1 AND is_blocked = 0
    ON DUPLICATE KEY UPDATE 
        total_earnings = VALUES(total_earnings),
        updated_at = VALUES(updated_at);
    
    -- Update weekly
    INSERT INTO leaderboard (user_id, period, week_start, total_earnings, updated_at)
    SELECT 
        u.id, 
        'weekly', 
        DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY),
        COALESCE(SUM(wt.amount), 0),
        NOW()
    FROM users u
    LEFT JOIN wallet_transactions wt ON u.id = wt.user_id 
        AND wt.transaction_type IN ('referral', 'task', 'bonus')
        AND wt.status = 'completed'
        AND DATE(wt.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    WHERE u.is_active = 1 AND u.is_blocked = 0
    GROUP BY u.id
    ON DUPLICATE KEY UPDATE 
        total_earnings = VALUES(total_earnings),
        updated_at = VALUES(updated_at);
    
    -- Update monthly
    INSERT INTO leaderboard (user_id, period, month_start, total_earnings, updated_at)
    SELECT 
        u.id, 
        'monthly', 
        DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE())-1 DAY),
        COALESCE(SUM(wt.amount), 0),
        NOW()
    FROM users u
    LEFT JOIN wallet_transactions wt ON u.id = wt.user_id 
        AND wt.transaction_type IN ('referral', 'task', 'bonus')
        AND wt.status = 'completed'
        AND DATE(wt.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    WHERE u.is_active = 1 AND u.is_blocked = 0
    GROUP BY u.id
    ON DUPLICATE KEY UPDATE 
        total_earnings = VALUES(total_earnings),
        updated_at = VALUES(updated_at);
    
    -- Update ranks
    SET @rank = 0;
    UPDATE leaderboard 
    SET rank_position = (@rank := @rank + 1)
    WHERE period = 'all_time'
    ORDER BY total_earnings DESC;
    
    SET @rank = 0;
    UPDATE leaderboard 
    SET rank_position = (@rank := @rank + 1)
    WHERE period = 'weekly'
    ORDER BY total_earnings DESC;
    
    SET @rank = 0;
    UPDATE leaderboard 
    SET rank_position = (@rank := @rank + 1)
    WHERE period = 'monthly'
    ORDER BY total_earnings DESC;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 2, 'admin_login', NULL, '150.242.252.98', NULL, '2026-08-26 06:00:45'),
(2, NULL, 2, 'admin_login', NULL, '150.242.252.98', NULL, '2026-08-26 06:19:15'),
(3, 1, 2, 'withdrawal_rejected', '{\"withdrawal_id\":2,\"amount\":\"2500.00\",\"old_status\":\"pending\",\"new_status\":\"rejected\",\"admin_notes\":\"Fake\"}', NULL, NULL, '2026-08-26 07:11:32'),
(4, 1, 2, 'withdrawal_approved', '{\"withdrawal_id\":3,\"amount\":\"1500.00\",\"old_status\":\"under_review\",\"new_status\":\"approved\",\"admin_notes\":\"Approved.\"}', NULL, NULL, '2026-08-26 07:19:32'),
(5, NULL, 2, 'admin_login', NULL, '2405:201:550d:10af:144c:d5cd:f04:8b82', NULL, '2026-08-28 05:38:59');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('super_admin','admin','moderator') DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(2, 'lonerashed9', 'lonerashed9@gmail.com', '$2y$10$ag9frr4EDjiXxKKRlJEfxuokbSbmCqrCuyIkpfCSOh4wJ7TN5qv0y', 'Super Administrator', 'super_admin', 1, '2026-08-25 22:36:15', '2026-08-23 13:54:36', '2026-08-25 22:36:15');

-- --------------------------------------------------------

--
-- Table structure for table `admin_settings`
--

CREATE TABLE `admin_settings` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_config`
--

CREATE TABLE `app_config` (
  `id` int(11) NOT NULL,
  `config_key` varchar(50) NOT NULL,
  `config_value` text NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `banners`
--

INSERT INTO `banners` (`id`, `title`, `description`, `image_url`, `link_url`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'Welcome to PaisaPay!', 'Earn money by inviting friends and completing tasks', NULL, '/invite', 1, 1, '2026-08-23 13:31:12', '2026-08-23 13:31:12'),
(2, '🎉 New Tasks Available!', 'Complete tasks and earn rewards instantly', NULL, '/earn', 1, 2, '2026-08-23 13:31:12', '2026-08-23 13:31:12');

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `template_key` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`id`, `template_key`, `subject`, `body`, `created_at`, `updated_at`) VALUES
(1, 'welcome', 'Welcome to PaisaPay!', 'Hello {name}, Welcome to PaisaPay! Your referral code is {referral_code}. Start earning now!', '2026-08-23 13:31:12', '2026-08-23 13:31:12'),
(2, 'referral_reward', 'You Earned a Referral Reward!', 'Hello {name}, You earned ₹{amount} for referring {referred_name}!', '2026-08-23 13:31:12', '2026-08-23 13:31:12'),
(3, 'withdrawal_request', 'Withdrawal Request Received', 'Hello {name}, Your withdrawal request of ₹{amount} has been received. We will process it soon.', '2026-08-23 13:31:12', '2026-08-23 13:31:12'),
(4, 'withdrawal_completed', 'Withdrawal Completed', 'Hello {name}, Your withdrawal of ₹{amount} has been completed. Check your account.', '2026-08-23 13:31:12', '2026-08-23 13:31:12'),
(5, 'withdrawal_rejected', 'Withdrawal Rejected', 'Hello {name}, Your withdrawal request of ₹{amount} has been rejected. Reason: {reason}', '2026-08-23 13:31:12', '2026-08-23 13:31:12'),
(6, 'task_completed', 'Task Completed Successfully', 'Hello {name}, You completed task \"{task_name}\" and earned ₹{amount}!', '2026-08-23 13:31:12', '2026-08-23 13:31:12');

-- --------------------------------------------------------

--
-- Table structure for table `fraud_reports`
--

CREATE TABLE `fraud_reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reported_by` varchar(50) DEFAULT NULL,
  `fraud_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `evidence` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence`)),
  `status` enum('pending','investigating','confirmed','dismissed') DEFAULT 'pending',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leaderboard`
--

CREATE TABLE `leaderboard` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `period` enum('weekly','monthly','all_time') NOT NULL,
  `total_earnings` decimal(12,2) DEFAULT 0.00,
  `withdrawal_count` int(11) DEFAULT 0,
  `rank_position` int(11) DEFAULT 0,
  `week_start` date DEFAULT NULL,
  `month_start` date DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('push','in_app','banner','popup') DEFAULT 'in_app',
  `is_read` tinyint(1) DEFAULT 0,
  `is_sent` tinyint(1) DEFAULT 0,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_logs`
--

CREATE TABLE `otp_logs` (
  `id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `country_code` varchar(5) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `verification_id` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `attempts` int(11) DEFAULT 0,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `method_name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `icon` varchar(500) DEFAULT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `min_amount` decimal(10,2) DEFAULT 0.00,
  `max_amount` decimal(10,2) DEFAULT 999999.99,
  `account_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`account_details`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `method_name`, `display_name`, `icon`, `is_enabled`, `min_amount`, `max_amount`, `account_details`, `created_at`, `updated_at`) VALUES
(1, 'upi', 'UPI', 'https://img.icons8.com/?size=100&id=112309&format=png&color=000000', 1, 0.00, 999999.99, NULL, '2026-08-23 13:31:12', '2026-08-25 17:11:57'),
(2, 'phonepe', 'PhonePe', 'https://img.icons8.com/?size=100&id=OYtBxIlJwMGA&format=png&color=000000', 1, 0.00, 999999.99, NULL, '2026-08-23 13:31:12', '2026-08-25 12:59:34'),
(3, 'paytm', 'Paytm', 'https://img.icons8.com/?size=100&id=Aub11Fs5DJVg&format=png&color=000000', 1, 0.00, 999999.99, NULL, '2026-08-23 13:31:12', '2026-08-25 13:03:38'),
(4, 'googlepay', 'Google Pay', 'https://img.icons8.com/?size=100&id=am4ltuIYDpQ5&format=png&color=000000', 1, 0.00, 999999.99, NULL, '2026-08-23 13:31:12', '2026-08-25 13:02:12'),
(5, 'bank_transfer', 'Bank Transfer', 'https://img.icons8.com/?size=100&id=ikR3ficCVx19&format=png&color=000000', 1, 0.00, 999999.99, NULL, '2026-08-23 13:31:12', '2026-08-25 13:05:19'),
(6, 'usdt', 'USDT (TRC20)', 'https://img.icons8.com/?size=100&id=U8V97McJaXmr&format=png&color=000000', 1, 0.00, 999999.99, NULL, '2026-08-23 13:31:12', '2026-08-25 13:04:37');

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` int(11) NOT NULL,
  `referrer_id` int(11) NOT NULL,
  `referred_user_id` int(11) NOT NULL,
  `referral_code` varchar(20) NOT NULL,
  `reward_amount` decimal(10,2) DEFAULT 0.00,
  `is_rewarded` tinyint(1) DEFAULT 0,
  `reward_date` timestamp NULL DEFAULT NULL,
  `is_valid` tinyint(1) DEFAULT 1,
  `validation_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `referred_user_verified` tinyint(1) DEFAULT 0,
  `referred_user_active` tinyint(1) DEFAULT 0,
  `referred_user_tasks_completed` int(11) DEFAULT 0,
  `is_genuine` tinyint(1) DEFAULT 1,
  `validation_status` enum('pending','verified','flagged','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `referrals`
--

INSERT INTO `referrals` (`id`, `referrer_id`, `referred_user_id`, `referral_code`, `reward_amount`, `is_rewarded`, `reward_date`, `is_valid`, `validation_notes`, `created_at`, `updated_at`, `referred_user_verified`, `referred_user_active`, `referred_user_tasks_completed`, `is_genuine`, `validation_status`) VALUES
(1, 1, 2, 'SYJ657', 25.00, 1, NULL, 1, NULL, '2026-03-18 13:21:59', '2026-08-26 05:35:16', 1, 1, 19, 1, 'verified'),
(2, 1, 3, 'SYJ657', 25.00, 1, NULL, 1, NULL, '2026-03-23 13:21:59', '2026-08-26 07:16:19', 1, 0, 0, 0, 'pending'),
(3, 1, 4, 'SYJ657', 25.00, 1, NULL, 1, NULL, '2026-03-28 13:21:59', '2026-08-26 05:34:20', 1, 1, 14, 1, 'verified'),
(4, 1, 5, 'SYJ657', 25.00, 1, NULL, 1, NULL, '2026-04-02 13:21:59', '2026-08-26 05:34:20', 1, 1, 13, 1, 'verified'),
(5, 1, 6, 'SYJ657', 25.00, 1, NULL, 1, NULL, '2026-04-07 13:21:59', '2026-08-26 05:34:20', 1, 1, 5, 1, 'verified'),
(6, 1, 7, 'SYJ657', 25.00, 1, NULL, 1, NULL, '2026-04-12 13:21:59', '2026-08-26 05:34:20', 1, 1, 7, 1, 'verified'),
(7, 1, 8, 'SYJ657', 25.00, 1, NULL, 1, NULL, '2026-04-17 13:21:59', '2026-08-26 05:34:20', 1, 1, 10, 1, 'verified'),
(8, 1, 9, 'SYJ657', 25.00, 1, NULL, 1, NULL, '2026-04-22 13:21:59', '2026-08-26 06:20:42', 1, 1, 11, 1, 'verified'),
(9, 1, 10, 'SYJ657', 25.00, 1, NULL, 1, NULL, '2026-04-27 13:21:59', '2026-08-26 05:34:20', 1, 1, 10, 1, 'verified'),
(10, 1, 11, 'SYJ657', 25.00, 1, NULL, 1, NULL, '2026-05-02 13:21:59', '2026-08-26 05:34:20', 1, 1, 12, 1, 'verified'),
(11, 1, 12, 'SYJ657', 25.00, 1, NULL, 1, NULL, '2026-05-07 13:21:59', '2026-08-26 05:34:20', 1, 1, 12, 1, 'verified'),
(12, 1, 13, 'SYJ657', 25.00, 1, NULL, 1, NULL, '2026-05-12 13:21:59', '2026-08-26 05:34:20', 1, 1, 13, 1, 'verified'),
(13, 2, 14, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-03-28 13:21:59', '2026-08-26 05:34:20', 1, 1, 10, 1, 'verified'),
(14, 2, 15, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-04-02 13:21:59', '2026-08-26 05:34:20', 1, 1, 10, 1, 'verified'),
(15, 2, 16, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-04-07 13:21:59', '2026-08-26 05:34:20', 1, 1, 8, 1, 'verified'),
(16, 2, 17, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-04-12 13:21:59', '2026-08-26 05:34:20', 1, 1, 8, 1, 'verified'),
(17, 2, 18, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-04-17 13:21:59', '2026-08-26 05:34:20', 1, 1, 6, 1, 'verified'),
(18, 2, 19, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-04-22 13:21:59', '2026-08-26 05:34:20', 1, 1, 6, 1, 'verified'),
(19, 2, 20, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-04-27 13:21:59', '2026-08-26 05:34:20', 1, 1, 6, 1, 'verified'),
(20, 2, 1, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-05-02 13:21:59', '2026-08-26 07:16:19', 1, 1, 49, 1, 'verified'),
(21, 2, 3, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-05-07 13:21:59', '2026-08-26 07:16:19', 1, 0, 0, 0, 'pending'),
(22, 2, 4, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-05-12 13:21:59', '2026-08-26 05:34:20', 1, 1, 14, 1, 'verified'),
(23, 3, 5, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-04-07 13:21:59', '2026-08-26 05:34:20', 1, 1, 13, 1, 'verified'),
(24, 3, 6, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-04-12 13:21:59', '2026-08-26 05:34:20', 1, 1, 5, 1, 'verified'),
(25, 3, 7, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-04-17 13:21:59', '2026-08-26 05:34:20', 1, 1, 7, 1, 'verified'),
(26, 3, 8, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-04-22 13:21:59', '2026-08-26 05:34:20', 1, 1, 10, 1, 'verified'),
(27, 3, 9, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-04-27 13:21:59', '2026-08-26 06:20:42', 1, 1, 11, 1, 'verified'),
(28, 3, 10, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-05-02 13:21:59', '2026-08-26 05:34:20', 1, 1, 10, 1, 'verified'),
(29, 3, 11, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-05-07 13:21:59', '2026-08-26 05:34:20', 1, 1, 12, 1, 'verified'),
(30, 3, 12, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-05-12 13:21:59', '2026-08-26 05:34:20', 1, 1, 12, 1, 'verified'),
(31, 3, 13, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-05-17 13:21:59', '2026-08-26 05:34:20', 1, 1, 13, 1, 'verified'),
(32, 4, 14, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-04-17 13:21:59', '2026-08-26 05:34:20', 1, 1, 10, 1, 'verified'),
(33, 4, 15, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-04-22 13:21:59', '2026-08-26 05:34:20', 1, 1, 10, 1, 'verified'),
(34, 4, 16, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-04-27 13:21:59', '2026-08-26 05:34:20', 1, 1, 8, 1, 'verified'),
(35, 4, 17, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-05-02 13:21:59', '2026-08-26 05:34:20', 1, 1, 8, 1, 'verified'),
(36, 4, 18, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-05-07 13:21:59', '2026-08-26 05:34:20', 1, 1, 6, 1, 'verified'),
(37, 4, 19, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-05-12 13:21:59', '2026-08-26 05:34:20', 1, 1, 6, 1, 'verified'),
(38, 4, 20, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-05-17 13:21:59', '2026-08-26 05:34:20', 1, 1, 6, 1, 'verified'),
(39, 4, 1, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-05-22 13:21:59', '2026-08-26 07:16:19', 1, 1, 49, 1, 'verified'),
(40, 5, 2, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-04-27 13:21:59', '2026-08-26 05:35:16', 1, 1, 19, 1, 'verified'),
(41, 5, 3, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-05-02 13:21:59', '2026-08-26 07:16:19', 1, 0, 0, 0, 'pending'),
(42, 5, 4, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-05-07 13:21:59', '2026-08-26 05:34:20', 1, 1, 14, 1, 'verified'),
(43, 5, 6, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-05-12 13:21:59', '2026-08-26 05:34:20', 1, 1, 5, 1, 'verified'),
(44, 5, 7, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-05-17 13:21:59', '2026-08-26 05:34:20', 1, 1, 7, 1, 'verified'),
(45, 5, 8, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-05-22 13:21:59', '2026-08-26 05:34:20', 1, 1, 10, 1, 'verified'),
(46, 5, 9, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-05-27 13:21:59', '2026-08-26 06:20:42', 1, 1, 11, 1, 'verified'),
(47, 5, 10, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-06-01 13:21:59', '2026-08-26 05:34:20', 1, 1, 10, 1, 'verified'),
(48, 6, 11, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-05-07 13:21:59', '2026-08-26 05:34:20', 1, 1, 12, 1, 'verified'),
(49, 6, 12, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-05-12 13:21:59', '2026-08-26 05:34:20', 1, 1, 12, 1, 'verified'),
(50, 6, 13, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-05-17 13:21:59', '2026-08-26 05:34:20', 1, 1, 13, 1, 'verified'),
(51, 6, 14, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-05-22 13:21:59', '2026-08-26 05:34:20', 1, 1, 10, 1, 'verified'),
(52, 6, 15, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-05-27 13:21:59', '2026-08-26 05:34:20', 1, 1, 10, 1, 'verified'),
(53, 6, 16, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-06-01 13:21:59', '2026-08-26 05:34:20', 1, 1, 8, 1, 'verified'),
(54, 6, 17, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-06-06 13:21:59', '2026-08-26 05:34:20', 1, 1, 8, 1, 'verified'),
(55, 7, 18, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-05-17 13:21:59', '2026-08-26 05:34:20', 1, 1, 6, 1, 'verified'),
(56, 7, 19, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-05-22 13:21:59', '2026-08-26 05:34:20', 1, 1, 6, 1, 'verified'),
(57, 7, 20, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-05-27 13:21:59', '2026-08-26 05:34:20', 1, 1, 6, 1, 'verified'),
(58, 7, 1, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-06-01 13:21:59', '2026-08-26 07:16:19', 1, 1, 49, 1, 'verified'),
(59, 7, 2, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-06-06 13:21:59', '2026-08-26 05:35:16', 1, 1, 19, 1, 'verified'),
(60, 7, 3, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-06-11 13:21:59', '2026-08-26 07:16:19', 1, 0, 0, 0, 'pending'),
(61, 7, 4, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-06-16 13:21:59', '2026-08-26 05:34:20', 1, 1, 14, 1, 'verified'),
(179, 2, 24, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-05-17 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(180, 2, 25, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-05-22 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(181, 2, 26, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-05-27 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(182, 2, 27, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-06-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(183, 2, 28, 'PRIYA456', 25.00, 1, NULL, 1, NULL, '2026-06-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(184, 3, 29, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-05-22 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(185, 3, 30, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-05-27 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(186, 3, 31, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-06-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(187, 3, 32, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-06-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(188, 3, 33, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-06-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(189, 3, 34, 'RAHUL789', 25.00, 1, NULL, 1, NULL, '2026-06-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(190, 4, 35, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-05-27 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(191, 4, 36, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-06-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(192, 4, 37, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-06-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(193, 4, 38, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-06-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(194, 4, 39, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-06-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(195, 4, 40, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-06-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(196, 4, 41, 'SNEHA012', 25.00, 1, NULL, 1, NULL, '2026-06-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(197, 5, 42, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-06-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(198, 5, 43, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-06-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(199, 5, 44, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-06-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(200, 5, 45, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-06-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(201, 5, 46, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-06-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(202, 5, 47, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-07-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(203, 5, 48, 'VIKRAM34', 25.00, 1, NULL, 1, NULL, '2026-07-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(204, 6, 49, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-06-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(205, 6, 50, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-06-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(206, 6, 51, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-06-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(207, 6, 52, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-06-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(208, 6, 53, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-07-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(209, 6, 54, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-07-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(210, 6, 55, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-07-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(211, 6, 56, 'ANANYA56', 25.00, 1, NULL, 1, NULL, '2026-07-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(212, 7, 57, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-06-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(213, 7, 58, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-06-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(214, 7, 59, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-07-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(215, 7, 60, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-07-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(216, 7, 61, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-07-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(217, 7, 62, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-07-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(218, 7, 63, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-07-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(219, 7, 64, 'KARAN78', 25.00, 1, NULL, 1, NULL, '2026-07-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(220, 8, 65, 'MEERA90', 25.00, 1, NULL, 1, NULL, '2026-05-17 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(221, 8, 66, 'MEERA90', 25.00, 1, NULL, 1, NULL, '2026-05-22 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(222, 8, 67, 'MEERA90', 25.00, 1, NULL, 1, NULL, '2026-05-27 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(223, 8, 68, 'MEERA90', 25.00, 1, NULL, 1, NULL, '2026-06-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(224, 8, 69, 'MEERA90', 25.00, 1, NULL, 1, NULL, '2026-06-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(225, 8, 70, 'MEERA90', 25.00, 1, NULL, 1, NULL, '2026-06-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(226, 8, 71, 'MEERA90', 25.00, 1, NULL, 1, NULL, '2026-06-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(227, 8, 72, 'MEERA90', 25.00, 1, NULL, 1, NULL, '2026-06-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(228, 8, 73, 'MEERA90', 25.00, 1, NULL, 1, NULL, '2026-06-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(229, 8, 74, 'MEERA90', 25.00, 1, NULL, 1, NULL, '2026-07-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(230, 9, 75, 'ARJUN11', 25.00, 1, NULL, 1, NULL, '2026-05-27 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(231, 9, 76, 'ARJUN11', 25.00, 1, NULL, 1, NULL, '2026-06-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(232, 9, 77, 'ARJUN11', 25.00, 1, NULL, 1, NULL, '2026-06-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(233, 9, 78, 'ARJUN11', 25.00, 1, NULL, 1, NULL, '2026-06-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(234, 9, 79, 'ARJUN11', 25.00, 1, NULL, 1, NULL, '2026-06-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(235, 9, 80, 'ARJUN11', 25.00, 1, NULL, 1, NULL, '2026-06-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(236, 9, 81, 'ARJUN11', 25.00, 1, NULL, 1, NULL, '2026-06-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(237, 9, 82, 'ARJUN11', 25.00, 1, NULL, 1, NULL, '2026-07-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(238, 9, 83, 'ARJUN11', 25.00, 1, NULL, 1, NULL, '2026-07-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(239, 10, 84, 'KAVYA22', 25.00, 1, NULL, 1, NULL, '2026-06-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(240, 10, 85, 'KAVYA22', 25.00, 1, NULL, 1, NULL, '2026-06-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(241, 10, 86, 'KAVYA22', 25.00, 1, NULL, 1, NULL, '2026-06-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(242, 10, 87, 'KAVYA22', 25.00, 1, NULL, 1, NULL, '2026-06-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(243, 10, 88, 'KAVYA22', 25.00, 1, NULL, 1, NULL, '2026-06-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(244, 10, 89, 'KAVYA22', 25.00, 1, NULL, 1, NULL, '2026-07-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(245, 10, 90, 'KAVYA22', 25.00, 1, NULL, 1, NULL, '2026-07-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(246, 10, 91, 'KAVYA22', 25.00, 1, NULL, 1, NULL, '2026-07-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(247, 11, 92, 'ROHAN33', 25.00, 1, NULL, 1, NULL, '2026-06-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(248, 11, 93, 'ROHAN33', 25.00, 1, NULL, 1, NULL, '2026-06-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(249, 11, 94, 'ROHAN33', 25.00, 1, NULL, 1, NULL, '2026-06-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(250, 11, 95, 'ROHAN33', 25.00, 1, NULL, 1, NULL, '2026-06-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(251, 11, 96, 'ROHAN33', 25.00, 1, NULL, 1, NULL, '2026-07-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(252, 11, 97, 'ROHAN33', 25.00, 1, NULL, 1, NULL, '2026-07-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(253, 11, 98, 'ROHAN33', 25.00, 1, NULL, 1, NULL, '2026-07-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(254, 12, 99, 'DIVYA44', 25.00, 1, NULL, 1, NULL, '2026-06-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(255, 12, 100, 'DIVYA44', 25.00, 1, NULL, 1, NULL, '2026-06-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(256, 12, 101, 'DIVYA44', 25.00, 1, NULL, 1, NULL, '2026-06-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(257, 12, 102, 'DIVYA44', 25.00, 1, NULL, 1, NULL, '2026-07-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(258, 12, 103, 'DIVYA44', 25.00, 1, NULL, 1, NULL, '2026-07-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(259, 12, 104, 'DIVYA44', 25.00, 1, NULL, 1, NULL, '2026-07-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(260, 13, 105, 'SURESH55', 25.00, 1, NULL, 1, NULL, '2026-06-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(261, 13, 106, 'SURESH55', 25.00, 1, NULL, 1, NULL, '2026-06-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(262, 13, 107, 'SURESH55', 25.00, 1, NULL, 1, NULL, '2026-07-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(263, 13, 108, 'SURESH55', 25.00, 1, NULL, 1, NULL, '2026-07-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(264, 13, 109, 'SURESH55', 25.00, 1, NULL, 1, NULL, '2026-07-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(265, 13, 110, 'SURESH55', 25.00, 1, NULL, 1, NULL, '2026-07-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(266, 14, 111, 'LAKSH66', 25.00, 1, NULL, 1, NULL, '2026-06-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(267, 14, 112, 'LAKSH66', 25.00, 1, NULL, 1, NULL, '2026-07-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(268, 14, 113, 'LAKSH66', 25.00, 1, NULL, 1, NULL, '2026-07-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(269, 14, 114, 'LAKSH66', 25.00, 1, NULL, 1, NULL, '2026-07-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(270, 14, 115, 'LAKSH66', 25.00, 1, NULL, 1, NULL, '2026-07-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(271, 15, 116, 'GANES77', 25.00, 1, NULL, 1, NULL, '2026-07-01 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(272, 15, 117, 'GANES77', 25.00, 1, NULL, 1, NULL, '2026-07-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(273, 15, 118, 'GANES77', 25.00, 1, NULL, 1, NULL, '2026-07-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(274, 15, 119, 'GANES77', 25.00, 1, NULL, 1, NULL, '2026-07-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(275, 15, 120, 'GANES77', 25.00, 1, NULL, 1, NULL, '2026-07-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(276, 16, 121, 'SARITA88', 25.00, 1, NULL, 1, NULL, '2026-07-06 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(277, 16, 122, 'SARITA88', 25.00, 1, NULL, 1, NULL, '2026-07-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(278, 16, 123, 'SARITA88', 25.00, 1, NULL, 1, NULL, '2026-07-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(279, 16, 124, 'SARITA88', 25.00, 1, NULL, 1, NULL, '2026-07-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(280, 17, 125, 'MOHAN99', 25.00, 1, NULL, 1, NULL, '2026-07-11 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(281, 17, 126, 'MOHAN99', 25.00, 1, NULL, 1, NULL, '2026-07-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(282, 17, 127, 'MOHAN99', 25.00, 1, NULL, 1, NULL, '2026-07-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(283, 17, 128, 'MOHAN99', 25.00, 1, NULL, 1, NULL, '2026-07-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(284, 18, 129, 'NANDI00', 25.00, 1, NULL, 1, NULL, '2026-07-16 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(285, 18, 130, 'NANDI00', 25.00, 1, NULL, 1, NULL, '2026-07-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(286, 18, 131, 'NANDI00', 25.00, 1, NULL, 1, NULL, '2026-07-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(287, 19, 132, 'PRAKA11', 25.00, 1, NULL, 1, NULL, '2026-07-21 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(288, 19, 133, 'PRAKA11', 25.00, 1, NULL, 1, NULL, '2026-07-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(289, 19, 134, 'PRAKA11', 25.00, 1, NULL, 1, NULL, '2026-07-31 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(290, 20, 135, 'DEEPA22', 25.00, 1, NULL, 1, NULL, '2026-07-26 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(291, 20, 136, 'DEEPA22', 25.00, 1, NULL, 1, NULL, '2026-07-31 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending'),
(292, 20, 137, 'DEEPA22', 25.00, 1, NULL, 1, NULL, '2026-08-05 13:31:16', '2026-08-26 05:34:20', NULL, 0, 0, 0, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` enum('string','integer','decimal','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
(1, 'signup_bonus', '10.00', 'decimal', 'Bonus amount given on successful signup', '2026-08-23 13:31:12'),
(2, 'referral_bonus', '25.00', 'decimal', 'Bonus amount given per successful referral', '2026-08-23 13:31:12'),
(3, 'invite_reward', '5.00', 'decimal', 'Reward for inviting a new user', '2026-08-23 13:31:12'),
(4, 'task_reward', '10.00', 'decimal', 'Default reward amount per task', '2026-08-23 13:31:12'),
(5, 'min_withdrawal', '1500.00', 'decimal', 'Minimum withdrawal amount', '2026-08-26 07:06:29'),
(6, 'max_withdrawal', '50000.00', 'decimal', 'Maximum withdrawal amount', '2026-08-23 13:31:12'),
(7, 'required_referrals', '10', 'integer', 'Number of referrals required for withdrawal', '2026-08-23 13:31:12'),
(8, 'required_tasks', '20', 'integer', 'Number of tasks required for withdrawal', '2026-08-26 07:26:07'),
(9, 'daily_task_limit', '10', 'integer', 'Maximum tasks per user per day', '2026-08-23 13:31:12'),
(10, 'daily_referral_limit', '10', 'integer', 'Maximum referrals per user per day', '2026-08-25 22:39:23'),
(11, 'daily_withdrawal_limit', '3', 'integer', 'Maximum withdrawals per user per day', '2026-08-23 13:31:12'),
(12, 'maintenance_mode', '0', 'boolean', 'Enable maintenance mode', '2026-08-26 07:06:29'),
(13, 'currency_symbol', '₹', 'string', 'Currency symbol', '2026-08-23 13:31:12'),
(14, 'app_version', '3.7.6', 'string', 'Current app version', '2026-08-25 22:39:23'),
(15, 'max_devices_per_user', '3', 'integer', 'Maximum devices allowed per user', '2026-08-23 13:31:12'),
(16, 'referral_expiry_days', '30', 'integer', 'Days until referral code expires', '2026-08-23 13:31:12'),
(17, 'email_notifications', '0', 'boolean', 'Enable email notifications', '2026-08-26 07:06:29'),
(18, 'push_notifications', '0', 'boolean', 'Enable push notifications', '2026-08-26 07:06:29');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fa-link',
  `url` varchar(500) NOT NULL,
  `reward_amount` decimal(10,2) NOT NULL,
  `timer_seconds` int(11) DEFAULT 30,
  `daily_limit` int(11) DEFAULT 5,
  `is_one_time` tinyint(1) DEFAULT 0,
  `is_repeatable` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `task_type` enum('website','whatsapp','telegram','facebook','instagram','youtube','custom') DEFAULT 'website',
  `schedule_time` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `title`, `description`, `icon`, `url`, `reward_amount`, `timer_seconds`, `daily_limit`, `is_one_time`, `is_repeatable`, `is_active`, `task_type`, `schedule_time`, `created_at`, `updated_at`) VALUES
(1, 'Visit Our Website', 'Visit our official website and explore', 'fa-globe', 'https://example.com', 5.00, 30, 3, 0, 1, 1, 'website', NULL, '2026-08-23 13:31:12', '2026-08-23 13:31:12'),
(2, 'Join WhatsApp Channel', 'Join our WhatsApp channel for updates', 'fa-whatsapp', 'https://wa.me/1234567890', 10.00, 20, 2, 1, 0, 1, 'whatsapp', NULL, '2026-08-23 13:31:12', '2026-08-23 13:31:12'),
(3, 'Follow on Instagram', 'Follow our Instagram page', 'fa-instagram', 'https://instagram.com/example', 8.00, 15, 2, 1, 0, 1, 'instagram', NULL, '2026-08-23 13:31:12', '2026-08-23 13:31:12'),
(4, 'Subscribe on YouTube', 'Subscribe to our YouTube channel', 'fa-youtube', 'https://youtube.com/example', 15.00, 30, 1, 1, 0, 1, 'youtube', NULL, '2026-08-23 13:31:12', '2026-08-23 13:31:12'),
(5, 'Like Facebook Page', 'Like and follow our Facebook page', 'fa-facebook', 'https://facebook.com/example', 5.00, 10, 2, 1, 0, 1, 'facebook', NULL, '2026-08-23 13:31:12', '2026-08-23 13:31:12'),
(6, 'Join Telegram Group', 'Join our Telegram group for news', 'fa-telegram', 'https://t.me/example', 10.00, 20, 2, 1, 0, 1, 'telegram', NULL, '2026-08-23 13:31:12', '2026-08-23 13:31:12'),
(7, 'Follow On Instagram', '', 'fa-link', 'https://instagram.com', 5.00, 30, 5, 0, 0, 1, 'website', NULL, '2026-08-23 15:16:40', '2026-08-23 15:16:40'),
(8, 'test', '', 'fa-link', 'https://google.com', 2.00, 30, 5, 0, 0, 1, 'website', NULL, '2026-08-24 16:13:09', '2026-08-24 16:13:09'),
(9, 'Do Flip', 'Do a flip and get reward', 'fa-link', 'https://www.google.com', 3.00, 5, 5, 1, 0, 1, 'website', NULL, '2026-08-26 06:23:12', '2026-08-26 06:30:33');

-- --------------------------------------------------------

--
-- Table structure for table `task_history`
--

CREATE TABLE `task_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `reward_amount` decimal(10,2) NOT NULL,
  `is_claimed` tinyint(1) DEFAULT 0,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `task_history`
--

INSERT INTO `task_history` (`id`, `user_id`, `task_id`, `reward_amount`, `is_claimed`, `claimed_at`, `completed_at`, `ip_address`, `device_id`) VALUES
(1, 2, 5, 5.00, 1, NULL, '2026-08-24 17:04:03', NULL, NULL),
(2, 5, 5, 5.00, 1, NULL, '2026-08-24 17:04:32', NULL, NULL),
(3, 9, 5, 5.00, 1, NULL, '2026-08-24 17:04:03', NULL, NULL),
(4, 1, 5, 5.00, 1, NULL, '2026-08-24 17:04:32', NULL, NULL),
(5, 1, 5, 5.00, 1, NULL, '2026-08-24 17:04:03', NULL, NULL),
(6, 1, 5, 5.00, 1, NULL, '2026-08-24 17:04:32', NULL, NULL),
(7, 1, 5, 5.00, 1, NULL, '2026-08-24 17:04:03', NULL, NULL),
(8, 1, 5, 5.00, 1, NULL, '2026-08-24 17:04:32', NULL, NULL),
(9, 1, 5, 5.00, 1, NULL, '2026-08-24 17:04:32', NULL, NULL),
(10, 1, 5, 5.00, 1, NULL, '2026-08-24 17:04:32', NULL, NULL),
(11, 1, 5, 5.00, 1, NULL, '2026-08-24 17:04:03', NULL, NULL),
(12, 1, 1, 10.00, 1, NULL, '2026-03-08 13:21:59', NULL, NULL),
(13, 1, 2, 15.00, 1, NULL, '2026-03-13 13:21:59', NULL, NULL),
(14, 1, 3, 8.00, 1, NULL, '2026-03-18 13:21:59', NULL, NULL),
(15, 1, 4, 5.00, 1, NULL, '2026-03-23 13:21:59', NULL, NULL),
(16, 1, 1, 10.00, 1, NULL, '2026-03-28 13:21:59', NULL, NULL),
(17, 1, 2, 15.00, 1, NULL, '2026-04-02 13:21:59', NULL, NULL),
(18, 1, 3, 8.00, 1, NULL, '2026-04-07 13:21:59', NULL, NULL),
(19, 1, 4, 5.00, 1, NULL, '2026-04-12 13:21:59', NULL, NULL),
(20, 1, 1, 10.00, 1, NULL, '2026-04-17 13:21:59', NULL, NULL),
(21, 1, 2, 15.00, 1, NULL, '2026-04-22 13:21:59', NULL, NULL),
(22, 13, 3, 8.00, 1, NULL, '2026-04-27 13:21:59', NULL, NULL),
(23, 1, 4, 5.00, 1, NULL, '2026-05-02 13:21:59', NULL, NULL),
(24, 1, 1, 10.00, 1, NULL, '2026-05-07 13:21:59', NULL, NULL),
(25, 1, 2, 15.00, 1, NULL, '2026-05-12 13:21:59', NULL, NULL),
(26, 1, 3, 8.00, 1, NULL, '2026-05-17 13:21:59', NULL, NULL),
(27, 1, 4, 5.00, 1, NULL, '2026-05-22 13:21:59', NULL, NULL),
(28, 1, 1, 10.00, 1, NULL, '2026-05-27 13:21:59', NULL, NULL),
(29, 1, 2, 15.00, 1, NULL, '2026-06-01 13:21:59', NULL, NULL),
(30, 1, 3, 8.00, 1, NULL, '2026-06-06 13:21:59', NULL, NULL),
(31, 1, 4, 5.00, 1, NULL, '2026-06-11 13:21:59', NULL, NULL),
(32, 2, 1, 10.00, 1, NULL, '2026-03-18 13:21:59', NULL, NULL),
(33, 2, 2, 15.00, 1, NULL, '2026-03-23 13:21:59', NULL, NULL),
(34, 2, 3, 8.00, 1, NULL, '2026-03-28 13:21:59', NULL, NULL),
(35, 2, 4, 5.00, 1, NULL, '2026-04-02 13:21:59', NULL, NULL),
(36, 2, 1, 10.00, 1, NULL, '2026-04-07 13:21:59', NULL, NULL),
(37, 2, 2, 15.00, 1, NULL, '2026-04-12 13:21:59', NULL, NULL),
(38, 2, 3, 8.00, 1, NULL, '2026-04-17 13:21:59', NULL, NULL),
(39, 2, 4, 5.00, 1, NULL, '2026-04-22 13:21:59', NULL, NULL),
(40, 2, 1, 10.00, 1, NULL, '2026-04-27 13:21:59', NULL, NULL),
(41, 2, 2, 15.00, 1, NULL, '2026-05-02 13:21:59', NULL, NULL),
(42, 2, 3, 8.00, 1, NULL, '2026-05-07 13:21:59', NULL, NULL),
(43, 2, 4, 5.00, 1, NULL, '2026-05-12 13:21:59', NULL, NULL),
(44, 2, 1, 10.00, 1, NULL, '2026-05-17 13:21:59', NULL, NULL),
(45, 2, 2, 15.00, 1, NULL, '2026-05-22 13:21:59', NULL, NULL),
(46, 2, 3, 8.00, 1, NULL, '2026-05-27 13:21:59', NULL, NULL),
(47, 2, 4, 5.00, 1, NULL, '2026-06-01 13:21:59', NULL, NULL),
(48, 2, 1, 10.00, 1, NULL, '2026-06-06 13:21:59', NULL, NULL),
(49, 2, 2, 15.00, 1, NULL, '2026-06-11 13:21:59', NULL, NULL),
(50, 1, 1, 10.00, 1, NULL, '2026-03-28 13:21:59', NULL, NULL),
(51, 1, 2, 15.00, 1, NULL, '2026-04-02 13:21:59', NULL, NULL),
(52, 1, 3, 8.00, 1, NULL, '2026-04-07 13:21:59', NULL, NULL),
(53, 1, 4, 5.00, 1, NULL, '2026-04-12 13:21:59', NULL, NULL),
(54, 1, 1, 10.00, 1, NULL, '2026-04-17 13:21:59', NULL, NULL),
(55, 1, 2, 15.00, 1, NULL, '2026-04-22 13:21:59', NULL, NULL),
(56, 1, 3, 8.00, 1, NULL, '2026-04-27 13:21:59', NULL, NULL),
(57, 1, 4, 5.00, 1, NULL, '2026-05-02 13:21:59', NULL, NULL),
(58, 1, 1, 10.00, 1, NULL, '2026-05-07 13:21:59', NULL, NULL),
(59, 1, 2, 15.00, 1, NULL, '2026-05-12 13:21:59', NULL, NULL),
(60, 1, 3, 8.00, 1, NULL, '2026-05-17 13:21:59', NULL, NULL),
(61, 1, 4, 5.00, 1, NULL, '2026-05-22 13:21:59', NULL, NULL),
(62, 1, 1, 10.00, 1, NULL, '2026-05-27 13:21:59', NULL, NULL),
(63, 1, 2, 15.00, 1, NULL, '2026-06-01 13:21:59', NULL, NULL),
(64, 1, 3, 8.00, 1, NULL, '2026-06-06 13:21:59', NULL, NULL),
(65, 1, 4, 5.00, 1, NULL, '2026-06-11 13:21:59', NULL, NULL),
(66, 4, 1, 10.00, 1, NULL, '2026-04-07 13:21:59', NULL, NULL),
(67, 4, 2, 15.00, 1, NULL, '2026-04-12 13:21:59', NULL, NULL),
(68, 4, 3, 8.00, 1, NULL, '2026-04-17 13:21:59', NULL, NULL),
(69, 4, 4, 5.00, 1, NULL, '2026-04-22 13:21:59', NULL, NULL),
(70, 4, 1, 10.00, 1, NULL, '2026-04-27 13:21:59', NULL, NULL),
(71, 4, 2, 15.00, 1, NULL, '2026-05-02 13:21:59', NULL, NULL),
(72, 4, 3, 8.00, 1, NULL, '2026-05-07 13:21:59', NULL, NULL),
(73, 4, 4, 5.00, 1, NULL, '2026-05-12 13:21:59', NULL, NULL),
(74, 4, 1, 10.00, 1, NULL, '2026-05-17 13:21:59', NULL, NULL),
(75, 4, 2, 15.00, 1, NULL, '2026-05-22 13:21:59', NULL, NULL),
(76, 4, 3, 8.00, 1, NULL, '2026-05-27 13:21:59', NULL, NULL),
(77, 4, 4, 5.00, 1, NULL, '2026-06-01 13:21:59', NULL, NULL),
(78, 4, 1, 10.00, 1, NULL, '2026-06-06 13:21:59', NULL, NULL),
(79, 4, 2, 15.00, 1, NULL, '2026-06-11 13:21:59', NULL, NULL),
(80, 5, 1, 10.00, 1, NULL, '2026-04-17 13:21:59', NULL, NULL),
(81, 5, 2, 15.00, 1, NULL, '2026-04-22 13:21:59', NULL, NULL),
(82, 5, 3, 8.00, 1, NULL, '2026-04-27 13:21:59', NULL, NULL),
(83, 5, 4, 5.00, 1, NULL, '2026-05-02 13:21:59', NULL, NULL),
(84, 5, 1, 10.00, 1, NULL, '2026-05-07 13:21:59', NULL, NULL),
(85, 5, 2, 15.00, 1, NULL, '2026-05-12 13:21:59', NULL, NULL),
(86, 5, 3, 8.00, 1, NULL, '2026-05-17 13:21:59', NULL, NULL),
(87, 5, 4, 5.00, 1, NULL, '2026-05-22 13:21:59', NULL, NULL),
(88, 5, 1, 10.00, 1, NULL, '2026-05-27 13:21:59', NULL, NULL),
(89, 5, 2, 15.00, 1, NULL, '2026-06-01 13:21:59', NULL, NULL),
(90, 5, 3, 8.00, 1, NULL, '2026-06-06 13:21:59', NULL, NULL),
(91, 5, 4, 5.00, 1, NULL, '2026-06-11 13:21:59', NULL, NULL),
(92, 6, 1, 10.00, 1, NULL, '2026-06-16 13:31:16', NULL, NULL),
(93, 6, 2, 15.00, 1, NULL, '2026-06-21 13:31:16', NULL, NULL),
(94, 6, 3, 8.00, 1, NULL, '2026-06-26 13:31:16', NULL, NULL),
(95, 6, 4, 5.00, 1, NULL, '2026-07-01 13:31:16', NULL, NULL),
(96, 6, 1, 10.00, 1, NULL, '2026-07-06 13:31:16', NULL, NULL),
(97, 7, 2, 15.00, 1, NULL, '2026-06-26 13:31:16', NULL, NULL),
(98, 7, 3, 8.00, 1, NULL, '2026-07-01 13:31:16', NULL, NULL),
(99, 7, 4, 5.00, 1, NULL, '2026-07-06 13:31:16', NULL, NULL),
(100, 7, 1, 10.00, 1, NULL, '2026-07-11 13:31:16', NULL, NULL),
(101, 7, 2, 15.00, 1, NULL, '2026-07-16 13:31:16', NULL, NULL),
(102, 7, 3, 8.00, 1, NULL, '2026-07-21 13:31:16', NULL, NULL),
(103, 7, 4, 5.00, 1, NULL, '2026-07-26 13:31:16', NULL, NULL),
(104, 8, 1, 10.00, 1, NULL, '2026-05-22 13:31:16', NULL, NULL),
(105, 8, 2, 15.00, 1, NULL, '2026-05-27 13:31:16', NULL, NULL),
(106, 8, 3, 8.00, 1, NULL, '2026-06-01 13:31:16', NULL, NULL),
(107, 8, 4, 5.00, 1, NULL, '2026-06-06 13:31:16', NULL, NULL),
(108, 8, 1, 10.00, 1, NULL, '2026-06-11 13:31:16', NULL, NULL),
(109, 8, 2, 15.00, 1, NULL, '2026-06-16 13:31:16', NULL, NULL),
(110, 8, 3, 8.00, 1, NULL, '2026-06-21 13:31:16', NULL, NULL),
(111, 8, 4, 5.00, 1, NULL, '2026-06-26 13:31:16', NULL, NULL),
(112, 8, 1, 10.00, 1, NULL, '2026-07-01 13:31:16', NULL, NULL),
(113, 8, 2, 15.00, 1, NULL, '2026-07-06 13:31:16', NULL, NULL),
(114, 9, 3, 8.00, 1, NULL, '2026-06-01 13:31:16', NULL, NULL),
(115, 9, 4, 5.00, 1, NULL, '2026-06-06 13:31:16', NULL, NULL),
(116, 9, 1, 10.00, 1, NULL, '2026-06-11 13:31:16', NULL, NULL),
(117, 9, 2, 15.00, 1, NULL, '2026-06-16 13:31:16', NULL, NULL),
(118, 9, 3, 8.00, 1, NULL, '2026-06-21 13:31:16', NULL, NULL),
(119, 9, 4, 5.00, 1, NULL, '2026-06-26 13:31:16', NULL, NULL),
(120, 9, 1, 10.00, 1, NULL, '2026-07-01 13:31:16', NULL, NULL),
(121, 9, 2, 15.00, 1, NULL, '2026-07-06 13:31:16', NULL, NULL),
(122, 9, 3, 8.00, 1, NULL, '2026-07-11 13:31:16', NULL, NULL),
(123, 9, 4, 5.00, 1, NULL, '2026-07-16 13:31:16', NULL, NULL),
(124, 10, 1, 10.00, 1, NULL, '2026-06-11 13:31:16', NULL, NULL),
(125, 10, 2, 15.00, 1, NULL, '2026-06-16 13:31:16', NULL, NULL),
(126, 10, 3, 8.00, 1, NULL, '2026-06-21 13:31:16', NULL, NULL),
(127, 10, 4, 5.00, 1, NULL, '2026-06-26 13:31:16', NULL, NULL),
(128, 10, 1, 10.00, 1, NULL, '2026-07-01 13:31:16', NULL, NULL),
(129, 10, 2, 15.00, 1, NULL, '2026-07-06 13:31:16', NULL, NULL),
(130, 10, 3, 8.00, 1, NULL, '2026-07-11 13:31:16', NULL, NULL),
(131, 10, 4, 5.00, 1, NULL, '2026-07-16 13:31:16', NULL, NULL),
(132, 10, 1, 10.00, 1, NULL, '2026-07-21 13:31:16', NULL, NULL),
(133, 10, 2, 15.00, 1, NULL, '2026-07-26 13:31:16', NULL, NULL),
(134, 11, 3, 8.00, 1, NULL, '2026-06-16 13:31:16', NULL, NULL),
(135, 11, 4, 5.00, 1, NULL, '2026-06-21 13:31:16', NULL, NULL),
(136, 11, 1, 10.00, 1, NULL, '2026-06-26 13:31:16', NULL, NULL),
(137, 11, 2, 15.00, 1, NULL, '2026-07-01 13:31:16', NULL, NULL),
(138, 11, 3, 8.00, 1, NULL, '2026-07-06 13:31:16', NULL, NULL),
(139, 11, 4, 5.00, 1, NULL, '2026-07-11 13:31:16', NULL, NULL),
(140, 11, 1, 10.00, 1, NULL, '2026-07-16 13:31:16', NULL, NULL),
(141, 11, 2, 15.00, 1, NULL, '2026-07-21 13:31:16', NULL, NULL),
(142, 11, 3, 8.00, 1, NULL, '2026-07-26 13:31:16', NULL, NULL),
(143, 11, 4, 5.00, 1, NULL, '2026-07-31 13:31:16', NULL, NULL),
(144, 11, 1, 10.00, 1, NULL, '2026-08-05 13:31:16', NULL, NULL),
(145, 11, 2, 15.00, 1, NULL, '2026-08-10 13:31:16', NULL, NULL),
(146, 12, 3, 8.00, 1, NULL, '2026-06-21 13:31:16', NULL, NULL),
(147, 12, 4, 5.00, 1, NULL, '2026-06-26 13:31:16', NULL, NULL),
(148, 12, 1, 10.00, 1, NULL, '2026-07-01 13:31:16', NULL, NULL),
(149, 12, 2, 15.00, 1, NULL, '2026-07-06 13:31:16', NULL, NULL),
(150, 12, 3, 8.00, 1, NULL, '2026-07-11 13:31:16', NULL, NULL),
(151, 12, 4, 5.00, 1, NULL, '2026-07-16 13:31:16', NULL, NULL),
(152, 12, 1, 10.00, 1, NULL, '2026-07-21 13:31:16', NULL, NULL),
(153, 12, 2, 15.00, 1, NULL, '2026-07-26 13:31:16', NULL, NULL),
(154, 12, 3, 8.00, 1, NULL, '2026-07-31 13:31:16', NULL, NULL),
(155, 12, 4, 5.00, 1, NULL, '2026-08-05 13:31:16', NULL, NULL),
(156, 12, 1, 10.00, 1, NULL, '2026-08-10 13:31:16', NULL, NULL),
(157, 12, 2, 15.00, 1, NULL, '2026-08-15 13:31:16', NULL, NULL),
(158, 13, 3, 8.00, 1, NULL, '2026-06-26 13:31:16', NULL, NULL),
(159, 13, 4, 5.00, 1, NULL, '2026-07-01 13:31:16', NULL, NULL),
(160, 13, 1, 10.00, 1, NULL, '2026-07-06 13:31:16', NULL, NULL),
(161, 13, 2, 15.00, 1, NULL, '2026-07-11 13:31:16', NULL, NULL),
(162, 13, 3, 8.00, 1, NULL, '2026-07-16 13:31:16', NULL, NULL),
(163, 13, 4, 5.00, 1, NULL, '2026-07-21 13:31:16', NULL, NULL),
(164, 13, 1, 10.00, 1, NULL, '2026-07-26 13:31:16', NULL, NULL),
(165, 13, 2, 15.00, 1, NULL, '2026-07-31 13:31:16', NULL, NULL),
(166, 13, 3, 8.00, 1, NULL, '2026-08-05 13:31:16', NULL, NULL),
(167, 13, 4, 5.00, 1, NULL, '2026-08-10 13:31:16', NULL, NULL),
(168, 13, 1, 10.00, 1, NULL, '2026-08-15 13:31:16', NULL, NULL),
(169, 13, 2, 15.00, 1, NULL, '2026-08-20 13:31:16', NULL, NULL),
(170, 14, 3, 8.00, 1, NULL, '2026-07-01 13:31:16', NULL, NULL),
(171, 14, 4, 5.00, 1, NULL, '2026-07-06 13:31:16', NULL, NULL),
(172, 14, 1, 10.00, 1, NULL, '2026-07-11 13:31:16', NULL, NULL),
(173, 14, 2, 15.00, 1, NULL, '2026-07-16 13:31:16', NULL, NULL),
(174, 14, 3, 8.00, 1, NULL, '2026-07-21 13:31:16', NULL, NULL),
(175, 14, 4, 5.00, 1, NULL, '2026-07-26 13:31:16', NULL, NULL),
(176, 14, 1, 10.00, 1, NULL, '2026-07-31 13:31:16', NULL, NULL),
(177, 14, 2, 15.00, 1, NULL, '2026-08-05 13:31:16', NULL, NULL),
(178, 14, 3, 8.00, 1, NULL, '2026-08-10 13:31:16', NULL, NULL),
(179, 14, 4, 5.00, 1, NULL, '2026-08-15 13:31:16', NULL, NULL),
(180, 15, 1, 10.00, 1, NULL, '2026-07-06 13:31:16', NULL, NULL),
(181, 15, 2, 15.00, 1, NULL, '2026-07-11 13:31:16', NULL, NULL),
(182, 15, 3, 8.00, 1, NULL, '2026-07-16 13:31:16', NULL, NULL),
(183, 15, 4, 5.00, 1, NULL, '2026-07-21 13:31:16', NULL, NULL),
(184, 15, 1, 10.00, 1, NULL, '2026-07-26 13:31:16', NULL, NULL),
(185, 15, 2, 15.00, 1, NULL, '2026-07-31 13:31:16', NULL, NULL),
(186, 15, 3, 8.00, 1, NULL, '2026-08-05 13:31:16', NULL, NULL),
(187, 15, 4, 5.00, 1, NULL, '2026-08-10 13:31:16', NULL, NULL),
(188, 15, 1, 10.00, 1, NULL, '2026-08-15 13:31:16', NULL, NULL),
(189, 15, 2, 15.00, 1, NULL, '2026-08-20 13:31:16', NULL, NULL),
(190, 16, 3, 8.00, 1, NULL, '2026-07-11 13:31:16', NULL, NULL),
(191, 16, 4, 5.00, 1, NULL, '2026-07-16 13:31:16', NULL, NULL),
(192, 16, 1, 10.00, 1, NULL, '2026-07-21 13:31:16', NULL, NULL),
(193, 16, 2, 15.00, 1, NULL, '2026-07-26 13:31:16', NULL, NULL),
(194, 16, 3, 8.00, 1, NULL, '2026-07-31 13:31:16', NULL, NULL),
(195, 16, 4, 5.00, 1, NULL, '2026-08-05 13:31:16', NULL, NULL),
(196, 16, 1, 10.00, 1, NULL, '2026-08-10 13:31:16', NULL, NULL),
(197, 16, 2, 15.00, 1, NULL, '2026-08-15 13:31:16', NULL, NULL),
(198, 17, 3, 8.00, 1, NULL, '2026-07-16 13:31:16', NULL, NULL),
(199, 17, 4, 5.00, 1, NULL, '2026-07-21 13:31:16', NULL, NULL),
(200, 17, 1, 10.00, 1, NULL, '2026-07-26 13:31:16', NULL, NULL),
(201, 17, 2, 15.00, 1, NULL, '2026-07-31 13:31:16', NULL, NULL),
(202, 17, 3, 8.00, 1, NULL, '2026-08-05 13:31:16', NULL, NULL),
(203, 17, 4, 5.00, 1, NULL, '2026-08-10 13:31:16', NULL, NULL),
(204, 17, 1, 10.00, 1, NULL, '2026-08-15 13:31:16', NULL, NULL),
(205, 17, 2, 15.00, 1, NULL, '2026-08-20 13:31:16', NULL, NULL),
(206, 18, 3, 8.00, 1, NULL, '2026-07-21 13:31:16', NULL, NULL),
(207, 18, 4, 5.00, 1, NULL, '2026-07-26 13:31:16', NULL, NULL),
(208, 18, 1, 10.00, 1, NULL, '2026-07-31 13:31:16', NULL, NULL),
(209, 18, 2, 15.00, 1, NULL, '2026-08-05 13:31:16', NULL, NULL),
(210, 18, 3, 8.00, 1, NULL, '2026-08-10 13:31:16', NULL, NULL),
(211, 18, 4, 5.00, 1, NULL, '2026-08-15 13:31:16', NULL, NULL),
(212, 19, 1, 10.00, 1, NULL, '2026-07-26 13:31:16', NULL, NULL),
(213, 19, 2, 15.00, 1, NULL, '2026-07-31 13:31:16', NULL, NULL),
(214, 19, 3, 8.00, 1, NULL, '2026-08-05 13:31:16', NULL, NULL),
(215, 19, 4, 5.00, 1, NULL, '2026-08-10 13:31:16', NULL, NULL),
(216, 19, 1, 10.00, 1, NULL, '2026-08-15 13:31:16', NULL, NULL),
(217, 19, 2, 15.00, 1, NULL, '2026-08-20 13:31:16', NULL, NULL),
(218, 20, 3, 8.00, 1, NULL, '2026-07-31 13:31:16', NULL, NULL),
(219, 20, 4, 5.00, 1, NULL, '2026-08-05 13:31:16', NULL, NULL),
(220, 20, 1, 10.00, 1, NULL, '2026-08-10 13:31:16', NULL, NULL),
(221, 20, 2, 15.00, 1, NULL, '2026-08-15 13:31:16', NULL, NULL),
(222, 20, 3, 8.00, 1, NULL, '2026-08-20 13:31:16', NULL, NULL),
(223, 20, 4, 5.00, 1, NULL, '2026-08-23 13:31:16', NULL, NULL),
(224, 1, 9, 3.00, 1, NULL, '2026-08-26 06:31:24', NULL, NULL),
(225, 1, 9, 3.00, 1, NULL, '2026-08-26 06:31:38', NULL, NULL),
(226, 1, 5, 5.00, 1, NULL, '2026-08-26 06:32:00', NULL, NULL),
(227, 1, 9, 3.00, 1, NULL, '2026-08-26 06:39:06', NULL, NULL),
(228, 1, 9, 3.00, 1, NULL, '2026-08-26 06:39:31', NULL, NULL),
(229, 1, 9, 3.00, 1, NULL, '2026-08-26 06:45:59', NULL, NULL);

--
-- Triggers `task_history`
--
DELIMITER $$
CREATE TRIGGER `after_task_history_delete` AFTER DELETE ON `task_history` FOR EACH ROW BEGIN
    IF OLD.is_claimed = 1 THEN
        UPDATE referrals r
        SET 
            r.referred_user_tasks_completed = (
                SELECT COUNT(*) 
                FROM task_history 
                WHERE user_id = OLD.user_id 
                AND is_claimed = 1
            ),
            r.referred_user_active = CASE 
                WHEN (
                    SELECT COUNT(*) 
                    FROM task_history 
                    WHERE user_id = OLD.user_id 
                    AND is_claimed = 1
                ) > 0 THEN 1 
                ELSE 0 
            END,
            r.referred_user_verified = (
                SELECT u.is_verified 
                FROM users u 
                WHERE u.id = OLD.user_id
            ),
            r.is_genuine = CASE 
                WHEN (
                    SELECT u.is_verified 
                    FROM users u 
                    WHERE u.id = OLD.user_id
                ) = 1 
                AND (
                    SELECT COUNT(*) 
                    FROM task_history 
                    WHERE user_id = OLD.user_id 
                    AND is_claimed = 1
                ) >= 3 THEN 1 
                ELSE 0 
            END,
            r.validation_status = CASE 
                WHEN (
                    SELECT u.is_verified 
                    FROM users u 
                    WHERE u.id = OLD.user_id
                ) = 1 
                AND (
                    SELECT COUNT(*) 
                    FROM task_history 
                    WHERE user_id = OLD.user_id 
                    AND is_claimed = 1
                ) >= 3 THEN 'verified'
                ELSE 'pending' 
            END,
            r.updated_at = NOW()
        WHERE r.referred_user_id = OLD.user_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_task_history_insert` AFTER INSERT ON `task_history` FOR EACH ROW BEGIN
    IF NEW.is_claimed = 1 THEN
        UPDATE referrals r
        SET 
            r.referred_user_tasks_completed = (
                SELECT COUNT(*) 
                FROM task_history 
                WHERE user_id = NEW.user_id 
                AND is_claimed = 1
            ),
            r.referred_user_active = 1,
            r.referred_user_verified = (
                SELECT u.is_verified 
                FROM users u 
                WHERE u.id = NEW.user_id
            ),
            r.is_genuine = CASE 
                WHEN (
                    SELECT u.is_verified 
                    FROM users u 
                    WHERE u.id = NEW.user_id
                ) = 1 
                AND (
                    SELECT COUNT(*) 
                    FROM task_history 
                    WHERE user_id = NEW.user_id 
                    AND is_claimed = 1
                ) >= 3 THEN 1 
                ELSE 0 
            END,
            r.validation_status = CASE 
                WHEN (
                    SELECT u.is_verified 
                    FROM users u 
                    WHERE u.id = NEW.user_id
                ) = 1 
                AND (
                    SELECT COUNT(*) 
                    FROM task_history 
                    WHERE user_id = NEW.user_id 
                    AND is_claimed = 1
                ) >= 3 THEN 'verified'
                ELSE 'pending' 
            END,
            r.updated_at = NOW()
        WHERE r.referred_user_id = NEW.user_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_task_history_update` AFTER UPDATE ON `task_history` FOR EACH ROW BEGIN
    IF NEW.user_id != OLD.user_id OR NEW.is_claimed != OLD.is_claimed THEN
        IF OLD.is_claimed = 1 THEN
            UPDATE referrals r
            SET 
                r.referred_user_tasks_completed = (
                    SELECT COUNT(*) 
                    FROM task_history 
                    WHERE user_id = OLD.user_id 
                    AND is_claimed = 1
                ),
                r.referred_user_active = CASE 
                    WHEN (
                        SELECT COUNT(*) 
                        FROM task_history 
                        WHERE user_id = OLD.user_id 
                        AND is_claimed = 1
                    ) > 0 THEN 1 
                    ELSE 0 
                END,
                r.referred_user_verified = (
                    SELECT u.is_verified 
                    FROM users u 
                    WHERE u.id = OLD.user_id
                ),
                r.is_genuine = CASE 
                    WHEN (
                        SELECT u.is_verified 
                        FROM users u 
                        WHERE u.id = OLD.user_id
                    ) = 1 
                    AND (
                        SELECT COUNT(*) 
                        FROM task_history 
                        WHERE user_id = OLD.user_id 
                        AND is_claimed = 1
                    ) >= 3 THEN 1 
                    ELSE 0 
                END,
                r.validation_status = CASE 
                    WHEN (
                        SELECT u.is_verified 
                        FROM users u 
                        WHERE u.id = OLD.user_id
                    ) = 1 
                    AND (
                        SELECT COUNT(*) 
                        FROM task_history 
                        WHERE user_id = OLD.user_id 
                        AND is_claimed = 1
                    ) >= 3 THEN 'verified'
                    ELSE 'pending' 
                END,
                r.updated_at = NOW()
            WHERE r.referred_user_id = OLD.user_id;
        END IF;
        IF NEW.is_claimed = 1 THEN
            UPDATE referrals r
            SET 
                r.referred_user_tasks_completed = (
                    SELECT COUNT(*) 
                    FROM task_history 
                    WHERE user_id = NEW.user_id 
                    AND is_claimed = 1
                ),
                r.referred_user_active = 1,
                r.referred_user_verified = (
                    SELECT u.is_verified 
                    FROM users u 
                    WHERE u.id = NEW.user_id
                ),
                r.is_genuine = CASE 
                    WHEN (
                        SELECT u.is_verified 
                        FROM users u 
                        WHERE u.id = NEW.user_id
                    ) = 1 
                    AND (
                        SELECT COUNT(*) 
                        FROM task_history 
                        WHERE user_id = NEW.user_id 
                        AND is_claimed = 1
                    ) >= 3 THEN 1 
                    ELSE 0 
                END,
                r.validation_status = CASE 
                    WHEN (
                        SELECT u.is_verified 
                        FROM users u 
                        WHERE u.id = NEW.user_id
                    ) = 1 
                    AND (
                        SELECT COUNT(*) 
                        FROM task_history 
                        WHERE user_id = NEW.user_id 
                        AND is_claimed = 1
                    ) >= 3 THEN 'verified'
                    ELSE 'pending' 
                END,
                r.updated_at = NOW()
            WHERE r.referred_user_id = NEW.user_id;
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `country_code` varchar(5) DEFAULT '+91',
  `phone_number` varchar(20) DEFAULT NULL,
  `referral_code` varchar(20) NOT NULL,
  `referred_by` int(11) DEFAULT NULL,
  `wallet_balance` decimal(12,2) DEFAULT 0.00,
  `total_earnings` decimal(12,2) DEFAULT 0.00,
  `referral_earnings` decimal(12,2) DEFAULT 0.00,
  `task_earnings` decimal(12,2) DEFAULT 0.00,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_blocked` tinyint(1) DEFAULT 0,
  `is_fraud_flag` tinyint(1) DEFAULT 0,
  `fraud_reason` text DEFAULT NULL,
  `firebase_uid` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_verified` tinyint(1) DEFAULT 0,
  `last_activity_date` timestamp NULL DEFAULT NULL,
  `activity_score` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `country_code`, `phone_number`, `referral_code`, `referred_by`, `wallet_balance`, `total_earnings`, `referral_earnings`, `task_earnings`, `is_verified`, `verification_token`, `reset_token`, `reset_token_expiry`, `is_active`, `is_blocked`, `is_fraud_flag`, `fraud_reason`, `firebase_uid`, `ip_address`, `last_login`, `created_at`, `updated_at`, `email_verified`, `last_activity_date`, `activity_score`) VALUES
(1, 'Aahil Lone', 'lonerashed9@gmail.com', '$2y$10$trZ0NVvnG8q0WY0eGghD1uOWgFmF3O2SpmWX0VNM3g5.470AMNKcu', '+91', '+91 7006030778', 'SYJ657', NULL, 7020.00, 1010.00, 300.00, 210.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, '2026-08-28 05:37:51', '2026-02-26 13:21:59', '2026-08-28 05:37:51', 0, '2026-08-26 06:32:00', 3),
(2, 'Priya Patel', 'priya.p@email.com', '$2y$10$dummyhash2', '+91', NULL, 'PRIYA456', NULL, 7200.00, 7752.00, 375.00, 177.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-03-13 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(3, 'Rahul Singh', 'rahul.s@email.com', '$2y$10$dummyhash3', '+91', NULL, 'RAHUL789', NULL, 6800.00, 7382.00, 375.00, 207.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-03-28 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(4, 'Sneha Reddy', 'sneha.r@email.com', '$2y$10$dummyhash4', '+91', NULL, 'SNEHA012', NULL, 6500.00, 7014.00, 375.00, 139.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-04-07 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(5, 'Vikram Joshi', 'vikram.j@email.com', '$2y$10$dummyhash5', '+91', NULL, 'VIKRAM34', NULL, 6200.00, 6689.00, 375.00, 114.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-04-17 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(6, 'Ananya Iyer', 'ananya.i@email.com', '$2y$10$dummyhash6', '+91', NULL, 'ANANYA56', NULL, 5800.00, 6223.00, 375.00, 48.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-04-27 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(7, 'Karan Mehta', 'karan.m@email.com', '$2y$10$dummyhash7', '+91', NULL, 'KARAN78', NULL, 5500.00, 5941.00, 375.00, 66.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-05-02 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(8, 'Meera Nair', 'meera.n@email.com', '$2y$10$dummyhash8', '+91', NULL, 'MEERA90', NULL, 5200.00, 5551.00, 250.00, 101.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-05-07 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(9, 'Arjun Desai', 'arjun.d@email.com', '$2y$10$dummyhash9', '+91', NULL, 'ARJUN11', NULL, 4900.00, 5214.00, 225.00, 89.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-05-17 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(10, 'Kavya Rao', 'kavya.r@email.com', '$2y$10$dummyhash10', '+91', NULL, 'KAVYA22', NULL, 4600.00, 4901.00, 200.00, 101.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-05-27 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(11, 'Rohan Gupta', 'rohan.g@email.com', '$2y$10$dummyhash11', '+91', NULL, 'ROHAN33', NULL, 4300.00, 4589.00, 175.00, 114.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-06-01 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(12, 'Divya Krishnan', 'divya.k@email.com', '$2y$10$dummyhash12', '+91', NULL, 'DIVYA44', NULL, 4000.00, 4264.00, 150.00, 114.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-06-06 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(13, 'Suresh Menon', 'suresh.m@email.com', '$2y$10$dummyhash13', '+91', NULL, 'SURESH55', NULL, 3800.00, 4064.00, 150.00, 114.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-06-11 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(14, 'Lakshmi Narayan', 'lakshmi.n@email.com', '$2y$10$dummyhash14', '+91', NULL, 'LAKSH66', NULL, 3600.00, 3814.00, 125.00, 89.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-06-16 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(15, 'Ganesh Iyer', 'ganesh.i@email.com', '$2y$10$dummyhash15', '+91', NULL, 'GANES77', NULL, 3400.00, 3626.00, 125.00, 101.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-06-21 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(16, 'Sarita Pillai', 'sarita.p@email.com', '$2y$10$dummyhash16', '+91', NULL, 'SARITA88', NULL, 3200.00, 3376.00, 100.00, 76.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-06-26 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(17, 'Mohan Raj', 'mohan.r@email.com', '$2y$10$dummyhash17', '+91', NULL, 'MOHAN99', NULL, 3000.00, 3176.00, 100.00, 76.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-07-01 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(18, 'Nandini Menon', 'nandini.m@email.com', '$2y$10$dummyhash18', '+91', NULL, 'NANDI00', NULL, 2800.00, 2926.00, 75.00, 51.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-07-06 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(19, 'Prakash Raj', 'prakash.r@email.com', '$2y$10$dummyhash19', '+91', NULL, 'PRAKA11', NULL, 2600.00, 2738.00, 75.00, 63.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-07-11 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0),
(20, 'Deepa Kumar', 'deepa.k@email.com', '$2y$10$dummyhash20', '+91', NULL, 'DEEPA22', NULL, 2400.00, 2526.00, 75.00, 51.00, 1, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL, NULL, '2026-07-16 13:21:59', '2026-08-25 13:31:16', 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_activity_summary`
--

CREATE TABLE `user_activity_summary` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `tasks_completed` int(11) DEFAULT 0,
  `referrals_made` int(11) DEFAULT 0,
  `referrals_active` int(11) DEFAULT 0,
  `referrals_verified` int(11) DEFAULT 0,
  `earnings` decimal(12,2) DEFAULT 0.00,
  `withdrawal_count` int(11) DEFAULT 0,
  `total_withdrawn` decimal(12,2) DEFAULT 0.00,
  `is_withdrawal_eligible` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_devices`
--

CREATE TABLE `user_devices` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_id` varchar(255) NOT NULL,
  `device_name` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `os_version` varchar(50) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `firebase_installation_id` varchar(255) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_leaderboard`
-- (See below for the actual view)
--
CREATE TABLE `v_leaderboard` (
`user_id` int(11)
,`full_name` varchar(100)
,`referral_code` varchar(20)
,`total_earnings` decimal(12,2)
,`referral_count` bigint(21)
,`task_count` bigint(21)
,`withdrawal_count` bigint(21)
,`total_withdrawn` decimal(34,2)
,`rank_position` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_today_stats`
-- (See below for the actual view)
--
CREATE TABLE `v_today_stats` (
`new_users` bigint(21)
,`new_referrals` bigint(21)
,`tasks_completed` bigint(21)
,`pending_withdrawals` bigint(21)
,`earnings_today` decimal(34,2)
,`paid_today` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_user_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_user_summary` (
`id` int(11)
,`full_name` varchar(100)
,`phone_number` varchar(20)
,`referral_code` varchar(20)
,`wallet_balance` decimal(12,2)
,`total_earnings` decimal(12,2)
,`referral_earnings` decimal(12,2)
,`task_earnings` decimal(12,2)
,`is_verified` tinyint(1)
,`is_active` tinyint(1)
,`is_blocked` tinyint(1)
,`created_at` timestamp
,`referral_count` bigint(21)
,`task_count` bigint(21)
,`withdrawal_count` bigint(21)
,`total_withdrawn` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_withdrawal_stats`
-- (See below for the actual view)
--
CREATE TABLE `v_withdrawal_stats` (
`total` bigint(21)
,`pending` decimal(23,0)
,`under_review` decimal(23,0)
,`approved` decimal(23,0)
,`rejected` decimal(23,0)
,`paid` decimal(23,0)
,`pending_amount` decimal(34,2)
,`paid_amount` decimal(34,2)
,`rejected_amount` decimal(34,2)
,`total_amount` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `transaction_type` enum('credit','debit','withdrawal','referral','task','bonus','admin_adjustment') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `balance_after` decimal(12,2) NOT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wallet_transactions`
--

INSERT INTO `wallet_transactions` (`id`, `user_id`, `amount`, `transaction_type`, `description`, `reference_id`, `balance_after`, `status`, `created_at`) VALUES
(1, 1, 2000.00, 'withdrawal', 'Withdrawal request #1 via bank_transfer', NULL, 6500.00, 'completed', '2026-08-25 22:35:29'),
(2, 1, 2500.00, 'withdrawal', 'Withdrawal request #2 via phonepe', NULL, 4000.00, 'completed', '2026-08-26 04:29:57'),
(3, 1, 3.00, 'task', 'Task #9 completed', NULL, 6006.00, 'completed', '2026-08-26 06:31:24'),
(4, 1, 3.00, 'task', 'Task #9 completed', NULL, 6009.00, 'completed', '2026-08-26 06:31:38'),
(5, 1, 5.00, 'task', 'Task #5 completed', NULL, 6016.00, 'completed', '2026-08-26 06:32:00'),
(6, 1, 3.00, 'task', 'Task #9 completed', NULL, 6017.00, 'completed', '2026-08-26 06:39:06'),
(7, 1, 3.00, 'task', 'Task #9 completed', NULL, 6020.00, 'completed', '2026-08-26 06:39:31'),
(8, 1, 3.00, 'task', 'Task #9 completed', NULL, 6023.00, 'completed', '2026-08-26 06:45:59'),
(12, 1, 2500.00, 'credit', 'Refund from rejected withdrawal #2', NULL, 11020.00, 'completed', '2026-08-26 07:11:32'),
(13, 1, 1500.00, 'withdrawal', 'Withdrawal request #3', NULL, 7020.00, 'pending', '2026-08-26 07:13:26');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `account_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`account_details`)),
  `status` enum('pending','under_review','approved','rejected','paid') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `requirements_met` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requirements_met`)),
  `validation_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `withdrawals`
--

INSERT INTO `withdrawals` (`id`, `user_id`, `amount`, `payment_method`, `account_details`, `status`, `admin_notes`, `processed_by`, `processed_at`, `created_at`, `updated_at`, `requirements_met`, `validation_notes`, `reviewed_by`, `reviewed_at`) VALUES
(1, 1, 2000.00, 'bank_transfer', '{\"account_number\":\"35674333665434\",\"ifsc\":\"JALA0tpbans\",\"account_holder\":\"Raashid\"}', 'rejected', NULL, NULL, NULL, '2026-08-25 22:35:29', '2026-08-26 04:50:05', NULL, NULL, NULL, NULL),
(2, 1, 2500.00, 'phonepe', '{\"phone\":\"7006030778\"}', 'rejected', 'Fake', 2, '2026-08-26 07:11:32', '2026-08-26 04:29:57', '2026-08-26 07:11:32', NULL, NULL, NULL, NULL),
(3, 1, 1500.00, 'phonepe', '{\"phone\":\"7006030778\"}', 'approved', 'Approved.', 2, '2026-08-26 07:19:32', '2026-08-26 07:13:26', '2026-08-26 07:19:32', '{\"met\":true,\"details\":{\"tasks\":{\"required\":\"10\",\"current\":25,\"met\":true},\"referrals\":{\"required\":\"10\",\"current\":12,\"total\":12,\"met\":true},\"amount\":{\"min\":\"1500.00\",\"max\":\"50000.00\",\"current\":1500,\"met\":true}},\"last_withdrawal_date\":\"Never\"}', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_admin_setting` (`admin_id`,`setting_key`);

--
-- Indexes for table `app_config`
--
ALTER TABLE `app_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`),
  ADD KEY `idx_config_key` (`config_key`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_display_order` (`display_order`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_key` (`template_key`);

--
-- Indexes for table `fraud_reports`
--
ALTER TABLE `fraud_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_fraud_type` (`fraud_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_fraud_reports_resolved_by` (`resolved_by`);

--
-- Indexes for table `leaderboard`
--
ALTER TABLE `leaderboard`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_period_user` (`user_id`,`period`,`week_start`,`month_start`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_period` (`period`),
  ADD KEY `idx_rank` (`rank_position`),
  ADD KEY `idx_updated_at` (`updated_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_is_sent` (`is_sent`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_admin_id` (`admin_id`);

--
-- Indexes for table `otp_logs`
--
ALTER TABLE `otp_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_phone` (`phone_number`),
  ADD KEY `idx_verification_id` (`verification_id`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `method_name` (`method_name`),
  ADD KEY `idx_is_enabled` (`is_enabled`),
  ADD KEY `idx_method_name` (`method_name`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_referral` (`referrer_id`,`referred_user_id`),
  ADD KEY `idx_referrer_id` (`referrer_id`),
  ADD KEY `idx_referred_user_id` (`referred_user_id`),
  ADD KEY `idx_referral_code` (`referral_code`),
  ADD KEY `idx_referrer_created` (`referrer_id`,`created_at`),
  ADD KEY `idx_referrals_referrer_created` (`referrer_id`,`created_at`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_task_type` (`task_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `task_history`
--
ALTER TABLE `task_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_task_id` (`task_id`),
  ADD KEY `idx_completed_at` (`completed_at`),
  ADD KEY `idx_user_task` (`user_id`,`task_id`),
  ADD KEY `idx_user_task_date` (`user_id`,`task_id`,`completed_at`),
  ADD KEY `idx_task_history_user_claimed_completed` (`user_id`,`is_claimed`,`completed_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_phone` (`phone_number`),
  ADD KEY `idx_referral` (`referral_code`),
  ADD KEY `idx_referred_by` (`referred_by`),
  ADD KEY `idx_firebase_uid` (`firebase_uid`),
  ADD KEY `idx_verification_token` (`verification_token`),
  ADD KEY `idx_reset_token` (`reset_token`);

--
-- Indexes for table `user_activity_summary`
--
ALTER TABLE `user_activity_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_period` (`user_id`,`period_start`);

--
-- Indexes for table `user_devices`
--
ALTER TABLE `user_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `firebase_installation_id` (`firebase_installation_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_device_id` (`device_id`),
  ADD KEY `idx_firebase_install` (`firebase_installation_id`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_reference_id` (`reference_id`),
  ADD KEY `idx_user_type_created` (`user_id`,`transaction_type`,`created_at`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_payment_method` (`payment_method`),
  ADD KEY `idx_user_status_created` (`user_id`,`status`,`created_at`),
  ADD KEY `fk_withdrawals_processed_by` (`processed_by`),
  ADD KEY `idx_withdrawals_user_status_created` (`user_id`,`status`,`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admin_settings`
--
ALTER TABLE `admin_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_config`
--
ALTER TABLE `app_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `fraud_reports`
--
ALTER TABLE `fraud_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leaderboard`
--
ALTER TABLE `leaderboard`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otp_logs`
--
ALTER TABLE `otp_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=293;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `task_history`
--
ALTER TABLE `task_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=230;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `user_activity_summary`
--
ALTER TABLE `user_activity_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_devices`
--
ALTER TABLE `user_devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

-- --------------------------------------------------------

--
-- Structure for view `v_leaderboard`
--
DROP TABLE IF EXISTS `v_leaderboard`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u226625657_paisapay`@`127.0.0.1` SQL SECURITY DEFINER VIEW `v_leaderboard`  AS SELECT `u`.`id` AS `user_id`, `u`.`full_name` AS `full_name`, `u`.`referral_code` AS `referral_code`, `u`.`total_earnings` AS `total_earnings`, count(distinct `r`.`id`) AS `referral_count`, count(distinct `th`.`id`) AS `task_count`, count(distinct `w`.`id`) AS `withdrawal_count`, coalesce(sum(case when `w`.`status` = 'paid' then `w`.`amount` else 0 end),0) AS `total_withdrawn`, rank() over ( order by `u`.`total_earnings` desc) AS `rank_position` FROM (((`users` `u` left join `referrals` `r` on(`u`.`id` = `r`.`referrer_id`)) left join `task_history` `th` on(`u`.`id` = `th`.`user_id` and `th`.`is_claimed` = 1)) left join `withdrawals` `w` on(`u`.`id` = `w`.`user_id`)) WHERE `u`.`is_active` = 1 AND `u`.`is_blocked` = 0 GROUP BY `u`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_today_stats`
--
DROP TABLE IF EXISTS `v_today_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u226625657_paisapay`@`127.0.0.1` SQL SECURITY DEFINER VIEW `v_today_stats`  AS SELECT (select count(0) from `users` where cast(`users`.`created_at` as date) = curdate()) AS `new_users`, (select count(0) from `referrals` where cast(`referrals`.`created_at` as date) = curdate()) AS `new_referrals`, (select count(0) from `task_history` where cast(`task_history`.`completed_at` as date) = curdate() and `task_history`.`is_claimed` = 1) AS `tasks_completed`, (select count(0) from `withdrawals` where cast(`withdrawals`.`created_at` as date) = curdate() and `withdrawals`.`status` = 'pending') AS `pending_withdrawals`, (select coalesce(sum(`wallet_transactions`.`amount`),0) from `wallet_transactions` where cast(`wallet_transactions`.`created_at` as date) = curdate() and `wallet_transactions`.`transaction_type` in ('referral','task','bonus')) AS `earnings_today`, (select coalesce(sum(`withdrawals`.`amount`),0) from `withdrawals` where cast(`withdrawals`.`created_at` as date) = curdate() and `withdrawals`.`status` = 'paid') AS `paid_today` ;

-- --------------------------------------------------------

--
-- Structure for view `v_user_summary`
--
DROP TABLE IF EXISTS `v_user_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u226625657_paisapay`@`127.0.0.1` SQL SECURITY DEFINER VIEW `v_user_summary`  AS SELECT `u`.`id` AS `id`, `u`.`full_name` AS `full_name`, `u`.`phone_number` AS `phone_number`, `u`.`referral_code` AS `referral_code`, `u`.`wallet_balance` AS `wallet_balance`, `u`.`total_earnings` AS `total_earnings`, `u`.`referral_earnings` AS `referral_earnings`, `u`.`task_earnings` AS `task_earnings`, `u`.`is_verified` AS `is_verified`, `u`.`is_active` AS `is_active`, `u`.`is_blocked` AS `is_blocked`, `u`.`created_at` AS `created_at`, count(distinct `r`.`id`) AS `referral_count`, count(distinct `th`.`id`) AS `task_count`, count(distinct `w`.`id`) AS `withdrawal_count`, coalesce(sum(case when `w`.`status` = 'paid' then `w`.`amount` else 0 end),0) AS `total_withdrawn` FROM (((`users` `u` left join `referrals` `r` on(`u`.`id` = `r`.`referrer_id`)) left join `task_history` `th` on(`u`.`id` = `th`.`user_id` and `th`.`is_claimed` = 1)) left join `withdrawals` `w` on(`u`.`id` = `w`.`user_id`)) GROUP BY `u`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `v_withdrawal_stats`
--
DROP TABLE IF EXISTS `v_withdrawal_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u226625657_paisapay`@`127.0.0.1` SQL SECURITY DEFINER VIEW `v_withdrawal_stats`  AS SELECT count(0) AS `total`, sum(case when `withdrawals`.`status` = 'pending' then 1 else 0 end) AS `pending`, sum(case when `withdrawals`.`status` = 'under_review' then 1 else 0 end) AS `under_review`, sum(case when `withdrawals`.`status` = 'approved' then 1 else 0 end) AS `approved`, sum(case when `withdrawals`.`status` = 'rejected' then 1 else 0 end) AS `rejected`, sum(case when `withdrawals`.`status` = 'paid' then 1 else 0 end) AS `paid`, coalesce(sum(case when `withdrawals`.`status` in ('pending','under_review') then `withdrawals`.`amount` else 0 end),0) AS `pending_amount`, coalesce(sum(case when `withdrawals`.`status` = 'paid' then `withdrawals`.`amount` else 0 end),0) AS `paid_amount`, coalesce(sum(case when `withdrawals`.`status` = 'rejected' then `withdrawals`.`amount` else 0 end),0) AS `rejected_amount`, coalesce(sum(`withdrawals`.`amount`),0) AS `total_amount` FROM `withdrawals` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD CONSTRAINT `fk_admin_settings_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fraud_reports`
--
ALTER TABLE `fraud_reports`
  ADD CONSTRAINT `fk_fraud_reports_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fraud_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leaderboard`
--
ALTER TABLE `leaderboard`
  ADD CONSTRAINT `fk_leaderboard_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referrals`
--
ALTER TABLE `referrals`
  ADD CONSTRAINT `fk_referrals_referred` FOREIGN KEY (`referred_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_referrals_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_history`
--
ALTER TABLE `task_history`
  ADD CONSTRAINT `fk_task_history_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_task_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_referred_by` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_activity_summary`
--
ALTER TABLE `user_activity_summary`
  ADD CONSTRAINT `user_activity_summary_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_devices`
--
ALTER TABLE `user_devices`
  ADD CONSTRAINT `fk_user_devices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `fk_wallet_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `fk_withdrawals_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_withdrawals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
