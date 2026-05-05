-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql100.byetcluster.com
-- Generation Time: May 05, 2026 at 02:19 AM
-- Server version: 11.4.10-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_41081040_carbon_footprint_db`
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
  `amount` decimal(12,4) NOT NULL,
  `emission_result` decimal(12,4) NOT NULL,
  `log_date` date NOT NULL,
  `log_type` varchar(20) DEFAULT 'daily',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `carbon_logs`
--

INSERT INTO `carbon_logs` (`id`, `user_id`, `factor_id`, `amount`, `emission_result`, `log_date`, `log_type`, `created_at`) VALUES
(63, 2, 2, '1221.0000', '2673.8679', '2026-01-24', 'daily', '2026-01-24 08:58:31'),
(64, 2, 3, '1221.0000', '3306.8343', '2026-01-24', 'daily', '2026-01-24 08:58:31'),
(65, 2, 2, '231.0000', '505.8669', '2026-01-01', 'daily', '2026-01-24 09:03:05'),
(66, 2, 3, '321.0000', '869.3643', '2026-01-01', 'daily', '2026-01-24 09:03:05'),
(67, 2, 2, '2332.0000', '5106.8468', '2026-01-24', 'daily', '2026-01-24 15:07:33'),
(68, 2, 3, '232.0000', '628.3256', '2026-01-24', 'daily', '2026-01-24 15:07:33'),
(69, 2, 2, '23.0000', '50.3677', '2026-01-24', 'daily', '2026-01-24 15:08:44'),
(70, 2, 3, '23.0000', '62.2909', '2026-01-24', 'daily', '2026-01-24 15:08:44'),
(71, 2, 2, '23.0000', '50.3677', '2026-01-01', 'daily', '2026-01-24 15:09:02'),
(72, 2, 3, '23.0000', '62.2909', '2026-01-01', 'daily', '2026-01-24 15:09:02'),
(73, 2, 2, '233.0000', '510.2467', '2026-01-24', 'daily', '2026-01-24 09:14:09'),
(74, 2, 3, '233.0000', '631.0339', '2026-01-24', 'daily', '2026-01-24 09:14:09'),
(81, 2, 2, '23.0000', '50.3677', '2026-01-23', 'daily', '2026-01-24 09:15:18'),
(82, 2, 3, '23.0000', '62.2909', '2026-01-23', 'daily', '2026-01-24 09:15:18'),
(83, 2, 2, '23.0000', '50.3677', '2025-12-01', 'monthly', '2026-01-24 09:16:37'),
(84, 2, 3, '234.0000', '633.7422', '2025-12-01', 'monthly', '2026-01-24 09:16:37'),
(103, 2, 2, '323.0000', '707.3377', '2026-01-01', 'monthly', '2026-01-25 00:04:32'),
(104, 2, 3, '323.0000', '874.7809', '2026-01-01', 'monthly', '2026-01-25 00:04:32'),
(108, 2, 2, '23232.0000', '50875.7568', '2026-02-12', 'daily', '2026-02-12 01:59:59'),
(109, 2, 3, '232.0000', '628.3256', '2026-02-12', 'daily', '2026-02-12 02:04:20'),
(110, 2, 3, '321.0000', '869.3643', '2026-02-01', 'monthly', '2026-02-12 02:38:32'),
(111, 2, 1, '321.0000', '160.4679', '2026-02-01', 'monthly', '2026-02-12 02:38:32'),
(112, 2, 2, '322.0000', '882.4732', '2026-02-01', 'monthly', '2026-02-20 02:34:36'),
(113, 2, 13, '322.0000', '731.5518', '2026-02-01', 'monthly', '2026-02-20 02:34:36'),
(114, 2, 1, '322.0000', '160.9678', '2026-02-01', 'monthly', '2026-02-20 02:34:36'),
(115, 2, 5, '322.0000', '360.6400', '2026-02-01', 'monthly', '2026-02-20 02:34:36'),
(116, 2, 15, '322.0000', '255.9256', '2026-02-01', 'monthly', '2026-02-20 02:34:36'),
(117, 2, 4, '322.0000', '202.2160', '2026-02-01', 'monthly', '2026-02-20 02:34:36'),
(118, 2, 16, '322.0000', '647.2200', '2026-02-01', 'monthly', '2026-02-20 02:34:36'),
(119, 5, 3, '10.0000', '27.0830', '2026-02-24', 'daily', '2026-02-25 01:08:57'),
(120, 5, 12, '10.0000', '27.0780', '2026-02-24', 'daily', '2026-02-25 01:08:57'),
(121, 5, 2, '10.0000', '27.4060', '2026-02-24', 'daily', '2026-02-25 01:08:57'),
(122, 5, 7, '10.0000', '10.0000', '2026-02-24', 'daily', '2026-02-25 01:08:57'),
(123, 5, 9, '10.0000', '280.0000', '2026-02-24', 'daily', '2026-02-25 01:08:57'),
(124, 5, 10, '10.0000', '13000.0000', '2026-02-24', 'daily', '2026-02-25 01:08:57'),
(125, 5, 1, '10.0000', '4.9990', '2026-02-24', 'daily', '2026-02-25 01:08:57'),
(126, 5, 5, '50.0000', '56.0000', '2026-02-24', 'daily', '2026-02-25 01:08:57'),
(127, 5, 15, '10.0000', '7.9480', '2026-02-24', 'daily', '2026-02-25 01:08:57'),
(128, 5, 4, '10.0000', '6.2800', '2026-02-24', 'daily', '2026-02-25 01:08:57'),
(129, 5, 3, '50000.0000', '135415.0000', '2026-02-01', 'monthly', '2026-02-25 01:11:09'),
(130, 5, 2, '50.0000', '137.0300', '2026-02-01', 'monthly', '2026-02-25 01:11:09'),
(131, 5, 10, '10000.0000', '999999.9999', '2026-02-01', 'monthly', '2026-02-25 01:11:09'),
(132, 5, 1, '10000.0000', '4999.0000', '2026-02-01', 'monthly', '2026-02-25 01:11:09'),
(133, 5, 4, '100000.0000', '62800.0000', '2026-02-01', 'monthly', '2026-02-25 01:11:09'),
(134, 5, 3, '45645.0000', '123620.3535', '2026-01-01', 'quarterly', '2026-02-25 01:12:36'),
(135, 5, 2, '475645.0000', '999999.9999', '2026-01-01', 'quarterly', '2026-02-25 01:12:36'),
(136, 5, 7, '456.0000', '456.0000', '2026-01-01', 'quarterly', '2026-02-25 01:12:36'),
(137, 5, 8, '456.0000', '12768.0000', '2026-01-01', 'quarterly', '2026-02-25 01:12:36'),
(138, 5, 9, '456.0000', '12768.0000', '2026-01-01', 'quarterly', '2026-02-25 01:12:36'),
(139, 5, 1, '456465.0000', '228186.8535', '2026-01-01', 'quarterly', '2026-02-25 01:12:36'),
(140, 5, 5, '456.0000', '510.7200', '2026-01-01', 'quarterly', '2026-02-25 01:12:36'),
(141, 5, 3, '789423.0000', '999999.9999', '2026-01-01', 'yearly', '2026-02-25 01:13:10'),
(142, 5, 2, '45645654.0000', '999999.9999', '2026-01-01', 'yearly', '2026-02-25 01:13:10'),
(143, 5, 7, '456.0000', '456.0000', '2026-01-01', 'yearly', '2026-02-25 01:13:10'),
(144, 5, 8, '456.0000', '12768.0000', '2026-01-01', 'yearly', '2026-02-25 01:13:10'),
(145, 5, 9, '456.0000', '12768.0000', '2026-01-01', 'yearly', '2026-02-25 01:13:10'),
(146, 5, 6, '456.0000', '1419.7104', '2026-01-01', 'yearly', '2026-02-25 01:13:10'),
(147, 5, 1, '99999999.9900', '999999.9999', '2026-01-01', 'yearly', '2026-02-25 01:13:10'),
(148, 5, 4, '456456.0000', '286654.3680', '2026-01-01', 'yearly', '2026-02-25 01:13:10'),
(149, 5, 16, '4564564.0000', '999999.9999', '2026-01-01', 'yearly', '2026-02-25 01:13:10'),
(150, 5, 17, '456654.0000', '999999.9999', '2026-01-01', 'yearly', '2026-02-25 01:13:10'),
(151, 6, 3, '322.0000', '872.0726', '2026-02-01', 'monthly', '2026-02-25 03:49:00'),
(152, 6, 12, '322.0000', '871.9116', '2026-02-01', 'monthly', '2026-02-25 03:49:00'),
(153, 6, 1, '3222.0000', '1610.6778', '2026-02-01', 'monthly', '2026-02-25 03:49:00'),
(154, 6, 5, '322.0000', '360.6400', '2026-02-01', 'monthly', '2026-02-25 03:49:00'),
(155, 6, 2, '344.0000', '942.7664', '2026-02-24', 'daily', '2026-02-25 03:49:14'),
(156, 6, 13, '344.0000', '781.5336', '2026-02-24', 'daily', '2026-02-25 03:49:14'),
(157, 6, 14, '344.0000', '781.5336', '2026-02-24', 'daily', '2026-02-25 03:49:14'),
(167, 6, 3, '345.0000', '934.3635', '2026-02-25', 'daily', '2026-02-25 19:54:27'),
(168, 6, 2, '345.0000', '945.5070', '2026-02-25', 'daily', '2026-02-25 19:54:27'),
(169, 6, 13, '345.0000', '783.8055', '2026-02-25', 'daily', '2026-02-25 19:54:27'),
(170, 6, 14, '345.0000', '783.8055', '2026-02-25', 'daily', '2026-02-25 19:54:27'),
(171, 6, 1, '160000.0000', '79984.0000', '2026-02-01', 'monthly', '2026-02-25 20:34:18'),
(172, 6, 1, '160000.0000', '79984.0000', '2024-06-01', 'monthly', '2026-02-27 16:18:09'),
(173, 6, 1, '218000.0000', '108978.2000', '2024-07-01', 'monthly', '2026-02-27 16:18:32'),
(174, 6, 1, '216000.0000', '107978.4000', '2024-08-01', 'monthly', '2026-02-27 16:19:02'),
(175, 6, 1, '216656.0000', '108306.3344', '2024-09-01', 'monthly', '2026-02-27 16:19:33'),
(176, 6, 1, '135000.0000', '67486.5000', '2024-10-01', 'monthly', '2026-02-27 16:20:02'),
(177, 6, 1, '175000.0000', '87482.5000', '2024-11-01', 'monthly', '2026-02-27 16:20:23'),
(178, 6, 1, '119000.0000', '59488.1000', '2024-12-01', 'monthly', '2026-02-27 16:20:52'),
(179, 6, 1, '106000.0000', '52989.4000', '2025-01-01', 'monthly', '2026-02-27 16:21:08'),
(180, 6, 1, '122000.0000', '60987.8000', '2025-02-01', 'monthly', '2026-02-27 16:21:30'),
(181, 6, 1, '89000.0000', '44491.1000', '2025-03-01', 'monthly', '2026-02-27 16:21:53'),
(182, 6, 1, '83000.0000', '41491.7000', '2025-04-01', 'monthly', '2026-02-27 16:22:10'),
(183, 6, 1, '84000.0000', '41991.6000', '2025-05-01', 'monthly', '2026-02-27 16:22:29'),
(184, 6, 4, '39216.0000', '24627.6480', '2025-01-01', 'yearly', '2026-02-27 16:23:21'),
(192, 6, 19, '691.0000', '1513.0136', '2026-03-16', 'daily', '2026-03-16 20:36:53'),
(193, 6, 1, '123.0000', '61.4877', '2026-03-16', 'daily', '2026-03-16 20:36:53'),
(203, 6, 19, '707.9700', '1550.1711', '2024-01-01', 'quarterly', '2026-03-16 21:34:19'),
(204, 6, 2, '1382.2700', '3788.2492', '2024-01-01', 'quarterly', '2026-03-16 21:34:19'),
(205, 6, 1, '563000.0000', '281443.7000', '2024-01-01', 'quarterly', '2026-03-16 21:34:19'),
(206, 6, 19, '586.4100', '1284.0033', '2024-04-01', 'quarterly', '2026-03-16 21:34:55'),
(207, 6, 2, '796.5100', '2182.9153', '2024-04-01', 'quarterly', '2026-03-16 21:34:55'),
(208, 6, 1, '426000.0000', '212957.4000', '2024-04-01', 'quarterly', '2026-03-16 21:34:55'),
(209, 6, 19, '837.2100', '1833.1550', '2024-07-01', 'quarterly', '2026-03-16 21:35:50'),
(210, 6, 2, '1828.3400', '5010.7486', '2024-07-01', 'quarterly', '2026-03-16 21:35:50'),
(211, 6, 1, '650656.0000', '325262.9344', '2024-07-01', 'quarterly', '2026-03-16 21:35:50'),
(212, 6, 19, '671.6300', '1470.6010', '2024-10-01', 'quarterly', '2026-03-16 21:36:35'),
(213, 6, 2, '1325.1800', '3631.7883', '2024-10-01', 'quarterly', '2026-03-16 21:36:35'),
(214, 6, 1, '429000.0000', '214457.1000', '2024-10-01', 'quarterly', '2026-03-16 21:36:35'),
(215, 6, 19, '794.1000', '1738.7614', '2025-01-01', 'quarterly', '2026-03-16 21:36:54'),
(216, 6, 2, '919.0000', '2518.6114', '2025-01-01', 'quarterly', '2026-03-16 21:36:54'),
(217, 6, 1, '317000.0000', '158468.3000', '2025-01-01', 'quarterly', '2026-03-16 21:36:54'),
(218, 6, 19, '680.1100', '1489.1689', '2025-04-01', 'quarterly', '2026-03-16 21:37:16'),
(219, 6, 2, '1178.4500', '3229.6601', '2025-04-01', 'quarterly', '2026-03-16 21:37:16'),
(220, 6, 1, '426000.0000', '212957.4000', '2025-04-01', 'quarterly', '2026-03-16 21:37:16'),
(221, 6, 19, '614.4100', '1345.3121', '2025-07-01', 'quarterly', '2026-03-16 21:37:49'),
(222, 6, 2, '1159.4100', '3177.4790', '2025-07-01', 'quarterly', '2026-03-16 21:37:49'),
(223, 6, 1, '536000.0000', '267946.4000', '2025-07-01', 'quarterly', '2026-03-16 21:37:49'),
(230, 6, 19, '174.1700', '381.3626', '2025-10-01', 'quarterly', '2026-03-16 21:39:32'),
(231, 6, 2, '623.4100', '1708.5174', '2025-10-01', 'quarterly', '2026-03-16 21:39:32'),
(232, 6, 1, '102000.0000', '50989.8000', '2025-10-01', 'quarterly', '2026-03-16 21:39:32');

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
  `scope_id` int(11) NOT NULL DEFAULT 1 COMMENT '1=Scope1, 2=Scope2, 3=Scope3',
  `factor_name` varchar(150) NOT NULL,
  `factor_value` decimal(12,6) NOT NULL,
  `unit` varchar(50) NOT NULL COMMENT 'เช่น kgCO2e/unit',
  `reference_year` year(4) NOT NULL,
  `source_reference` varchar(255) DEFAULT NULL COMMENT 'แหล่งอ้างอิงค่า EF เช่น TGO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `emission_factors`
--

INSERT INTO `emission_factors` (`factor_id`, `source_id`, `scope_id`, `factor_name`, `factor_value`, `unit`, `reference_year`, `source_reference`) VALUES
(1, 8, 2, 'ปริมาณการใช้ไฟฟ้า', '0.499900', 'kWh', 2023, NULL),
(2, 2, 1, 'น้ำมัน Diesel', '2.740600', 'ลิตร', 2023, NULL),
(3, 1, 1, 'เครื่องกำเนิดไฟฟ้าดีเซล', '2.708300', 'ลิตร', 2023, NULL),
(4, 11, 3, 'เศษอาหาร', '0.628000', 'ลิตร', 2023, NULL),
(5, 9, 3, 'กระดาษ A4 (กระดาษที่ใช้งานแล้ว)', '1.120000', 'กิโลกรัม', 2023, NULL),
(6, 7, 1, 'LPG', '3.113400', 'กิโลกรัม', 0000, NULL),
(7, 3, 1, 'สารดับเพลิง (CO2)', '1.000000', 'kgCO2', 0000, NULL),
(8, 4, 1, 'สารมีเทนจากระบบถังบำบัดน้ำเสีย', '28.000000', 'kgH4', 0000, NULL),
(9, 5, 1, 'สารมีเทนจากบ่อบำบัดน้ำเสียแบบไม่เติมอากาศ', '28.000000', 'kgCH4', 0000, NULL),
(10, 6, 1, 'สารทำความเย็นชนิด R134a', '1300.000000', 'HFC-134a', 0000, NULL),
(11, 6, 1, 'สารทำความเย็นชนิด R32', '677.000000', 'HFC-32', 0000, NULL),
(12, 1, 1, 'ปั๊มน้ำดับเพลิงเครื่องยนต์ดีเซล', '2.707800', 'ลิตร', 0000, NULL),
(13, 2, 1, 'น้ำมัน Gasohol 91, E20, E85', '2.271900', 'ลิตร', 0000, NULL),
(14, 2, 1, 'น้ำมัน Gasohol 95', '2.271900', 'ลิตร', 0000, NULL),
(15, 10, 3, 'การใช้น้ำประปา', '0.794800', 'ลูกบาศก์เมตร', 0000, NULL),
(16, 11, 3, 'ขวดพลาสติก', '2.010000', 'กิโลกรัม', 0000, NULL),
(17, 11, 3, 'ถุงขยะ (ถุงดำ)', '2.510000', 'กิโลกรัม', 0000, NULL),
(19, 1, 1, 'น้ำมันเบนซิน / Gasoline', '2.189600', 'ลิตร', 0000, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `emission_sources`
--

CREATE TABLE `emission_sources` (
  `source_id` int(11) NOT NULL,
  `source_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `scope_id` int(11) NOT NULL DEFAULT 3 COMMENT '1=Scope1, 2=Scope2, 3=Scope3'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `emission_sources`
