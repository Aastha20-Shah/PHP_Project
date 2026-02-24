-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 26, 2025 at 08:30 AM
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
-- Database: `keyvault`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `full_name`, `username`, `email`, `password`, `created_at`) VALUES
(2, 'Shah Aastha', 'ashah464', 'shahaastha2024@gmail.com', '$2y$10$8wL6txv7otQONDeP4AeVtO2rGgG5KLXzfqBQ2se0FEnpiFSoyAYs.', '2025-09-25 07:09:37');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `action` varchar(255) NOT NULL,
  `status` enum('success','warning','danger') DEFAULT 'success',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `user_name`, `action`, `status`, `created_at`) VALUES
(1, 1, 'Shah Aastha', 'Visited Audit Log page', 'success', '2025-09-21 22:53:10'),
(2, 1, 'Shah Aastha', 'Visited Audit Log page', 'success', '2025-09-21 22:53:18'),
(3, 1, 'Shah Aastha', 'Visited Audit Log page', 'success', '2025-09-21 22:53:41'),
(4, 1, 'Shah Aastha', 'Visited Audit Log page', 'success', '2025-09-21 22:54:06'),
(5, 1, 'Shah Aastha', 'Visited Audit Log page', 'success', '2025-09-21 23:03:05'),
(6, 1, 'Shah Aastha', 'Visited Audit Log page', 'success', '2025-09-21 23:04:56'),
(7, 1, 'Shah Aastha', 'Visited Audit Log page', 'success', '2025-09-21 23:05:49'),
(8, 1, 'Shah Aastha', 'Visited Audit Log page', 'success', '2025-09-21 23:06:37'),
(9, 1, 'Shah Aastha', 'Visited Audit Log page', 'success', '2025-09-21 23:12:22'),
(10, 1, 'Shah Aastha', 'File Upload: Blood Donation(SRS).docx', 'success', '2025-09-21 23:20:45'),
(11, 1, 'Shah Aastha', 'Visited Audit Log page', 'success', '2025-09-22 12:37:22'),
(12, 1, 'Shah Aastha', 'Visited Audit Log page', 'success', '2025-09-22 12:37:27'),
(13, 1, 'Shah Aastha', 'Visited Audit Log page', 'success', '2025-09-22 12:37:32'),
(14, 1, 'Shah Aastha', 'Visited Audit Log page', 'success', '2025-09-22 12:37:38'),
(15, 1, 'Shah Aastha', 'Visited Audit Log page', 'success', '2025-09-22 12:37:43'),
(16, 1, 'Shah Aastha', 'Visited documents.php', 'success', '2025-09-22 12:45:24'),
(17, 1, 'Shah Aastha', 'File Upload: ', 'success', '2025-09-22 12:45:24'),
(18, 1, 'Shah Aastha', 'Visited password.php', 'success', '2025-09-22 12:45:37'),
(19, 1, 'Shah Aastha', 'Added new password entry', 'success', '2025-09-22 12:45:37'),
(20, 1, 'Shah Aastha', 'Deleted password ID ', 'danger', '2025-09-22 12:45:37'),
(21, 1, 'Shah Aastha', 'Visited password.php', 'success', '2025-09-22 12:46:37'),
(22, 1, 'Shah Aastha', 'Added new password entry', 'success', '2025-09-22 12:46:37'),
(23, 1, 'Shah Aastha', 'Deleted password ID ', 'danger', '2025-09-22 12:46:37'),
(24, 1, 'Shah Aastha', 'Visited password.php', 'success', '2025-09-22 12:47:01'),
(25, 1, 'Shah Aastha', 'Added new password entry', 'success', '2025-09-22 12:47:01'),
(26, 1, 'Shah Aastha', 'Deleted password ID ', 'danger', '2025-09-22 12:47:01'),
(27, 1, 'Shah Aastha', 'Visited password.php', 'success', '2025-09-22 12:47:01'),
(28, 1, 'Shah Aastha', 'Added new password entry', 'success', '2025-09-22 12:47:01'),
(29, 1, 'Shah Aastha', 'Deleted password ID ', 'danger', '2025-09-22 12:47:01'),
(30, 1, 'Shah Aastha', 'Visited password.php', 'success', '2025-09-22 12:47:06'),
(31, 1, 'Shah Aastha', 'Added new password entry', 'success', '2025-09-22 12:47:06'),
(32, 1, 'Shah Aastha', 'Deleted password ID ', 'danger', '2025-09-22 12:47:06'),
(33, 1, 'Shah Aastha', 'Visited password.php', 'success', '2025-09-22 12:47:10'),
(34, 1, 'Shah Aastha', 'Added new password entry', 'success', '2025-09-22 12:47:10'),
(35, 1, 'Shah Aastha', 'Deleted password ID ', 'danger', '2025-09-22 12:47:10'),
(36, 1, 'Shah Aastha', 'Visited password.php', 'success', '2025-09-22 12:47:44'),
(37, 1, 'Shah Aastha', 'Added new password entry', 'success', '2025-09-22 12:47:44'),
(38, 1, 'Shah Aastha', 'Deleted password ID ', 'danger', '2025-09-22 12:47:44'),
(39, 1, 'Shah Aastha', 'Visited documents.php', 'success', '2025-09-22 12:50:00'),
(40, 1, 'Shah Aastha', 'File Upload: ', 'success', '2025-09-22 12:50:00'),
(41, 1, 'Shah Aastha', 'Visited paymentcards.php', 'success', '2025-09-22 12:50:03'),
(42, 1, 'Shah Aastha', 'Visited privatemedia.php', 'success', '2025-09-22 12:50:04'),
(43, 1, 'Shah Aastha', 'Visited paymentcards.php', 'success', '2025-09-22 12:50:05'),
(44, 1, 'Shah Aastha', 'Visited paymentcards.php', 'success', '2025-09-22 12:50:25'),
(45, 1, 'Shah Aastha', 'Visited privatemedia.php', 'success', '2025-09-22 12:50:26'),
(46, 1, 'Shah Aastha', 'Visited paymentcards.php', 'success', '2025-09-22 12:50:53'),
(47, 1, 'Shah Aastha', 'Visited login.php', 'success', '2025-09-22 12:53:31'),
(48, 1, 'Shah Aastha', 'Visited login.php', 'success', '2025-09-22 12:53:44'),
(49, 1, 'Shah Aastha', 'Visited paymentcards.php', 'success', '2025-09-22 12:54:08'),
(50, 1, 'Shah Aastha', 'Visited privatemedia.php', 'success', '2025-09-22 12:54:10'),
(51, 1, 'Shah Aastha', 'Visited documents.php', 'success', '2025-09-22 12:54:11'),
(52, 1, 'Shah Aastha', 'File Upload: ', 'success', '2025-09-22 12:54:11'),
(53, 1, 'Shah Aastha', 'Visited paymentcards.php', 'success', '2025-09-22 12:54:17'),
(54, 1, 'Shah Aastha', 'Visited paymentcards.php', 'success', '2025-09-22 12:54:24'),
(55, 1, 'Shah Aastha', 'Visited password.php', 'success', '2025-09-22 12:54:30'),
(56, 1, 'Shah Aastha', 'Added new password entry', 'success', '2025-09-22 12:54:30'),
(57, 1, 'Shah Aastha', 'Deleted password ID ', 'danger', '2025-09-22 12:54:30'),
(58, 1, 'Shah Aastha', 'Visited dashboard.php', 'success', '2025-09-22 12:54:31'),
(59, 1, 'Shah Aastha', 'Visited password.php', 'success', '2025-09-22 12:54:32'),
(60, 1, 'Shah Aastha', 'Added new password entry', 'success', '2025-09-22 12:54:32'),
(61, 1, 'Shah Aastha', 'Deleted password ID ', 'danger', '2025-09-22 12:54:32'),
(62, 1, '', 'Added password for Insta', 'success', '2025-09-22 13:05:04'),
(63, 1, 'Shah Aastha', 'File Upload: ', 'success', '2025-09-22 13:06:47'),
(64, 1, 'Shah Aastha', 'Added password for deepai', 'success', '2025-09-22 13:11:28'),
(65, 1, 'Shah Aastha', 'File Upload: ', 'success', '2025-09-22 13:11:51'),
(66, 1, 'Shah Aastha', 'File Upload: Blood Donation(SRS).docx', 'success', '2025-09-22 13:14:57'),
(67, 1, 'Shah Aastha', 'Delete password for ', 'success', '2025-09-22 13:41:25'),
(68, 1, 'Shah Aastha', 'Upadate password for Netflix', 'success', '2025-09-22 13:51:07'),
(69, 1, 'Shah Aastha', 'Delete password for Insta', 'success', '2025-09-22 13:51:16'),
(70, 1, 'Shah Aastha', 'File Updated: ', 'success', '2025-09-22 13:54:04'),
(71, 1, 'Shah Aastha', 'File Updated: Blood Donation(SRS).docx', 'success', '2025-09-22 13:57:24'),
(72, 1, 'Shah Aastha', 'File Deleted: Unknown', 'success', '2025-09-22 13:59:25'),
(73, 1, 'Shah Aastha', 'File Deleted: FENIL SHAH TICKET RAJ SBT.pdf', 'success', '2025-09-22 14:01:07'),
(74, 1, 'Shah Aastha', 'User logged out', 'warning', '2025-09-22 14:02:02'),
(75, 1, 'Shah Aastha', 'Delete password for Chatgpt', 'success', '2025-09-22 18:00:05'),
(76, 1, 'Shah Aastha', 'File Updated: ACCOUNT.xlsx', 'success', '2025-09-22 18:00:22'),
(77, 1, 'Shah Aastha', 'Added payment card (Mastercard - ****9789)', 'success', '2025-09-22 18:06:58'),
(78, 1, 'Shah Aastha', 'Updated payment card (American Express  - ****8431)', 'success', '2025-09-22 18:07:14'),
(79, 1, 'Shah Aastha', 'Deleted payment card ( - ****)', 'success', '2025-09-22 18:07:33'),
(80, 1, 'Shah Aastha', 'Deleted payment card (Visa - ****228	)', 'success', '2025-09-22 18:13:39'),
(81, 1, 'Shah Aastha', 'User logged out', 'warning', '2025-09-22 18:31:36'),
(82, 1, 'Shah Aastha', 'User logged out', 'warning', '2025-09-23 10:33:04'),
(83, 1, 'Shah Aastha', 'User logged out', 'warning', '2025-09-23 10:33:23'),
(84, 1, 'Shah Aastha', 'User logged out', 'warning', '2025-09-23 10:44:53'),
(85, 1, 'Shah Aastha', 'User logged out', 'warning', '2025-09-23 10:49:35'),
(86, 1, 'Shah Aastha', 'User logged out', 'warning', '2025-09-23 10:52:50'),
(87, 1, 'Shah Aastha', 'User logged out', 'warning', '2025-09-23 10:54:09'),
(88, 1, 'Shah Aastha', 'User logged out', 'warning', '2025-09-23 10:59:56'),
(89, 1, 'Shah Aastha', 'User logged out', 'warning', '2025-09-23 11:02:23'),
(90, 1, 'Shah Aastha', 'User logged out', 'warning', '2025-09-23 11:03:07'),
(91, 1, 'Shah Aastha', 'User logged out', 'warning', '2025-09-23 11:13:39'),
(92, 1, 'Shah Aastha', 'User logged out', 'warning', '2025-09-23 11:18:21'),
(93, 1, 'Shah Aastha', 'User logged out', 'warning', '2025-09-23 12:26:30'),
(94, 1, 'Shah Aastha', 'Added payment card (American Express  - ****9532)', 'success', '2025-09-23 13:10:02'),
(95, 1, 'Shah Aastha', 'Uploaded media (0G9A9776.JPG, 10040 KB, jpg)', 'success', '2025-09-23 13:18:46'),
(96, 1, 'Shah Aasthaa', 'User logged out', 'warning', '2025-09-25 12:25:15'),
(97, 1, 'Shah Aasthaa', 'User logged in', 'warning', '2025-09-25 12:25:31');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `size` varchar(50) NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `user_id`, `name`, `size`, `tags`, `notes`, `file_path`, `created_at`) VALUES
(2, 1, 'Resume.docx', '22 KB', 'Docs', 'Professional ', 'uploads/1757783101_Resume.docx', '2025-09-13 17:05:01'),
(5, 1, 'ACCOUNT.xlsx', '10 KB', 'Accounts', 'Accounts', 'uploads/1758477256_ACCOUNT.xlsx', '2025-09-21 17:54:16');

