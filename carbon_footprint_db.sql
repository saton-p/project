-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 05, 2026 at 08:59 AM
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
-- Database: `carbon_footprint_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `activity_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `factor_id` int(11) DEFAULT NULL,
  `quantity` decimal(15,2) NOT NULL COMMENT 'ปริมาณที่ใช้ เช่น kWh, ลิตร, kg',
  `period_month` tinyint(4) NOT NULL COMMENT 'เดือนที่เกิดกิจกรรม 1-12',
  `period_year` year(4) NOT NULL COMMENT 'ปีที่เกิดกิจกรรม',
  `recorded_at` datetime DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL,
  `carbon_value` decimal(15,4) DEFAULT NULL COMMENT 'ผลลัพธ์ kgCO2e'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role_id` int(11) NOT NULL DEFAULT 2
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `password`, `full_name`, `email`, `created_at`, `role_id`) VALUES
(1, 'admin', '$2y$10$RZaQhI3X95fyfbLeB97VyO82l1CYtQe5kCcOOUgZuHbBjlfLOMJNm', 'admin', 'admin@gmail.com', '2026-01-20 18:02:34', 2);

-- --------------------------------------------------------

--
-- Table structure for table `carbon_logs`
--

CREATE TABLE `carbon_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `factor_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL COMMENT 'จำนวนที่กรอก',
  `emission_result` decimal(10,4) NOT NULL COMMENT 'ผลลัพธ์การคำนวณ',
  `log_date` date NOT NULL,
  `log_type` varchar(20) DEFAULT 'daily',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `carbon_logs`
--

INSERT INTO `carbon_logs` (`id`, `user_id`, `factor_id`, `amount`, `emission_result`, `log_date`, `log_type`, `created_at`) VALUES
(63, 2, 2, 1221.00, 2673.8679, '2026-01-24', 'daily', '2026-01-24 08:58:31'),
(64, 2, 3, 1221.00, 3306.8343, '2026-01-24', 'daily', '2026-01-24 08:58:31'),
(65, 2, 2, 231.00, 505.8669, '2026-01-01', 'daily', '2026-01-24 09:03:05'),
(66, 2, 3, 321.00, 869.3643, '2026-01-01', 'daily', '2026-01-24 09:03:05'),
(67, 2, 2, 2332.00, 5106.8468, '2026-01-24', 'daily', '2026-01-24 15:07:33'),
(68, 2, 3, 232.00, 628.3256, '2026-01-24', 'daily', '2026-01-24 15:07:33'),
(69, 2, 2, 23.00, 50.3677, '2026-01-24', 'daily', '2026-01-24 15:08:44'),
(70, 2, 3, 23.00, 62.2909, '2026-01-24', 'daily', '2026-01-24 15:08:44'),
(71, 2, 2, 23.00, 50.3677, '2026-01-01', 'daily', '2026-01-24 15:09:02'),
(72, 2, 3, 23.00, 62.2909, '2026-01-01', 'daily', '2026-01-24 15:09:02'),
(73, 2, 2, 233.00, 510.2467, '2026-01-24', 'daily', '2026-01-24 09:14:09'),
(74, 2, 3, 233.00, 631.0339, '2026-01-24', 'daily', '2026-01-24 09:14:09'),
(81, 2, 2, 23.00, 50.3677, '2026-01-23', 'daily', '2026-01-24 09:15:18'),
(82, 2, 3, 23.00, 62.2909, '2026-01-23', 'daily', '2026-01-24 09:15:18'),
(83, 2, 2, 23.00, 50.3677, '2025-12-01', 'monthly', '2026-01-24 09:16:37'),
(84, 2, 3, 234.00, 633.7422, '2025-12-01', 'monthly', '2026-01-24 09:16:37'),
(103, 2, 2, 323.00, 707.3377, '2026-01-01', 'monthly', '2026-01-25 00:04:32'),
(104, 2, 3, 323.00, 874.7809, '2026-01-01', 'monthly', '2026-01-25 00:04:32');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int(11) NOT NULL,
  `dept_name` varchar(100) NOT NULL,
  `building_name` varchar(100) DEFAULT NULL COMMENT 'ชื่ออาคาร/สถานที่',
  `total_staff` int(11) DEFAULT 0 COMMENT 'จำนวนพนักงานในหน่วยงาน',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `dept_name`, `building_name`, `total_staff`, `created_at`) VALUES
(1, 'IT', NULL, 0, '2026-01-20 16:40:50'),
(2, 'Accounting', NULL, 0, '2026-01-20 16:40:50');

-- --------------------------------------------------------

--
-- Table structure for table `emission_factors`
--

CREATE TABLE `emission_factors` (
  `factor_id` int(11) NOT NULL,
  `source_id` int(11) DEFAULT NULL,
  `factor_name` varchar(150) NOT NULL,
  `factor_value` decimal(15,6) NOT NULL COMMENT 'ค่า EF',
  `unit` varchar(50) NOT NULL COMMENT 'เช่น kgCO2e/unit',
  `reference_year` year(4) NOT NULL,
  `source_reference` varchar(255) DEFAULT NULL COMMENT 'แหล่งอ้างอิงค่า EF เช่น TGO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `emission_factors`
--

INSERT INTO `emission_factors` (`factor_id`, `source_id`, `factor_name`, `factor_value`, `unit`, `reference_year`, `source_reference`) VALUES
(1, 1, 'ปริมาณการใช้ไฟฟ้า', 0.499900, 'kWh', '2023', NULL),
(2, 2, 'น้ำมันเบนซิน', 2.189900, 'ลิตร', '2023', NULL),
(3, 2, 'น้ำมันดีเซล', 2.708300, 'ลิตร', '2023', NULL),
(4, 4, 'ปริมาณขยะของเสีย', 0.400000, 'ลิตร', '2023', NULL),
(5, 5, 'กระดาษ A4', 0.001200, 'กิโลกรัม', '2023', NULL),
(6, 2, 'LPG', 3.113400, 'kg', '0000', NULL),
(7, 2, 'สารดับเพลิง (CO2)', 1.000000, 'kgCO2', '0000', NULL),
(8, 2, 'สารมีเทนจากระบบถังบำบัดน้ำเสีย', 28.000000, 'kgH4', '0000', NULL),
(9, 2, 'สารมีเทนจากบ่อบำบัดน้ำเสียแบบไม่เติมอากาศ', 28.000000, 'kgCH4', '0000', NULL),
(10, 2, 'สารทำความเย็นชนิด R134a', 1300.000000, 'HFC-134a', '0000', NULL),
(11, 2, 'สารทำความเย็นชนิด R32', 677.000000, 'HFC-32', '0000', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `emission_sources`
--

CREATE TABLE `emission_sources` (
  `source_id` int(11) NOT NULL,
  `source_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `emission_sources`
