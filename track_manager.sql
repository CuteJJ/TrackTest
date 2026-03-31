-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 31, 2026 at 08:27 AM
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
-- Database: `track_manager`
--
CREATE DATABASE IF NOT EXISTS `track_manager` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `track_manager`;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `printers`
--

CREATE TABLE `printers` (
  `id` int(11) NOT NULL,
  `model_name` varchar(50) NOT NULL,
  `printer_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `printers`
--

INSERT INTO `printers` (`id`, `model_name`, `printer_path`) VALUES
(1, 'Ray', NULL),
(2, 'Beam SFP', NULL),
(3, 'Beam MFP', NULL),
(4, 'Pixiu SFP', NULL),
(5, 'Pixiu MFP', NULL),
(6, 'Flare', NULL),
(8, 'Open Spark', 'imgs/printers/open_spark.png'),
(10, 'Open Spark 2', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `task_date` date NOT NULL,
  `testing_type` enum('Smoke','Regression') NOT NULL,
  `fw_version_current` varchar(100) DEFAULT NULL,
  `fw_version_prev` varchar(100) DEFAULT NULL,
  `fw_version_rec` varchar(100) DEFAULT NULL,
  `fw_type` enum('Branch','Trunk') DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `task_date`, `testing_type`, `fw_version_current`, `fw_version_prev`, `fw_version_rec`, `fw_type`, `due_date`, `created_at`) VALUES
(23, '2026-03-04', 'Smoke', '123.457', '123.456', '63.0.1.6', 'Trunk', '2026-03-04', '2026-03-04 03:36:47'),
(25, '2026-03-11', 'Smoke', '6.3.0.3', '6.3.0.1', '6.3.1.1', 'Trunk', '2026-03-11', '2026-03-11 02:18:27'),
(28, '2026-03-24', 'Smoke', '12.2.2.2.3', '12.2.2.2.2', '12.0.0.0.1', 'Branch', '2026-03-24', '2026-03-24 03:37:32'),
(29, '2026-03-24', 'Smoke', '123.456', '6.3.0.3', '123.457', 'Trunk', '2026-03-24', '2026-03-24 03:45:42'),
(30, '2026-03-26', 'Smoke', '123.457', '123.456', '63.0.1.6', 'Trunk', '2026-03-26', '2026-03-26 07:04:57'),
(31, '2026-03-27', 'Smoke', '6.39.0.94', '6.38.0.512', '6.30.1.6', 'Trunk', '2026-03-27', '2026-03-26 09:10:58');

-- --------------------------------------------------------

--
-- Table structure for table `task_assignments`
--

CREATE TABLE `task_assignments` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `printer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `designation` enum('Main','Support') DEFAULT 'Main',
  `regression_url` text DEFAULT NULL,
  `overall_status` enum('Pass','Fail','Pending','Blocked','N/A') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_assignments`
--

INSERT INTO `task_assignments` (`id`, `task_id`, `printer_id`, `user_id`, `designation`, `regression_url`, `overall_status`) VALUES
(86, 23, 2, 1, 'Main', NULL, 'Fail'),
(87, 23, 2, 6, 'Support', NULL, 'Fail'),
(95, 25, 3, 4, 'Main', NULL, 'Pass'),
(96, 25, 3, 6, 'Support', NULL, 'Pass'),
(105, 28, 8, 1, 'Main', NULL, 'Pending'),
(106, 29, 10, 1, 'Main', NULL, 'Pass'),
(107, 30, 3, 1, 'Main', NULL, 'Pass'),
(108, 30, 2, 1, 'Main', NULL, 'Pass'),
(109, 30, 6, 1, 'Main', NULL, 'Fail'),
(111, 31, 1, 5, 'Main', NULL, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `test_cases`
--

CREATE TABLE `test_cases` (
  `id` int(11) NOT NULL,
  `printer_model` varchar(50) DEFAULT NULL,
  `case_code` varchar(50) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_cases`
--

INSERT INTO `test_cases` (`id`, `printer_model`, `case_code`, `title`) VALUES
(1, 'Pixiu SFP', '29477606', '02.Firmware Acceptance Start'),
(2, 'Pixiu SFP', '32148428', '03. Firmware Acceptance End'),
(3, 'Pixiu SFP', '29477759', '04.Firmware Acceptance PDL robustness'),
(4, 'Pixiu SFP', '29477381', '07.Network Configuration Acceptance'),
(5, 'Pixiu SFP', '29891475', '01.OOBE Start'),
(6, 'Pixiu SFP', '29891476', '02.OOBE Finish'),
(7, 'Pixiu SFP', '29842921', '04. Driver Acceptance'),
(8, 'Beam SFP', '29477606', '02.Firmware Acceptance Start'),
(9, 'Beam SFP', '32148428', '03. Firmware Acceptance End'),
(10, 'Beam SFP', '29477759', '04.Firmware Acceptance PDL robustness'),
(11, 'Beam SFP', '29477381', '07.Network Configuration Acceptance'),
(12, 'Beam SFP', '29891475', '01.OOBE Start'),
(13, 'Beam SFP', '29891476', '02.OOBE Finish'),
(14, 'Beam SFP', '29842921', '04. Driver Acceptance'),
(15, 'Beam MFP', '29477606', '02.Firmware Acceptance Start'),
(16, 'Beam MFP', '32148428', '03.Firmware Acceptance End'),
(17, 'Beam MFP', '29477759', '04.Firmware Acceptance PDL robustness'),
(18, 'Beam MFP', '30241676', '01.MFP Basic to Play'),
(19, 'Beam MFP', '30267749', '02.MFP Basic to Play'),
(20, 'Beam MFP', '29478019', '03.MFP Basic to Play'),
(21, 'Beam MFP', '29477956', '04.MFP Basic to Play'),
(22, 'Beam MFP', '29819311', 'Print from USB storage 1'),
(23, 'Beam MFP', '48740771', 'Print from USB storage 2'),
(24, 'Pixiu MFP', '29477606', '02.Firmware Acceptance Start'),
(25, 'Pixiu MFP', '32148428', '03.Firmware Acceptance End'),
(26, 'Pixiu MFP', '29477759', '04.Firmware Acceptance PDL robustness'),
(27, 'Pixiu MFP', '30241676', '01.MFP Basic to Play'),
(28, 'Pixiu MFP', '30267749', '02.MFP Basic to Play'),
(29, 'Pixiu MFP', '29478019', '03.MFP Basic to Play'),
(30, 'Pixiu MFP', '29477956', '04.MFP Basic to Play'),
(31, 'Pixiu MFP', '29819311', 'Print from USB storage 1'),
(32, 'Pixiu MFP', '48740771', 'Print from USB storage 2'),
(33, 'Ray', '29477606', '02.Firmware Acceptance Start'),
(34, 'Ray', '32148428', '03.Firmware Acceptance End'),
(35, 'Ray', '29477759', '04.Firmware Acceptance PDL robustness'),
(36, 'Ray', '29819311', 'Print from USB storage 1'),
(37, 'Ray', '48740771', 'Print from USB storage 2'),
(38, 'Ray', '29477381', '07.Network Configuration Acceptance'),
(39, 'Ray', '29477371', 'Power Management 1'),
(40, 'Ray', '29891475', '01.OOBE Start'),
(41, 'Ray', '29891476', '02.OOBE Finish'),
(42, 'Flare', '30481628', '01.Firmware Acceptance FDU'),
(43, 'Flare', '32148428', '03. Firmware Acceptance End'),
(44, 'Flare', '29477759', '04.Firmware Acceptance PDL robustness'),
(45, 'Flare', '29477381', '07.Network Configuration Acceptance'),
(46, 'Flare', '29891475', '01.OOBE Start'),
(47, 'Flare', '29891476', '02.OOBE Finish'),
(48, 'Flare', '29477372', 'Power Management 2'),
(49, 'Flare', '29842921', 'Driver Acceptance'),
(51, 'Open Spark', '123', 'USB Management'),
(52, 'Open Spark 2', '123', 'USB Management');

-- --------------------------------------------------------

--
-- Table structure for table `test_results`
--

CREATE TABLE `test_results` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `printer_id` int(11) NOT NULL,
  `test_case_id` int(11) NOT NULL,
  `status` enum('Pass','Fail','Pending','N/A','Blocked') DEFAULT 'Pending',
  `jira_url` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `assigned_to` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_results`
--

INSERT INTO `test_results` (`id`, `task_id`, `printer_id`, `test_case_id`, `status`, `jira_url`, `updated_by`, `updated_at`, `assigned_to`) VALUES
(114, 23, 2, 12, 'Blocked', '', 1, '2026-03-04 03:58:01', 1),
(115, 23, 2, 13, 'N/A', '', 1, '2026-03-04 03:58:03', 1),
(116, 23, 2, 11, 'Pass', 'jh', 1, '2026-03-24 02:32:19', 1),
(117, 23, 2, 14, 'Pass', '', 1, '2026-03-24 02:34:28', 1),
(118, 23, 2, 8, 'Pass', '', 2, '2026-03-04 03:39:57', 2),
(119, 23, 2, 9, 'N/A', '', 6, '2026-03-04 03:58:23', 6),
(120, 23, 2, 10, 'Blocked', '', 6, '2026-03-25 02:14:55', 6),
(133, 25, 3, 15, 'Pass', '', 6, '2026-03-11 02:46:35', 6),
(134, 25, 3, 17, 'Pass', '', 6, '2026-03-11 02:44:50', 6),
(135, 25, 3, 22, 'Pass', '', 6, '2026-03-11 02:19:58', 6),
(136, 25, 3, 18, 'Pass', '', 6, '2026-03-11 02:19:59', 6),
(137, 25, 3, 21, 'Pass', '', 4, '2026-03-11 02:20:01', 4),
(138, 25, 3, 20, 'Pass', '', 4, '2026-03-11 02:19:14', 4),
(139, 25, 3, 19, 'Pass', '', 6, '2026-03-11 02:44:58', 6),
(140, 25, 3, 23, 'Pass', '', 6, '2026-03-11 02:45:00', 6),
(141, 25, 3, 16, 'Pass', '', 6, '2026-03-11 02:44:58', 6),
(150, 29, 10, 52, 'Blocked', '', 1, '2026-03-25 02:14:10', 1),
(151, 28, 8, 51, 'Fail', 'https://asd, https://qwe', 1, '2026-03-26 08:58:16', 1),
(152, 30, 3, 15, 'Fail', 'https://123, https://234', 1, '2026-03-26 07:08:12', 1),
(153, 30, 3, 17, 'Pass', '', 1, '2026-03-26 07:06:12', 1),
(154, 30, 3, 21, 'Blocked', '', 1, '2026-03-26 07:08:14', 1),
(155, 30, 3, 20, 'Blocked', '', 1, '2026-03-26 07:08:16', 1),
(156, 30, 3, 22, 'N/A', '', 1, '2026-03-26 07:08:17', 1),
(157, 30, 3, 18, 'N/A', '', 1, '2026-03-26 07:08:19', 1),
(158, 30, 3, 19, 'N/A', '', 1, '2026-03-26 07:08:21', 1),
(159, 30, 3, 16, 'Pass', '', 1, '2026-03-26 07:06:37', 1),
(160, 30, 3, 23, 'Pass', '', 1, '2026-03-26 07:06:36', 1),
(161, 30, 2, 11, 'Pass', '', 1, '2026-03-26 07:07:02', 1),
(162, 30, 2, 8, 'Pass', '', 1, '2026-03-26 07:07:03', 1),
(163, 30, 2, 10, 'Pass', '', 1, '2026-03-26 07:07:05', 1),
(164, 30, 2, 14, 'Pass', '', 1, '2026-03-26 07:07:10', 1),
(165, 30, 2, 12, 'Pass', '', 1, '2026-03-26 07:07:09', 1),
(166, 30, 2, 13, 'Pass', '', 1, '2026-03-26 07:07:07', 1),
(167, 30, 2, 9, 'Pass', '', 1, '2026-03-26 07:07:06', 1),
(168, 30, 6, 48, 'Pass', '', 1, '2026-03-26 07:07:39', 1),
(169, 30, 6, 45, 'Pass', '', 1, '2026-03-26 07:07:37', 1),
(170, 30, 6, 44, 'Pass', '', 1, '2026-03-26 07:07:36', 1),
(171, 30, 6, 49, 'Pass', '', 1, '2026-03-26 07:07:35', 1),
(172, 30, 6, 46, 'Pass', '', 1, '2026-03-26 07:07:33', 1),
(173, 30, 6, 47, 'Pass', '', 1, '2026-03-26 07:07:32', 1),
(174, 30, 6, 42, 'Pass', '', 1, '2026-03-26 07:07:31', 1),
(175, 30, 6, 43, 'Pass', '', 1, '2026-03-26 07:07:30', 1),
(176, 31, 1, 39, 'Pass', 'https://xcsdcsD', 5, '2026-03-26 09:13:26', 5),
(177, 31, 1, 33, 'Pass', '', 5, '2026-03-26 09:14:16', 5),
(178, 31, 1, 34, 'Pass', '', 5, '2026-03-26 09:14:06', 5),
(179, 31, 1, 35, 'Pass', '', 5, '2026-03-26 09:13:59', 5);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('lead','tester','admin') NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `pfp_path` varchar(255) DEFAULT NULL,
  `status` enum('active','blocked') DEFAULT 'active',
  `remember_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `email`, `password`, `role`, `last_login`, `pfp_path`, `status`, `remember_token`) VALUES