-- --------------------------------------------------------

--
-- Table structure for table `media`
--

CREATE TABLE `media` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `size` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `media`
--

INSERT INTO `media` (`id`, `user_id`, `name`, `size`, `type`, `tags`, `uploaded_at`, `file_path`) VALUES
(1, 1, 'E learning.jpg', '110 KB', 'jpg', 'Activity ', '2025-09-13 17:31:13', 'uploads/media/1757784673_E learning.jpg'),
(2, 1, 'Bank.jpg', '106 KB', 'jpg', '', '2025-09-22 12:54:43', 'uploads/media/1758545683_Bank.jpg'),
(3, 1, '0G9A9776.JPG', '10040 KB', 'jpg', 'photos', '2025-09-23 07:48:46', 'uploads/media/1758613726_0G9A9776.JPG');

-- --------------------------------------------------------

--
-- Table structure for table `passwords`
--

CREATE TABLE `passwords` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `website` varchar(150) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `username` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `tag` varchar(100) DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `passwords`
--

INSERT INTO `passwords` (`id`, `user_id`, `website`, `url`, `username`, `password`, `tag`, `updated`) VALUES
(19, 1, 'Insta', 'https://www.instagram.com/', 'ashah464', 'JiBwo1U&agj3', 'personal', '2025-09-26 04:44:13'),
(21, 1, 'Facebook', 'https://deepai.com/', 'ashah464', 'IfMAkn^JhN5j', 'personal', '2025-09-26 04:44:13'),
(22, 1, 'Snapchat', 'https://mail.google.com/', 'Admin', '$yFB0nI#(i4l', 'personal', '2025-09-26 04:44:13'),
(24, 1, 'deepai', 'https://deepai.com/', 'shahaastha2024', ')42Yj*1JOx6N', 'personal', '2025-09-26 04:44:13'),
(25, 1, 'Gmail', 'https://mail.google.com/', 'shahaastha2024', '6CJEnwHXDalQ', 'Work', '2025-09-26 04:44:13'),
(26, 1, 'Netflix', 'https://www.netflix.com/', 'ashah4645', 'XljnLUAJ87g5', 'personal', '2025-09-26 04:44:13'),
(28, 1, 'LMS', 'https://lms.rku.ac.in/', 'ashah4645', 'l4m%FQn3RH^o', 'Work', '2025-09-26 04:44:13'),
(30, 1, 'deepai', 'https://deepai.com/', 'ashah4645', 'sKgF@0P)RLnv', 'personal', '2025-09-26 04:44:13'),
(31, 1, 'visa', '', 'mahekkk', 'dHWxedcdWtHkv1uEH7o8jjuv5ff3f2ONCoDkBcsrBUbBkSgWPHOyhupdj86WAGb0', 'personal', '2025-09-26 05:16:53'),
(32, 1, 'Insta', 'https://lms.rku.ac.in/', 'Admin', '12345678', 'Work', '2025-09-26 05:18:02');