--

INSERT INTO `emission_sources` (`source_id`, `source_name`, `description`, `is_active`, `scope_id`) VALUES
(1, 'การเผาไหม้แบบอยู่กับที่ (การใช้น้ำมันสำหรับงานอาคาร)', NULL, 1, 1),
(2, 'การเผาไหม้แบบเคลื่อนที่ (การใช้น้ำมันสำหรับการเดินทาง)', NULL, 1, 1),
(3, 'การใช้สารดับเพลิง (Fire Suppressant)', NULL, 1, 1),
(4, 'การปล่อยสารมีเทนจากระบบ Septic Tank', NULL, 1, 1),
(5, 'การปล่อยสารมีเทนจากบ่อบำบัดน้ำเสีย', NULL, 1, 1),
(6, 'การใช้สารทำความเย็น (Refrigerant)', NULL, 1, 1),
(7, 'การใช้ LPG', NULL, 1, 1),
(8, 'การใช้พลังงานไฟฟ้า', NULL, 1, 2),
(9, 'การใช้กระดาษ', NULL, 1, 3),
(10, 'การใช้น้ำประปา', NULL, 1, 3),
(11, 'ขยะของเสีย', NULL, 1, 3);

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

--
-- Dumping data for table `organization_info`
--

INSERT INTO `organization_info` (`org_id`, `org_name`, `address`, `total_employees`, `fiscal_year_start`, `logo_path`) VALUES
(1, 'wdasdwasdwa', 'sadwasdwasd', 10, '2026-02-20', NULL);

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
(2, 'tawan', '$2y$10$DlmoOMItCVIsOmaLYnr8Ve0FyeoE01OTQ20JCdR0l1Qgcu586YmEa', 'tawan deemesri', 'tawan@gmail.com', 1, 1, 'active', '2026-02-23 23:33:19', '2026-02-24 07:33:19'),
(4, 'Samitta', '$2y$10$oxeN6OE23gtAA8sqaFH4h.1iuT3aMVknb2D.GLpWfk1CYCKyRqXEi', 'สมิทธา กมุทชาติ', 'samitta-k@rmutp.ac.th', 1, 1, 'active', '2026-03-15 22:02:41', '2026-03-16 05:02:41'),
(5, 'rillnook', '$2y$10$4TktyEg23fD1gNCBfb5KWuyWexduAU/kQdWqTmclHDIC38c/8pHFK', 'สรวิชญ์ วรรรอุบล', 'rillnook@gmail.com', 1, 1, 'active', '2026-02-24 02:10:28', '2026-02-24 10:10:28'),
(6, 'user', '$2y$10$k8cSW2wUugsNv.eKajCCkeMyThRZHGxEzB2psIJEK8WSJg.ghyLfO', 'user', 'user@gmail.com', 1, 1, 'active', '2026-05-04 23:15:44', '2026-05-05 06:15:44');

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
  ADD KEY `fk_user_dept` (`dept_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=233;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `emission_factors`
--
ALTER TABLE `emission_factors`
  MODIFY `factor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `emission_sources`
--
ALTER TABLE `emission_sources`
  MODIFY `source_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `organization_info`
--
ALTER TABLE `organization_info`
  MODIFY `org_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  ADD CONSTRAINT `fk_user_dept` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