(1, 'Chan Jian Feng', 'jf', 'jfchan2015@gmail.com', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', '2026-03-31 14:25:07', 'imgs/profile_pics/user_1_1771571234.png', 'active', NULL),
(2, 'Kali', 'kali', '', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'lead', '2026-03-26 17:19:33', 'imgs/profile_pics/user_2_1773195924.png', 'active', NULL),
(3, 'joon', 'joon', '', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', '2026-03-11 10:36:28', 'imgs/profile_pics/user_3_1773196620.png', 'active', NULL),
(4, 'jonathan', 'jon', '', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', '2026-03-11 14:08:33', 'imgs/profile_pics/user_4_1773195706.png', 'active', NULL),
(5, 'Alya', 'alya', '', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', '2026-03-26 17:20:28', 'imgs/profile_pics/user_5_1773194790.png', 'active', NULL),
(6, 'matt', 'matt', '', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', '2026-03-25 10:16:09', 'imgs/profile_pics/user_6_1773195922.png', 'active', NULL),
(7, 'chingsheng', 'cs', '', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', '2026-03-11 10:37:59', 'imgs/profile_pics/user_7_1773196269.png', 'active', NULL),
(8, 'Adila', 'adila', '', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', '2026-03-11 10:47:28', 'imgs/profile_pics/user_8_1773194765.png', 'active', NULL),
(9, 'admin', 'admin', '', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'admin', '2026-03-16 16:32:34', NULL, 'active', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `token` (`token`);

--
-- Indexes for table `printers`
--
ALTER TABLE `printers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `model_name` (`model_name`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `task_assignments`
--
ALTER TABLE `task_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `printer_id` (`printer_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `test_cases`
--
ALTER TABLE `test_cases`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `test_results`
--
ALTER TABLE `test_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `fk_tr_assignee` (`assigned_to`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `printers`
--
ALTER TABLE `printers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `task_assignments`
--
ALTER TABLE `task_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `test_cases`
--
ALTER TABLE `test_cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `test_results`
--
ALTER TABLE `test_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=180;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `task_assignments`
--
ALTER TABLE `task_assignments`
  ADD CONSTRAINT `task_assignments_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_assignments_ibfk_2` FOREIGN KEY (`printer_id`) REFERENCES `printers` (`id`),
  ADD CONSTRAINT `task_assignments_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `test_results`
--
ALTER TABLE `test_results`
  ADD CONSTRAINT `fk_tr_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `test_results_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_results_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
