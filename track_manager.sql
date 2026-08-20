-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 17, 2026 at 11:54 PM
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

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `created_at`) VALUES
(3, 'alya.haqif@gmail.com', '3837dfdee7aa5e9c081a87a08a758eb48ad6aa3d98b3fb55179b247f9cac7ad3', '2026-07-25 10:41:48', '2026-07-25 07:41:48');

-- --------------------------------------------------------

--
-- Table structure for table `printers`
--

CREATE TABLE `printers` (
  `id` int(11) NOT NULL,
  `model_name` varchar(50) NOT NULL,
  `printer_path` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `printers`
--

INSERT INTO `printers` (`id`, `model_name`, `printer_path`, `status`) VALUES
(1, 'Ray', NULL, 'active'),
(2, 'Beam SFP', NULL, 'active'),
(3, 'Beam MFP', NULL, 'active'),
(4, 'Pixiu SFP', 'print', 'active'),
(5, 'Pixiu MFP', NULL, 'active'),
(6, 'Flare', NULL, 'active'),
(8, 'Open Spark', NULL, 'inactive'),
(10, 'Spark', NULL, 'active'),
(24, 'LOLA', NULL, 'active');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('In Progress','Completed') DEFAULT 'In Progress'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `task_date`, `testing_type`, `fw_version_current`, `fw_version_prev`, `fw_version_rec`, `fw_type`, `due_date`, `created_at`, `status`) VALUES
(57, '2026-07-28', 'Smoke', '6.30.1.6', '6.30.1.6', '6.30.1.6', 'Trunk', '2026-07-28', '2026-07-28 20:41:39', 'In Progress'),
(63, '2026-07-29', 'Regression', '6.30.1.6', '6.30.1.6', '6.30.1.6', 'Trunk', '2026-07-29', '2026-07-29 20:12:53', 'In Progress'),
(72, '2026-07-28', 'Smoke', NULL, '6.30.1.6', '6.30.1.6', 'Trunk', '2026-07-28', '2026-07-30 03:22:51', 'In Progress'),
(73, '2026-07-27', 'Regression', '6.30.1.6', '6.30.1.6', '6.30.1.6', 'Trunk', '2026-07-30', '2026-07-30 03:58:43', 'In Progress'),
(74, '2026-07-31', 'Smoke', NULL, '6.30.1.5', '6.30.1.6', 'Trunk', '2026-07-31', '2026-07-30 16:15:36', 'In Progress'),
(75, '2026-07-30', 'Smoke', '6.30.1.6', NULL, NULL, 'Trunk', '2026-07-30', '2026-07-30 16:56:40', 'In Progress'),
(77, '2026-07-30', 'Regression', '6.30.1.5', '6.30.1.6', '6.30.1.8', 'Trunk', '2026-07-30', '2026-07-30 18:15:50', 'In Progress'),
(79, '2026-07-30', 'Regression', '6.30.1.10', '6.30.1.6', NULL, 'Trunk', '2026-07-30', '2026-07-30 20:55:58', 'In Progress'),
(80, '2026-07-29', 'Smoke', '6.30.1.5', '6.30.1.7', '6.30.1.10', 'Trunk', '2026-07-29', '2026-07-30 21:31:47', 'In Progress'),
(82, '2026-07-27', 'Smoke', '6.27.1.6', '6.27.1.5', '6.27.1.0', 'Branch', '2026-07-27', '2026-07-30 22:07:07', 'In Progress'),
(83, '2026-07-31', 'Regression', '6.30.1.7', '6.30.1.7', '6.30.1.8', 'Trunk', '2026-07-31', '2026-07-30 23:01:09', 'In Progress'),
(84, '2026-08-07', 'Smoke', '6.30.1.8', '6.30.1.10', '6.30.1.8', 'Trunk', '2026-08-07', '2026-08-07 11:36:48', 'In Progress');

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
  `overall_status` enum('Pass','Fail','Pending','Blocked','N/A','Completed') DEFAULT 'Pending',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_assignments`
--

INSERT INTO `task_assignments` (`id`, `task_id`, `printer_id`, `user_id`, `designation`, `regression_url`, `overall_status`, `updated_at`) VALUES
(240, 77, 3, 2, 'Main', 'https://hp-testrail.external.hp.com/index.php?/plans/view/1186748', 'Completed', '2026-08-04 21:59:42'),
(241, 77, 2, 2, 'Main', 'https://hp-testrail.external.hp.com/index.php?/plans/view/1186748', 'Completed', '2026-08-04 21:59:42'),
(242, 77, 5, 2, 'Main', 'https://hp-testrail.external.hp.com/index.php?/plans/view/1186749', 'Completed', '2026-08-04 21:59:42'),
(249, 75, 4, 8, 'Main', NULL, 'Pending', '2026-08-04 21:59:42'),
(253, 79, 10, 2, 'Main', 'https://hp-testrail.external.hp.com/index.php?/plans/view/1186748', 'Completed', '2026-08-04 21:59:42'),
(254, 80, 6, 5, 'Main', NULL, 'Pass', '2026-08-04 21:59:42'),
(260, 82, 3, 8, 'Main', NULL, 'Pending', '2026-08-04 21:59:42'),
(262, 82, 1, 5, 'Support', NULL, 'Pending', '2026-08-04 21:59:42'),
(263, 82, 1, 4, 'Main', NULL, 'Pending', '2026-08-04 21:59:42'),
(264, 80, 6, 6, 'Support', NULL, 'Pass', '2026-08-04 21:59:42'),
(266, 83, 3, 2, 'Main', 'https://hp-testrail.external.hp.com/index.php?/plans/view/1186748', 'Pending', '2026-08-04 21:59:42'),
(267, 84, 1, 8, 'Main', NULL, 'Pending', '2026-08-08 18:00:41'),
(268, 84, 1, 5, 'Support', NULL, 'Pending', '2026-08-08 18:00:41'),
(269, 82, 10, 5, 'Main', NULL, 'Pending', '2026-08-08 16:59:45');

-- --------------------------------------------------------

--
-- Table structure for table `test_cases`
--

CREATE TABLE `test_cases` (
  `id` int(11) NOT NULL,
  `printer_model` varchar(255) DEFAULT NULL,
  `case_code` varchar(50) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_cases`
