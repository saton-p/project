-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 20, 2026 at 07:28 PM
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `password`, `full_name`, `email`, `created_at`) VALUES
(1, 'admin', '$2y$10$RZaQhI3X95fyfbLeB97VyO82l1CYtQe5kCcOOUgZuHbBjlfLOMJNm', 'admin', 'admin@gmail.com', '2026-01-20 18:02:34');

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
(1, 1, 'Electricity Grid Mix (Thailand)', 0.499900, 'kgCO2e/kWh', '2023', NULL),
(2, 2, 'Gasoline', 2.189200, 'kgCO2e/Litre', '2023', NULL),
(3, 2, 'Diesel', 2.708300, 'kgCO2e/Litre', '2023', NULL),
(4, 4, 'Municipal Solid Waste', 0.400000, 'kgCO2e/kg', '2023', NULL),
(5, 5, 'A4 Paper', 0.001200, 'kgCO2e/Sheet', '2023', NULL);

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
(2, 'tawan', '$2y$10$gxnN0uEgfII./gyHvrEQ1Of2llkcs9VAxnWniSAYZ1akMQK.rMmci', 'tawan deemesri', 'tawan@gmail.com', 1, 1, 'active', '2026-01-21 00:43:29', '2026-01-20 17:43:29');

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
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `emission_factors`
--
ALTER TABLE `emission_factors`
  MODIFY `factor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
