-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 11, 2025 at 09:10 PM
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
-- Database: `face_attendance_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `status` enum('present','absent','late') DEFAULT 'present',
  `confidence_score` decimal(5,4) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `date`, `check_in_time`, `check_out_time`, `status`, `confidence_score`, `ip_address`, `created_at`, `updated_at`, `time_in`, `time_out`, `image_path`, `notes`) VALUES
(1, 2, '2025-06-11', NULL, NULL, 'present', NULL, NULL, '2025-06-11 18:14:31', '2025-06-11 18:19:30', '23:44:31', '20:19:30', 'uploads/attendance/attendance_2_2025-06-11_20-14-31.jpg', NULL),
(2, 2, '2025-06-12', NULL, NULL, 'present', NULL, NULL, '2025-06-11 18:30:43', '2025-06-11 18:30:43', '00:00:43', NULL, 'uploads/attendance/attendance_2_2025-06-11_20-30-43.jpg', NULL),
(3, 1, '2025-06-12', NULL, NULL, 'present', NULL, NULL, '2025-06-11 18:42:18', '2025-06-11 18:42:18', '00:12:18', NULL, 'uploads/attendance/attendance_1_2025-06-11_20-42-18.jpg', NULL),
(4, 3, '2025-06-12', NULL, NULL, 'present', NULL, NULL, '2025-06-11 18:50:54', '2025-06-11 18:50:54', '00:20:54', NULL, 'uploads/attendance/attendance_3_2025-06-11_20-50-54.jpg', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('check_in','check_out','failed_attempt') NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `confidence_score` decimal(5,4) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `attendance_summary`
-- (See below for the actual view)
--
CREATE TABLE `attendance_summary` (
`id` int(11)
,`name` varchar(100)
,`employee_id` varchar(20)
,`total_days` bigint(21)
,`present_days` decimal(22,0)
,`absent_days` decimal(22,0)
,`late_days` decimal(22,0)
,`attendance_percentage` decimal(28,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_attendance`
-- (See below for the actual view)
--
CREATE TABLE `daily_attendance` (
`id` int(11)
,`employee_id` varchar(20)
,`name` varchar(100)
,`email` varchar(100)
,`date` date
,`check_in_time` time
,`check_out_time` time
,`status` enum('present','absent','late')
,`confidence_score` decimal(5,4)
);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'attendance_start_time', '09:00:00', 'Daily attendance start time', '2025-06-11 17:43:15'),
(2, 'attendance_end_time', '18:00:00', 'Daily attendance end time', '2025-06-11 17:43:15'),
(3, 'late_threshold', '09:15:00', 'Time after which attendance is marked as late', '2025-06-11 17:43:15'),
(4, 'face_confidence_threshold', '0.6', 'Minimum confidence score for face recognition', '2025-06-11 17:43:15'),
(5, 'max_attempts_per_day', '3', 'Maximum failed recognition attempts per day', '2025-06-11 17:43:15');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `face_descriptor` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_id`, `name`, `email`, `password`, `role`, `face_descriptor`, `profile_image`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ADMIN001', 'System Administrator', 'admin@company.com', '$2a$12$gI4iiiN/2opHk7uAoN8zretOfdfFPuaKEPMEQ6Ih34rFMpp0jzu0m', 'admin', NULL, NULL, 'active', '2025-06-11 17:43:15', '2025-06-11 18:41:27'),
(2, 'ADMIN002', 'rviya rshmika', 'm@gmail.com', '$2y$10$1HC17VAyYlV2CrUvbI0aLO8FQlJpHrXKXwhRPVGo2ZkNxfN6Yz/Ge', 'user', NULL, NULL, 'inactive', '2025-06-11 17:45:06', '2025-06-11 18:42:43'),
(3, '564646', 'ravindu', 'rashmikadinal975@gmail.com', '$2y$10$2NB1QCXnWqXF2IrGAlPfFuivwIIXamRfuBvA7ZgSKellHx5n0ML7m', 'user', NULL, NULL, 'active', '2025-06-11 18:50:19', '2025-06-11 18:50:19');

-- --------------------------------------------------------

--
-- Structure for view `attendance_summary`
--
DROP TABLE IF EXISTS `attendance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `attendance_summary`  AS SELECT `u`.`id` AS `id`, `u`.`name` AS `name`, `u`.`employee_id` AS `employee_id`, count(`a`.`id`) AS `total_days`, sum(case when `a`.`status` = 'present' then 1 else 0 end) AS `present_days`, sum(case when `a`.`status` = 'absent' then 1 else 0 end) AS `absent_days`, sum(case when `a`.`status` = 'late' then 1 else 0 end) AS `late_days`, round(sum(case when `a`.`status` = 'present' then 1 else 0 end) / count(`a`.`id`) * 100,2) AS `attendance_percentage` FROM (`users` `u` left join `attendance` `a` on(`u`.`id` = `a`.`user_id`)) WHERE `u`.`status` = 'active' GROUP BY `u`.`id`, `u`.`name`, `u`.`employee_id` ;

-- --------------------------------------------------------

--
-- Structure for view `daily_attendance`
--
DROP TABLE IF EXISTS `daily_attendance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_attendance`  AS SELECT `u`.`id` AS `id`, `u`.`employee_id` AS `employee_id`, `u`.`name` AS `name`, `u`.`email` AS `email`, `a`.`date` AS `date`, `a`.`check_in_time` AS `check_in_time`, `a`.`check_out_time` AS `check_out_time`, `a`.`status` AS `status`, `a`.`confidence_score` AS `confidence_score` FROM (`users` `u` left join `attendance` `a` on(`u`.`id` = `a`.`user_id`)) WHERE `u`.`status` = 'active' ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`date`),
  ADD KEY `idx_attendance_date` (`date`),
  ADD KEY `idx_attendance_user_date` (`user_id`,`date`);

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_logs_timestamp` (`timestamp`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_employee_id` (`employee_id`),
  ADD KEY `idx_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