--

INSERT INTO `test_cases` (`id`, `printer_model`, `case_code`, `title`) VALUES
(3, 'Pixiu SFP', '29477759', '04.Firmware Acceptance PDL robustness'),
(4, 'Pixiu SFP', '29477381', '07.Network Configuration Acceptance'),
(5, 'Pixiu SFP', '29891475', '01.OOBE Start'),
(6, 'Pixiu SFP', '29891476', '02.OOBE Finish'),
(8, 'Beam SFP', '29477606', '02.Firmware Acceptance Start'),
(10, 'Beam SFP', '29477759', '04.Firmware Acceptance PDL robustness'),
(11, 'Beam SFP', '29477381', '07.Network Configuration Acceptance'),
(12, 'Beam SFP', '29891475', '01.OOBE Start'),
(13, 'Beam SFP', '29891476', '02.OOBE Finish'),
(15, 'Beam MFP', '29477606', '02.Firmware Acceptance Start'),
(17, 'Beam MFP', '29477759', '04.Firmware Acceptance PDL robustness'),
(19, 'Beam MFP', '30267749', '02.MFP Basic to Play'),
(20, 'Beam MFP', '29478019', '03.MFP Basic to Play'),
(21, 'Beam MFP', '29477956', '04.MFP Basic to Play'),
(24, 'Pixiu MFP', '29477606', '02.Firmware Acceptance Start'),
(26, 'Pixiu MFP', '29477759', '04.Firmware Acceptance PDL robustness'),
(27, 'Pixiu MFP', '30241676', '01.MFP Basic to Play'),
(28, 'Pixiu MFP', '30267749', '02.MFP Basic to Play'),
(29, 'Pixiu MFP', '29478019', '03.MFP Basic to Play'),
(30, 'Pixiu MFP', '29477956', '04.MFP Basic to Play'),
(31, 'Pixiu MFP', '29819311', 'Print from USB storage 1'),
(32, 'Pixiu MFP', '48740771', 'Print from USB storage 2'),
(33, 'Ray', '29477606', '02.Firmware Acceptance Start'),
(35, 'Ray', '29477759', '04.Firmware Acceptance PDL robustness'),
(36, 'Ray', '29819311', 'Print from USB storage 1'),
(37, 'Ray', '48740771', 'Print from USB storage 2'),
(38, 'Ray', '29477381', '07.Network Configuration Acceptance'),
(39, 'Ray', 'TC29477371', 'Power Management 1'),
(40, 'Ray', '29891475', '01.OOBE Start'),
(41, 'Ray', '29891476', '02.OOBE Finish'),
(45, 'Flare', '29477381', '07.Network Configuration Acceptance'),
(46, 'Flare', '29891475', '01.OOBE Start'),
(48, 'Flare', '29477372', 'Power Management 2'),
(107, 'LOLA', 'TC29477371', 'Power Management 1'),
(108, 'LOLA', '29477372', 'Power Management 2'),
(109, NULL, 'TC29477371', 'Power Management 1'),
(111, 'Spark', '29477606', '02.Firmware Acceptance Start'),
(112, 'Spark', 'TC29477371', 'Power Management 1');

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
(310, 75, 1, 39, 'Pass', '', 5, '2026-07-30 18:10:54', 5),
(311, 75, 1, 33, 'Blocked', 'Connect to the Internet not supported', 5, '2026-07-30 17:41:35', 5),
(312, 75, 1, 36, 'Pass', '', 5, '2026-07-30 18:10:54', 5),
(313, 75, 1, 41, 'Fail', 'https://hp-jira.external.hp.com/browse/FIRM-32979', 5, '2026-07-30 17:35:12', 5),
(314, 75, 1, 35, 'Pass', '', 5, '2026-07-30 18:10:54', 5),
(315, 75, 1, 38, 'Pass', '', 5, '2026-07-30 18:10:54', 5),
(316, 75, 1, 34, 'Pass', '', 5, '2026-07-30 18:10:54', 5),
(317, 75, 1, 37, 'Pass', '', 5, '2026-07-30 18:10:54', 5),
(318, 75, 1, 40, 'Pass', '', 5, '2026-07-30 18:10:54', 5),
(319, 82, 1, 41, 'Pending', NULL, 5, '2026-08-07 15:36:05', NULL),
(320, 82, 1, 40, 'Pending', NULL, 5, '2026-08-07 15:36:05', NULL),
(321, 82, 1, 39, 'Pending', NULL, NULL, '2026-08-07 15:36:05', NULL),
(322, 82, 1, 34, '', NULL, NULL, '2026-07-30 23:23:02', 5),
(323, 82, 1, 33, '', NULL, NULL, '2026-07-30 23:28:32', 4),
(324, 82, 1, 35, '', NULL, NULL, '2026-07-30 23:28:32', 4),
(325, 82, 1, 36, '', NULL, NULL, '2026-07-30 23:28:32', 4),
(326, 82, 1, 38, '', NULL, NULL, '2026-07-30 23:28:32', 4),
(327, 82, 1, 37, '', NULL, NULL, '2026-07-30 23:28:32', 4),
(328, 82, 3, 15, 'Pass', '', 8, '2026-07-30 23:49:39', 8),
(329, 82, 3, 21, 'Pass', '', 8, '2026-07-30 23:49:39', 8),
(330, 82, 3, 20, 'Pass', '', 8, '2026-07-30 23:49:39', 8),
(331, 82, 3, 19, 'Pass', '', 8, '2026-07-30 23:49:39', 8),
(332, 82, 3, 17, 'Pass', '', 8, '2026-07-30 23:49:39', 8),
(333, 80, 6, 48, 'Pass', 'https://hp-jira.external.hp.com/browse/FIRM-32979', 5, '2026-08-04 21:13:55', 5),
(334, 80, 6, 45, 'Pass', '', 5, '2026-07-30 23:52:53', 5),
(335, 80, 6, 49, 'Pass', '', 5, '2026-07-30 23:52:53', 5),
(336, 80, 6, 46, 'Fail', 'https://hp-jira.external.hp.com/browse/FIRM-27170', 5, '2026-08-04 21:16:23', 5),
(337, 80, 6, 43, 'Pass', '', 5, '2026-07-30 23:52:53', 5),
(338, 84, 1, 33, 'Pass', 'https://hp-jira.external.hp.com/issues/?jql=id%20in%20(%20FIRM-24965%2CFIRM-23999)', 5, '2026-08-08 18:01:17', 5),
(339, 84, 1, 38, 'Pass', '', 5, '2026-08-07 16:36:22', 5),
(340, 84, 1, 35, 'Fail', 'https://hp-jira.external.hp.com/issues/?jql=id%20in%20(%20FIRM-24965%2CFIRM-23999)', 5, '2026-08-08 18:01:21', 5),
(341, 84, 1, 41, 'Pass', '', 5, '2026-08-07 16:36:22', 5),
(342, 84, 1, 36, 'Pass', '', 5, '2026-08-07 16:36:22', 5),
(343, 84, 1, 40, 'Pass', '', 5, '2026-08-07 16:36:22', 5),
(344, 84, 1, 37, 'Pass', '', 5, '2026-08-07 16:36:22', 5),
(345, 84, 1, 39, 'Pass', '', 5, '2026-08-07 16:36:22', 5),
(346, 82, 10, 112, 'Pending', NULL, NULL, '2026-08-08 17:01:19', NULL),
(347, 82, 10, 111, 'Pending', NULL, NULL, '2026-08-08 17:01:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `staff_id` varchar(50) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('lead','tester','admin') NOT NULL,
  `joined_date` date DEFAULT NULL,
  `security_question` varchar(255) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `pfp_path` varchar(255) DEFAULT NULL,
  `status` enum('active','blocked') DEFAULT 'active',
  `remember_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `staff_id`, `full_name`, `username`, `email`, `password`, `role`, `joined_date`, `security_question`, `security_answer`, `last_login`, `pfp_path`, `status`, `remember_token`) VALUES
(1, NULL, 'Chan Jian Feng', 'jf', 'jfchan2015@gmail.com', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', NULL, NULL, NULL, '2026-06-23 16:08:13', 'imgs/profile_pics/user_1_1771571234.png', 'active', NULL),
(2, 'LFP100', 'Kalidasen Krishnan', 'kali', '', '$2y$10$MjFTqEtLp3jyCk13MKzWBuUMpCyQRkNpgjeC0kDDe9alttecKdbwW', 'lead', '2026-07-25', NULL, NULL, '2026-08-09 02:31:33', NULL, 'active', NULL),
(3, NULL, 'joon', 'joon', '', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', NULL, NULL, NULL, '2026-07-31 07:27:03', 'imgs/profile_pics/user_3_1773196620.png', 'active', NULL),
(4, NULL, 'jonathan', 'jon', '', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', NULL, NULL, NULL, '2026-07-31 07:28:21', 'imgs/profile_pics/user_4_1773195706.png', 'active', NULL),
(5, NULL, 'Alya', 'alya', 'alya.haqif@gmail.com', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', NULL, NULL, NULL, '2026-08-09 02:24:58', 'imgs/profile_pics/user_5_1784968600.png', 'active', NULL),
(6, NULL, 'matt', 'matt', 'matthew.ng@beyondsoft.com', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', NULL, NULL, NULL, '2026-07-15 10:13:40', 'imgs/profile_pics/user_6_1773195922.png', 'active', NULL),
(7, NULL, 'chingsheng', 'cs', '', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', NULL, NULL, NULL, '2026-03-11 10:37:59', NULL, 'active', NULL),
(8, '157125', 'Shafiqah Alya Binti Khedzer', 'adila', 'shafiqah.alya@beyondsoft.com', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', '2025-01-06', NULL, NULL, '2026-07-31 07:49:30', 'imgs/profile_pics/user_8_1773194765.png', 'active', NULL),
(9, NULL, 'admin', 'admin', '', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'admin', NULL, NULL, NULL, '2026-08-09 02:37:12', NULL, 'active', NULL),
(25, '123fqwfwaw', 'ZUL ARIFFIN', 'ZUL', '', '$2y$10$uKnfZXTUr7/izLiQj81e9.9X4cEff5YCl/TLkFNA3cFveooxd03VW', 'tester', '2026-08-10', NULL, NULL, NULL, NULL, 'active', NULL);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_test_cases_printer` (`printer_model`);

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
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `staff_id` (`staff_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `printers`
--
ALTER TABLE `printers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `task_assignments`
--
ALTER TABLE `task_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=270;

--
-- AUTO_INCREMENT for table `test_cases`
--
ALTER TABLE `test_cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;

--
-- AUTO_INCREMENT for table `test_results`
--
ALTER TABLE `test_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=348;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

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
-- Constraints for table `test_cases`
--
ALTER TABLE `test_cases`
  ADD CONSTRAINT `fk_test_cases_printer` FOREIGN KEY (`printer_model`) REFERENCES `printers` (`model_name`) ON DELETE CASCADE ON UPDATE CASCADE;

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
