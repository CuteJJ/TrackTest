-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 12, 2026 at 10:24 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

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
-- Table structure for table `printers`
--

CREATE TABLE `printers` (
  `id` int(11) NOT NULL,
  `model_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `printers`
--

INSERT INTO `printers` (`id`, `model_name`) VALUES
(3, 'Beam MFP'),
(2, 'Beam SFP'),
(6, 'Flare'),
(7, 'Open Spark'),
(5, 'Pixiu MFP'),
(4, 'Pixiu SFP'),
(1, 'Ray');

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
(1, '2026-02-12', 'Smoke', '123.457', '123.456', '123.001', 'Trunk', '2026-02-12', '2026-02-12 08:38:20'),
(2, '2026-02-12', 'Regression', '23.457', '23.456', '23.001', 'Branch', '2026-02-12', '2026-02-12 08:52:24');

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
  `regression_url` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_assignments`
--

INSERT INTO `task_assignments` (`id`, `task_id`, `printer_id`, `user_id`, `designation`, `regression_url`) VALUES
(1, 1, 3, 1, 'Main', NULL),
(2, 1, 3, 2, 'Support', NULL),
(3, 1, 2, 2, 'Main', NULL),
(4, 1, 2, 1, 'Support', NULL),
(5, 1, 6, 1, 'Main', NULL),
(6, 2, 3, 2, 'Main', 'http://localhost/tracktest/create_task1.php'),
(7, 2, 2, 2, 'Main', 'http://localhost/tracktest/create_task2.php'),
(8, 2, 6, 2, 'Main', 'http://localhost/tracktest/create_task3.php');

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
(49, 'Flare', '29842921', 'Driver Acceptance');

-- --------------------------------------------------------

--
-- Table structure for table `test_results`
--

CREATE TABLE `test_results` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `printer_id` int(11) NOT NULL,
  `test_case_id` int(11) NOT NULL,
  `status` enum('Pass','Fail','Pending') DEFAULT 'Pending',
  `jira_url` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_results`
--

INSERT INTO `test_results` (`id`, `task_id`, `printer_id`, `test_case_id`, `status`, `jira_url`, `updated_by`, `updated_at`) VALUES
(1, 1, 3, 15, 'Fail', '', 2, '2026-02-12 09:12:55'),
(2, 1, 3, 17, 'Pass', '', 2, '2026-02-12 09:06:22'),
(3, 1, 3, 21, 'Pass', '', 2, '2026-02-12 09:06:26'),
(4, 1, 3, 20, 'Pass', '', 2, '2026-02-12 09:06:27'),
(5, 1, 3, 22, 'Pass', '', 2, '2026-02-12 09:06:28'),
(6, 1, 3, 18, 'Pass', '', 2, '2026-02-12 09:06:29'),
(7, 1, 3, 19, 'Pass', '', 2, '2026-02-12 09:06:30'),
(8, 1, 3, 16, 'Pass', '', 2, '2026-02-12 09:06:30'),
(9, 1, 3, 23, 'Pass', '', 2, '2026-02-12 09:06:31'),
(10, 1, 2, 8, 'Fail', '', 2, '2026-02-12 09:13:20'),
(11, 1, 2, 11, 'Fail', '', 2, '2026-02-12 09:13:21'),
(12, 1, 2, 10, 'Fail', '', 2, '2026-02-12 09:13:23'),
(13, 1, 2, 14, 'Pass', '', 2, '2026-02-12 09:13:24'),
(14, 1, 2, 12, 'Pass', '', 2, '2026-02-12 09:13:25'),
(15, 1, 2, 13, 'Pass', '', 2, '2026-02-12 09:13:26'),
(16, 1, 2, 9, 'Pass', '', 2, '2026-02-12 09:13:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('lead','tester') NOT NULL,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password`, `role`, `last_login`) VALUES
(1, 'Chan Jian Feng', 'jfchan', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'tester', NULL),
(2, 'Kali', 'Kali', '$2y$10$PuDwLb3pVVvMpi3Tk.he/uj4.IkTVYvrAdFrs.IR309NTzZvxGsH.', 'lead', NULL),
(3, 'Alice', 'Alice', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tester', NULL),
(4, 'Dante', 'Dante', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tester', NULL);

--
-- Indexes for dumped tables
--

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
  ADD KEY `updated_by` (`updated_by`);

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
-- AUTO_INCREMENT for table `printers`
--
ALTER TABLE `printers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `task_assignments`
--
ALTER TABLE `task_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `test_cases`
--
ALTER TABLE `test_cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `test_results`
--
ALTER TABLE `test_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  ADD CONSTRAINT `test_results_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_results_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