-- --------------------------------------------------------

--
-- Table structure for table `payment_cards`
--

CREATE TABLE `payment_cards` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `holder` varchar(100) NOT NULL,
  `brand` varchar(50) NOT NULL,
  `card_number` varchar(20) NOT NULL,
  `expiry` char(7) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_cards`
--

INSERT INTO `payment_cards` (`id`, `user_id`, `holder`, `brand`, `card_number`, `expiry`, `note`, `updated_at`) VALUES
(3, 1, 'Krisha', 'American Express ', '371449635398431', '2025-11', 'For Installment', '2025-09-22 12:37:14'),
(4, 1, 'Krisha', 'Visa', '6548971218517', '2025-12', 'For Installment', '2025-09-22 07:48:34'),
(6, 1, 'Aastha', 'American Express ', '123456879532', '2025-11', 'For Installment', '2025-09-23 07:40:02');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(100) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_active` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `verification_token` varchar(64) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rotation_enabled` tinyint(1) DEFAULT 0,
  `rotation_interval` int(11) DEFAULT 1,
  `last_rotation` datetime DEFAULT NULL,
  `website_lock_enabled` tinyint(1) DEFAULT 0,
  `website_lock_password` varchar(255) DEFAULT NULL,
  `password_manager_enabled` tinyint(1) DEFAULT 0,
  `audit_log_enabled` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `avatar`, `password`, `verification_token`, `verified`, `two_factor_enabled`, `created_at`, `rotation_enabled`, `rotation_interval`, `last_rotation`, `website_lock_enabled`, `website_lock_password`, `password_manager_enabled`, `audit_log_enabled`) VALUES
(1, 'Shah Aasthaa', 'shahaastha2024@gmail.com', 'uploads/68d24b2beddf7.JPG', '$2y$10$n5DMMli5jyMMReXrjqiKfuEXJT5dqkralJm3hFJ1kQumBGpigeZRO', NULL, 1, 0, '2025-09-12 16:22:33', 1, 1, '2025-09-26 10:14:13', 0, NULL, 1, 0),
(4, 'Aastha Shah', 'fenilshah3475@gmail.com', NULL, '$2y$10$aV9zB5bER1kZyOFnbzgfs.2/YMEHwY8hSsy.380mz9x4FvZDTuo5O', NULL, 1, 1, '2025-09-26 05:31:30', 1, 1, '2025-09-26 11:03:12', 0, NULL, 0, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_documents_user` (`user_id`);

--
-- Indexes for table `media`
--
ALTER TABLE `media`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `passwords`
--
ALTER TABLE `passwords`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user` (`user_id`);

--
-- Indexes for table `payment_cards`
--
ALTER TABLE `payment_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_payment_user` (`user_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `media`
--
ALTER TABLE `media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `passwords`
--
ALTER TABLE `passwords`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `payment_cards`
--
ALTER TABLE `payment_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `fk_documents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `passwords`
--
ALTER TABLE `passwords`
  ADD CONSTRAINT `fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_cards`
--
ALTER TABLE `payment_cards`
  ADD CONSTRAINT `fk_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