--

INSERT INTO `emission_sources` (`source_id`, `source_name`, `description`, `is_active`) VALUES
(1, 'ไฟฟ้า', NULL, 1),
(2, 'น้ำมันเชื้อเพลิง', NULL, 1),
(3, 'การเดินทาง', NULL, 1),
(4, 'ของเสีย', NULL, 1),
(5, 'กระดาษ', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `organization_info`
--

CREATE TABLE `organization_info` (
  `org_id` int(11) NOT NULL,
  `org_name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `total_employees` int(11) DEFAULT NULL,
  `fiscal_year_start` date DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `org_active_activities`
--

CREATE TABLE `org_active_activities` (
  `id` int(11) NOT NULL,
  `source_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL COMMENT 'Admin, User',
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `description`) VALUES
(1, 'User', NULL),
(2, 'Admin', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `role_id`, `dept_id`, `status`, `last_login`, `updated_at`) VALUES
(2, 'tawan', '$2y$10$DlmoOMItCVIsOmaLYnr8Ve0FyeoE01OTQ20JCdR0l1Qgcu586YmEa', 'tawan deemesri', 'tawan@gmail.com', 1, 1, 'active', '2026-02-05 14:50:57', '2026-02-05 07:50:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `dept_id` (`dept_id`),
  ADD KEY `factor_id` (`factor_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `carbon_logs`
--
ALTER TABLE `carbon_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `factor_id` (`factor_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`);

--
-- Indexes for table `emission_factors`
--
ALTER TABLE `emission_factors`
  ADD PRIMARY KEY (`factor_id`),
  ADD KEY `source_id` (`source_id`);

--
-- Indexes for table `emission_sources`
--
ALTER TABLE `emission_sources`
  ADD PRIMARY KEY (`source_id`);

--
-- Indexes for table `organization_info`
--
ALTER TABLE `organization_info`
  ADD PRIMARY KEY (`org_id`);

--
-- Indexes for table `org_active_activities`
--
ALTER TABLE `org_active_activities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `dept_id` (`dept_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `carbon_logs`
--
ALTER TABLE `carbon_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `emission_factors`
--
ALTER TABLE `emission_factors`
  MODIFY `factor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `emission_sources`
--
ALTER TABLE `emission_sources`
  MODIFY `source_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `organization_info`
--
ALTER TABLE `organization_info`
  MODIFY `org_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `org_active_activities`
--
ALTER TABLE `org_active_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `activity_logs_ibfk_2` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`),
  ADD CONSTRAINT `activity_logs_ibfk_3` FOREIGN KEY (`factor_id`) REFERENCES `emission_factors` (`factor_id`);

--
-- Constraints for table `carbon_logs`
--
ALTER TABLE `carbon_logs`
  ADD CONSTRAINT `carbon_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `carbon_logs_ibfk_2` FOREIGN KEY (`factor_id`) REFERENCES `emission_factors` (`factor_id`) ON DELETE CASCADE;

--
-- Constraints for table `emission_factors`
--
ALTER TABLE `emission_factors`
  ADD CONSTRAINT `emission_factors_ibfk_1` FOREIGN KEY (`source_id`) REFERENCES `emission_sources` (`source_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
