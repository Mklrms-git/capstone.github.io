-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 14, 2025 at 08:36 PM
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
-- Database: `mhavis`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `ip_address`, `created_at`) VALUES
(32, 3, 'Appointment request submitted', '::1', '2025-10-31 07:07:51'),
(33, 3, 'Appointment request submitted', '::1', '2025-10-31 07:13:17'),
(34, 3, 'Appointment request submitted', '::1', '2025-11-01 07:58:12'),
(35, 8, 'Appointment request submitted', '::1', '2025-11-01 09:16:52'),
(36, 8, 'Appointment request submitted', '::1', '2025-11-01 09:23:07'),
(37, 8, 'Appointment request submitted', '::1', '2025-11-01 09:31:29'),
(38, 9, 'Appointment request submitted', '::1', '2025-11-05 00:54:37'),
(39, 3, 'Appointment request submitted', '::1', '2025-11-05 01:19:12'),
(40, 11, 'Appointment request submitted', '::1', '2025-11-16 11:09:05'),
(41, 14, 'Appointment request submitted', '::1', '2025-11-16 12:32:28'),
(42, 14, 'Appointment request submitted', '::1', '2025-11-16 12:43:32'),
(43, 15, 'Appointment request submitted', '::1', '2025-11-25 00:36:04'),
(44, 15, 'Appointment request submitted', '::1', '2025-11-25 00:36:25'),
(45, 15, 'Appointment request submitted', '::1', '2025-11-26 18:10:36'),
(46, 15, 'Appointment request submitted', '::1', '2025-11-26 19:56:47'),
(47, 9, 'Appointment request submitted', '::1', '2025-11-27 00:01:04'),
(48, 17, 'Appointment request submitted', '::1', '2025-11-27 03:49:42'),
(49, 9, 'Appointment request submitted', '::1', '2025-12-02 08:45:45'),
(50, 9, 'Appointment request submitted', '::1', '2025-12-04 06:13:54'),
(51, 9, 'Appointment request submitted', '::1', '2025-12-04 06:14:35'),
(52, 9, 'Appointment request submitted', '::1', '2025-12-04 06:45:55'),
(53, 15, 'Appointment request submitted', '::1', '2025-12-06 16:22:27');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `old_doctor_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('scheduled','ongoing','settled','cancelled') NOT NULL DEFAULT 'scheduled',
  `reason` text NOT NULL,
  `notes` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status_updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `old_doctor_id`, `doctor_id`, `department_id`, `appointment_date`, `appointment_time`, `status`, `reason`, `notes`, `created_at`, `updated_at`, `status_updated_at`) VALUES
(2, 1, 18, 1, NULL, '2025-07-01', '11:00:00', 'settled', 'Consulation', '', '2025-06-04 09:32:22', '2025-11-30 16:49:20', '2025-06-04 09:32:22'),
(4, 4, 18, 1, NULL, '2025-06-05', '10:00:00', 'settled', 'Follow ups', '', '2025-06-05 06:44:48', '2025-11-30 16:49:20', '2025-06-05 06:44:48'),
(6, 9, 18, 1, NULL, '2025-08-05', '17:15:00', 'settled', 'Consulation', 'sample notes', '2025-07-31 04:14:30', '2025-11-30 16:49:20', '2025-07-31 04:14:30'),
(11, 4, NULL, 1, NULL, '2025-08-22', '08:10:00', 'settled', 'Follow ups', 'WALANG NOTES', '2025-08-14 12:10:52', '2025-11-30 16:49:20', '2025-08-14 12:10:52'),
(17, 9, NULL, 1, NULL, '2025-09-07', '13:00:00', 'settled', 'Follow-Up', '', '2025-09-05 10:37:45', '2025-11-30 16:49:20', '2025-09-05 10:37:45'),
(41, 28, NULL, 1, NULL, '2025-11-10', '11:00:00', 'settled', 'Check up ', 'Approved by admin. Notes: Sample Notes lang itu muna', '2025-11-05 00:55:17', '2025-11-30 16:49:20', '2025-11-05 00:55:17'),
(46, 34, NULL, 1, NULL, '2025-11-26', '14:00:00', 'cancelled', 'sample', 'Approved by admin. ', '2025-11-25 00:36:38', '2025-11-25 00:36:38', '2025-11-25 00:36:38'),
(48, 34, NULL, 1, NULL, '2025-12-01', '10:30:00', 'ongoing', 'checkup', '', '2025-11-26 15:25:02', '2025-11-26 15:25:02', '2025-11-26 15:25:02'),
(50, 34, NULL, 1, NULL, '2025-12-01', '13:00:00', 'ongoing', 'Check up', '', '2025-11-26 16:53:21', '2025-11-26 16:53:21', '2025-11-26 16:53:21'),
(51, 34, NULL, 1, NULL, '2025-12-08', '09:00:00', 'scheduled', 'Consultation', '', '2025-11-26 16:55:01', '2025-11-26 16:55:01', '2025-11-26 16:55:01'),
(52, 34, NULL, 1, NULL, '2025-12-01', '10:00:00', 'cancelled', 'sample', 'Approved by admin. ', '2025-11-26 18:11:38', '2025-11-26 18:11:38', '2025-11-26 18:11:38'),
(54, 34, NULL, 1, NULL, '2025-12-01', '09:00:00', 'cancelled', 'eme', 'eme', '2025-11-28 07:10:53', '2025-11-28 07:10:53', '2025-11-28 07:10:53'),
(55, 28, NULL, 1, NULL, '2025-12-08', '09:30:00', 'settled', 'sdtyul', 'Approved by admin. ', '2025-12-02 08:50:02', '2025-12-02 08:50:02', '2025-12-02 08:50:02'),
(56, 34, NULL, 1, NULL, '2025-12-01', '11:00:00', 'scheduled', 'sample', 'Approved by admin. ', '2025-12-06 16:17:05', '2025-12-06 16:17:05', '2025-12-06 16:17:05'),
(57, 34, NULL, 1, NULL, '2025-12-10', '15:30:00', 'settled', 'sasasas', 'Approved by admin. ', '2025-12-06 16:27:07', '2025-12-06 16:27:07', '2025-12-06 16:27:07'),
(58, 34, NULL, 1, NULL, '2025-12-15', '11:00:00', 'cancelled', 'General Check Up', '', '2025-12-14 16:23:01', '2025-12-14 16:23:01', NULL),
(59, 34, NULL, 1, NULL, '2025-12-15', '09:00:00', 'cancelled', 'Consultation', '\n\n[CANCELLED] Reason: DOCTOR EMERGENCY (Cancelled on 2025-12-15 01:44:30 by Admin)', '2025-12-14 16:26:22', '2025-12-14 16:26:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `appointment_requests`
--

CREATE TABLE `appointment_requests` (
  `id` int(11) NOT NULL,
  `patient_user_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time` time NOT NULL,
  `reason` text NOT NULL,
  `urgency` enum('Low','Medium','High') DEFAULT 'Medium',
  `status` enum('Pending','Approved','Rejected','Rescheduled') DEFAULT 'Pending',
  `admin_notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_requests`
--

INSERT INTO `appointment_requests` (`id`, `patient_user_id`, `doctor_id`, `department_id`, `preferred_date`, `preferred_time`, `reason`, `urgency`, `status`, `admin_notes`, `approved_by`, `approved_at`, `appointment_id`, `created_at`, `updated_at`) VALUES
(10, 9, 18, 3, '2025-11-10', '11:00:00', 'Check up ', 'Low', 'Approved', 'Sample Notes lang itu muna', 17, '2025-11-05 08:55:17', 41, '2025-11-05 00:54:37', '2025-11-05 00:55:17'),
(15, 15, 18, 3, '2025-11-26', '14:00:00', 'sample', 'Medium', 'Approved', '', 17, '2025-11-25 08:36:38', 46, '2025-11-25 00:36:04', '2025-11-25 00:36:38'),
(17, 15, 18, 3, '2025-12-01', '10:00:00', 'sample', 'Medium', 'Approved', '', 17, '2025-11-27 02:11:38', 52, '2025-11-26 18:10:36', '2025-11-26 18:11:38'),
(18, 15, 18, 3, '2025-12-01', '11:00:00', 'sample', 'Medium', 'Approved', '', 17, '2025-12-07 00:17:05', 56, '2025-11-26 19:56:47', '2025-12-06 16:17:05'),
(21, 9, 18, 3, '2025-12-08', '09:30:00', 'sdtyul', 'Medium', 'Approved', '', 17, '2025-12-02 16:50:02', 55, '2025-12-02 08:45:45', '2025-12-02 08:50:02'),
(25, 15, 18, 3, '2025-12-10', '15:30:00', 'sasasas', 'Medium', 'Approved', '', 17, '2025-12-07 00:27:07', 57, '2025-12-06 16:22:27', '2025-12-06 16:27:07');

-- --------------------------------------------------------

--
-- Stand-in structure for view `category_revenue_view`
-- (See below for the actual view)
--
CREATE TABLE `category_revenue_view` (
`category_id` int(11)
,`category_name` varchar(100)
,`category_description` text
,`transaction_count` bigint(21)
,`gross_revenue` decimal(32,2)
,`total_discounts` decimal(32,2)
,`net_revenue` decimal(32,2)
,`average_revenue` decimal(14,6)
,`fees_used` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `color` varchar(7) DEFAULT '#007bff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `created_at`, `updated_at`, `color`) VALUES
(1, 'Cardiology', 'Heart and cardiovascular system', '2025-07-31 00:53:58', '2025-07-31 09:47:23', '#dc3545'),
(2, 'ENT', 'Ear, Nose, and Throat care', '2025-07-31 00:53:58', '2025-07-31 09:47:38', '#20c997'),
(3, 'Internal Medicine', 'Comprehensive adult medical care', '2025-07-31 00:53:58', '2025-07-31 09:47:56', '#28a745'),
(4, 'OB-GYN', 'Obstetrics and Gynecology', '2025-07-31 00:53:58', '2025-07-31 09:48:38', '#e83e8c'),
(5, 'Orthopedic', 'Bones, joints, and musculoskeletal system', '2025-07-31 00:53:58', '2025-07-31 09:48:54', '#17a2b8'),
(6, 'Pediatrics', 'Child and adolescent healthcare', '2025-07-31 00:53:58', '2025-07-31 09:49:08', '#fd7e14'),
(7, 'Psychiatry', 'Mental health and psychiatric care', '2025-07-31 00:53:58', '2025-07-31 09:49:23', '#6f42c1'),
(8, 'Surgery', 'Surgical procedures and operations', '2025-07-31 00:53:58', '2025-07-31 00:53:58', '#007bff'),
(17, 'ORTHO', NULL, '2025-11-26 08:10:22', '2025-11-26 08:10:22', '#6c757d');

-- --------------------------------------------------------

--
-- Stand-in structure for view `department_performance_view`
-- (See below for the actual view)
--
CREATE TABLE `department_performance_view` (
`department` varchar(100)
,`department_name` varchar(100)
,`total_transactions` bigint(21)
,`gross_revenue` decimal(32,2)
,`total_discounts` decimal(32,2)
,`net_revenue` decimal(32,2)
,`average_revenue` decimal(14,6)
,`unique_services_used` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `user_id`, `specialization`, `license_number`, `department_id`, `created_at`) VALUES
(1, 18, 'Internal Medicine', NULL, 3, '2025-08-02 10:37:24'),
(4, 30, 'Psychiatry', '098889', 7, '2025-12-06 17:42:48'),
(5, 31, 'Surgery', '2023-123456', 8, '2025-12-09 14:14:46');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_departments`
--

CREATE TABLE `doctor_departments` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL COMMENT 'References users.id where role=Doctor',
  `department_id` int(11) NOT NULL COMMENT 'References departments.id',
  `specialization` varchar(100) DEFAULT NULL COMMENT 'Specialization for this department',
  `prc_number` varchar(50) DEFAULT NULL COMMENT 'PRC number for this department',
  `license_type` varchar(50) DEFAULT NULL COMMENT 'License type for this department',
  `prc_id_document` varchar(255) DEFAULT NULL COMMENT 'PRC ID document path for this department',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_departments`
--

INSERT INTO `doctor_departments` (`id`, `doctor_id`, `department_id`, `specialization`, `prc_number`, `license_type`, `prc_id_document`, `created_at`, `updated_at`) VALUES
(1, 18, 3, 'Internal Medicine', '0987654', 'RN', NULL, '2025-12-06 17:18:23', '2025-12-06 17:18:23'),
(2, 30, 6, 'sample', '01298777', 'RPh', NULL, '2025-12-06 17:42:48', '2025-12-06 17:42:48'),
(3, 30, 7, 'Psychiatry', '098889', 'RPT', 'uploads/prc_1765042968_69346b1830527.jpg', '2025-12-06 17:42:48', '2025-12-06 17:42:48'),
(4, 31, 1, '', '01234569', 'Other', 'uploads/prc_dept_1765289686_69382ed696714_0.jpg', '2025-12-09 14:14:46', '2025-12-09 14:14:46'),
(5, 31, 8, 'Surgery', '2023-123456', 'MD', 'uploads/prc_1765289686_69382ed67de6a.jpg', '2025-12-09 14:14:46', '2025-12-09 14:14:46');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_leaves`
--

CREATE TABLE `doctor_leaves` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL COMMENT 'References users.id',
  `leave_type` enum('Annual','Sick','Maternity','Paternity','Parental Leave','Emergency Leave','Bereavement Leave') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Active','Cancelled') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_leaves`
--

INSERT INTO `doctor_leaves` (`id`, `doctor_id`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `created_at`, `updated_at`) VALUES
(1, 23, '', '2025-12-01', '2025-12-03', 'punta ako boracay ', 'Active', '2025-11-27 00:09:08', '2025-12-14 17:59:48'),
(2, 23, '', '2025-12-04', '2025-12-11', 'wala lang', 'Active', '2025-11-28 07:08:19', '2025-12-14 17:59:48'),
(6, 18, 'Maternity', '2025-12-15', '2025-12-15', '', 'Active', '2025-12-14 18:35:48', '2025-12-14 18:35:48');

-- --------------------------------------------------------

--
-- Stand-in structure for view `doctor_performance_view`
-- (See below for the actual view)
--
CREATE TABLE `doctor_performance_view` (
`doctor_id` int(11)
,`doctor_name` varchar(511)
,`total_transactions` bigint(21)
,`gross_revenue` decimal(32,2)
,`total_discounts` decimal(32,2)
,`net_revenue` decimal(32,2)
,`average_revenue` decimal(14,6)
,`active_days` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `doctor_schedules`
--

CREATE TABLE `doctor_schedules` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `day_of_week` varchar(10) NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_schedules`
--

INSERT INTO `doctor_schedules` (`id`, `doctor_id`, `day_of_week`, `is_available`, `start_time`, `end_time`, `break_start`, `break_end`, `created_at`, `updated_at`) VALUES
(1, 18, '1', 1, '09:00:00', '15:00:00', '12:00:00', '13:00:00', '2025-07-31 03:18:27', '2025-11-04 23:16:32'),
(2, 18, '2', 0, '09:00:00', '17:00:00', '12:00:00', '13:00:00', '2025-07-31 03:18:27', '2025-07-31 03:18:27'),
(3, 18, '3', 1, '09:00:00', '17:00:00', '12:00:00', '13:00:00', '2025-07-31 03:18:27', '2025-07-31 03:18:27'),
(4, 18, '4', 0, '09:00:00', '17:00:00', '12:00:00', '13:00:00', '2025-07-31 03:18:27', '2025-07-31 03:18:27'),
(5, 18, '5', 0, '09:00:00', '17:00:00', '12:00:00', '13:00:00', '2025-07-31 03:18:27', '2025-11-04 23:16:32'),
(6, 18, '6', 0, '09:00:00', '17:00:00', '12:00:00', '13:00:00', '2025-07-31 03:18:27', '2025-11-04 23:16:32'),
(7, 18, '7', 0, '09:00:00', '17:00:00', '12:00:00', '13:00:00', '2025-07-31 03:18:27', '2025-07-31 03:18:27');

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `to_email` varchar(100) NOT NULL,
  `to_name` varchar(100) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `body_type` enum('text','html') DEFAULT 'html',
  `status` enum('Pending','Sent','Failed') DEFAULT 'Pending',
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 3,
  `last_attempt` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `scheduled_at` datetime DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_queue`
--

INSERT INTO `email_queue` (`id`, `to_email`, `to_name`, `subject`, `body`, `body_type`, `status`, `attempts`, `max_attempts`, `last_attempt`, `error_message`, `scheduled_at`, `sent_at`, `created_at`) VALUES
(1, 'hannahpauline1016@gmail.com', 'Hannah Placio', 'Registration Status - Mhavis Medical & Diagnostic Center', 'Dear Hannah,\n\nThank you for your interest in registering with Mhavis Medical & Diagnostic Center.\n\nUnfortunately, we cannot approve your registration at this time.\n\nReason: \n\nIf you have any questions or would like to discuss this further, please contact us.\n\nBest regards,\nMhavis Medical & Diagnostic Center', 'text', 'Failed', 3, 3, '2025-10-23 13:35:53', NULL, '2025-10-23 13:16:12', NULL, '2025-10-23 05:16:12'),
(2, 'mklrms@gmail.com', 'Mikaela Ramos', 'Registration Approved — You Can Now Log In', 'Hi Mikaela Ramos, your registration has been approved by the clinic admin. You can now log in to your account and start using the system.', 'text', 'Failed', 3, 3, '2025-10-31 12:42:24', NULL, '2025-10-23 13:22:10', NULL, '2025-10-23 05:22:10'),
(3, 'hannahpauline1016@gmail.com', 'Hannah Placio', 'Registration Approved — You Can Now Log In', 'Hi Hannah Placio, your registration has been approved by the clinic admin. You can now log in to your account and start using the system.', 'text', 'Failed', 3, 3, '2025-10-31 13:20:41', NULL, '2025-10-23 13:35:51', NULL, '2025-10-23 05:35:51'),
(4, 'jeremieplacio0@gmail.com', 'Imee Placio', 'Registration Status - Mhavis Medical & Diagnostic Center', 'Dear Imee,\n\nThank you for your interest in registering with Mhavis Medical & Diagnostic Center.\n\nUnfortunately, we cannot approve your registration at this time.\n\nReason: \n\nIf you have any questions or would like to discuss this further, please contact us.\n\nBest regards,\nMhavis Medical & Diagnostic Center', 'text', 'Failed', 3, 3, '2025-10-31 13:21:56', NULL, '2025-10-31 12:42:22', NULL, '2025-10-31 04:42:22'),
(10, 'warlofu21@gmail.com', 'Josh Andrei Navarro', 'Registration Status - Mhavis Medical & Diagnostic Center', 'Dear Josh Andrei,\n\nThank you for your interest in registering with Mhavis Medical & Diagnostic Center.\n\nUnfortunately, we cannot approve your registration at this time.\n\nReason: \n\nIf you have any questions or would like to discuss this further, please contact us.\n\nBest regards,\nMhavis Medical & Diagnostic Center', 'text', 'Sent', 2, 3, '2025-11-01 16:42:33', NULL, '2025-11-01 16:29:17', '2025-11-01 16:51:23', '2025-11-01 08:29:17'),
(11, 'warlofu21@gmail.com', 'Josh Andrei Navarro', 'Registration Approved — You Can Now Log In', 'Hi Josh Andrei Navarro, your registration has been approved by the clinic admin. You can now log in to your account and start using the system.', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-01 16:58:46', '2025-11-01 16:58:49', '2025-11-01 08:58:46'),
(12, 'warlofu21@gmail.com', 'Josh Andrei Navarro', 'Registration Approved — Your Account Credentials', 'Dear Josh Andrei Navarro,\n\nCongratulations! Your registration with Mhavis Medical & Diagnostic Center has been approved.\n\nYour Account Credentials:\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nUsername: warlofu123\nTemporary Password: KxsZYbF8pXL#\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\nYou can now log in to your patient portal using these credentials.\n\nIMPORTANT: For your security, please change your password after your first login.\n\nTo access your account, visit: http://localhost/mhavis/mhavis/patient_login.php\n\nIf you did not request this registration, please contact us immediately.\n\nBest regards,\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-01 17:06:33', '2025-11-01 17:06:36', '2025-11-01 09:06:33'),
(13, 'warlofu21@gmail.com', 'Josh Andrei Navarro', 'Registration Approved — Your Account Credentials', 'Dear Josh Andrei Navarro,\n\nCongratulations! Your registration with Mhavis Medical & Diagnostic Center has been approved.\n\nYour Account Credentials:\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\nUsername: warlofu123\nPassword: lofu123456\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\nYou can now log in to your patient portal using these credentials.\n\nIMPORTANT: For your security, we recommend changing your password after your first login.\n\nTo access your account, visit: http://localhost/mhavis/mhavis/patient_login.php\n\nIf you did not request this registration, please contact us immediately.\n\nBest regards,\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-01 17:10:09', '2025-11-01 17:10:12', '2025-11-01 09:10:09'),
(14, 'warlofu21@gmail.com', 'Josh Andrei Navarro', 'Registration Approved - Your Account Credentials', 'Dear Josh Andrei Navarro,\n\nCongratulations! Your patient registration has been approved.\n\n===============================================\nYOUR ACCOUNT CREDENTIALS\n===============================================\n\n  Username: warlofu123\n  Password: lofu123456\n\n===============================================\n\nYou can now access your patient portal at:\nhttp://localhost/mhavis/mhavis/patient_login.php\n\nIMPORTANT SECURITY REMINDER:\nFor your security, we recommend changing your password after your first login.\n\n-----------------------------------------------\n\nIf you did not request this registration, please contact us immediately.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-01 17:12:36', '2025-11-01 17:12:40', '2025-11-01 09:12:36'),
(15, 'warlofu21@gmail.com', 'Josh Andrei Navarro', 'Registration Approved - Your Account Credentials', 'Dear Josh Andrei Navarro,\n\nCongratulations! Your patient registration has been approved.\n\n===============================================\nYOUR ACCOUNT CREDENTIALS\n===============================================\n\n  Username: warlofu123\n  Password: lofu123456\n\n===============================================\n\nYou can now access your patient portal at:\nhttp://localhost/mhavis/mhavis/patient_login.php\n\nIMPORTANT SECURITY REMINDER:\nFor your security, we recommend changing your password after your first login.\n\n-----------------------------------------------\n\nIf you did not request this registration, please contact us immediately.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-01 17:15:36', '2025-11-01 17:15:40', '2025-11-01 09:15:36'),
(16, 'warlofu21@gmail.com', 'Josh Andrei Navarro', 'Appointment Approved - Mhavis Medical Center', 'Dear Josh Andrei,\n\nYour appointment request has been approved!\n\nDoctor: Dr. Maria Doe\nDate: Dec 22, 2025\nTime: 10:00 AM\nReason: dasdas\n\nPlease arrive 15 minutes early for your appointment.\n\nIf you need to reschedule or cancel, please contact us at least 24 hours in advance.\n\nBest regards,\nMhavis Medical & Diagnostic Center', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-01 17:17:18', '2025-11-01 17:32:27', '2025-11-01 09:17:18'),
(17, 'warlofu21@gmail.com', 'Josh Andrei Navarro', '✓ Appointment Approved - Mhavis Medical Center', '\r\n                        <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\r\n                            <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\r\n                                <h2 style=\'margin: 0;\'>✓ Appointment Approved!</h2>\r\n                            </div>\r\n                            <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\r\n                                <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Josh Andrei Navarro</strong>,</p>\r\n                                \r\n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                    Great news! Your appointment request has been approved by our admin team.\r\n                                </p>\r\n                                \r\n                                <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\r\n                                    <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\r\n                                    <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Maria Doe</p>\r\n                                    <p style=\'margin: 10px 0;\'><strong>Date:</strong> Friday, December 5, 2025</p>\r\n                                    <p style=\'margin: 10px 0;\'><strong>Time:</strong> 9:00 AM</p>\r\n                                    <p style=\'margin: 10px 0;\'><strong>Reason:</strong> weqweqwe</p>\r\n                                </div>\r\n                                \r\n                                <div style=\'background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;\'>\r\n                                    <p style=\'margin: 0; font-size: 14px; color: #856404;\'>\r\n                                        ⏰ <strong>Important:</strong> Please arrive 15 minutes early for your appointment to complete any necessary paperwork.\r\n                                    </p>\r\n                                </div>\r\n                                \r\n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                    If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.\r\n                                </p>\r\n                                \r\n                                <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\r\n                                \r\n                                <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\r\n                                    Best regards,<br>\r\n                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>\r\n                                    Healthcare Team\r\n                                </p>\r\n                            </div>\r\n                        </div>\r\n                        ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-11-01 17:23:33', '2025-11-01 17:32:31', '2025-11-01 09:23:33'),
(18, 'warlofu21@gmail.com', 'Josh Andrei Navarro', '✓ Appointment Approved - Mhavis Medical Center', '\r\n                        <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\r\n                            <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\r\n                                <h2 style=\'margin: 0;\'>✓ Appointment Approved!</h2>\r\n                            </div>\r\n                            <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\r\n                                <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Josh Andrei Navarro</strong>,</p>\r\n                                \r\n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                    Great news! Your appointment request has been approved by our admin team.\r\n                                </p>\r\n                                \r\n                                <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\r\n                                    <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\r\n                                    <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Simeon Daez</p>\r\n                                    <p style=\'margin: 10px 0;\'><strong>Date:</strong> Monday, December 1, 2025</p>\r\n                                    <p style=\'margin: 10px 0;\'><strong>Time:</strong> 10:00 AM</p>\r\n                                    <p style=\'margin: 10px 0;\'><strong>Reason:</strong> sdsdfsfsdf</p>\r\n                                </div>\r\n                                \r\n                                <div style=\'background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;\'>\r\n                                    <p style=\'margin: 0; font-size: 14px; color: #856404;\'>\r\n                                        ⏰ <strong>Important:</strong> Please arrive 15 minutes early for your appointment to complete any necessary paperwork.\r\n                                    </p>\r\n                                </div>\r\n                                \r\n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                    If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.\r\n                                </p>\r\n                                \r\n                                <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\r\n                                \r\n                                <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\r\n                                    Best regards,<br>\r\n                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>\r\n                                    Healthcare Team\r\n                                </p>\r\n                            </div>\r\n                        </div>\r\n                        ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-11-01 17:32:24', '2025-11-01 17:32:34', '2025-11-01 09:32:24'),
(19, 'ramos112115mikaela@gmail.com', 'Mikaela Ramos', 'Registration Approved - Your Account Credentials', 'Dear Mikaela Ramos,\n\nCongratulations! Your patient registration has been approved.\n\n===============================================\nYOUR ACCOUNT CREDENTIALS\n===============================================\n\n  Username: mklrms\n  Password: mikaela123\n\n===============================================\n\nYou can now access your patient portal at:\nhttp://localhost/mhavisLatest/mhavis/mhavis/patient_login.php\n\nIMPORTANT SECURITY REMINDER:\nFor your security, we recommend changing your password after your first login.\n\n-----------------------------------------------\n\nIf you did not request this registration, please contact us immediately.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-05 06:50:32', '2025-11-05 06:50:36', '2025-11-04 22:50:32'),
(20, 'ramos112115mikaela@gmail.com', 'Mikaela Ramos', '✓ Appointment Approved - Mhavis Medical Center', '\r\n                        <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\r\n                            <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\r\n                                <h2 style=\'margin: 0;\'>✓ Appointment Approved!</h2>\r\n                            </div>\r\n                            <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\r\n                                <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Mikaela Ramos</strong>,</p>\r\n                                \r\n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                    Great news! Your appointment request has been approved by our admin team.\r\n                                </p>\r\n                                \r\n                                <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\r\n                                    <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\r\n                                    <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Maria Doe</p>\r\n                                    <p style=\'margin: 10px 0;\'><strong>Date:</strong> Monday, November 10, 2025</p>\r\n                                    <p style=\'margin: 10px 0;\'><strong>Time:</strong> 11:00 AM</p>\r\n                                    <p style=\'margin: 10px 0;\'><strong>Reason:</strong> Check up </p>\r\n                                </div>\r\n                                \r\n                                <div style=\'background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;\'>\r\n                                    <p style=\'margin: 0; font-size: 14px; color: #856404;\'>\r\n                                        ⏰ <strong>Important:</strong> Please arrive 15 minutes early for your appointment to complete any necessary paperwork.\r\n                                    </p>\r\n                                </div>\r\n                                \r\n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                    If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.\r\n                                </p>\r\n                                \r\n                                <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\r\n                                \r\n                                <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\r\n                                    Best regards,<br>\r\n                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>\r\n                                    Healthcare Team\r\n                                </p>\r\n                            </div>\r\n                        </div>\r\n                        ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-11-05 08:55:17', '2025-11-05 08:55:21', '2025-11-05 00:55:17'),
(21, 'hannahpauline1016@gmail.com', 'Hannah Placio', 'Registration Approved - Your Account Credentials', 'Dear Hannah Placio,\n\nCongratulations! Your patient registration has been approved.\n\n===============================================\nYOUR ACCOUNT CREDENTIALS\n===============================================\n\n  Username: hnnhpwln\n  Password: hannah123\n\n===============================================\n\nYou can now access your patient portal at:\nhttp://localhost/mhavisLatest/mhavis/mhavis/patient_login.php\n\nIMPORTANT SECURITY REMINDER:\nFor your security, we recommend changing your password after your first login.\n\n-----------------------------------------------\n\nIf you did not request this registration, please contact us immediately.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-06 13:19:17', '2025-11-06 13:19:22', '2025-11-06 05:19:17'),
(23, 'eddedward45@gmail.com', 'Edward Lalu', 'Registration Status Update - Mhavis Medical & Diagnostic Center', 'Dear Edward Lalu,\n\nThank you for your interest in registering with Mhavis Medical & Diagnostic Center.\n\n===============================================\nREGISTRATION STATUS: NOT APPROVED\n===============================================\n\nUnfortunately, we are unable to approve your registration at this time.\n\nReason:\nYou can now log in to the Mhavis Patient Portal and make an appointment anytime, anywhere!\n\n-----------------------------------------------\n\nIf you have any questions or would like to discuss this further, please contact us directly.\n\nWe appreciate your understanding.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-16 17:53:37', '2025-11-16 17:53:46', '2025-11-16 09:53:37'),
(27, 'pjsbandal9876@gmail.com', 'Jade Bandal', 'Registration Approved - Your Account Credentials', 'Dear Jade Bandal,\n\nCongratulations! Your patient registration has been approved.\n\n===============================================\nYOUR ACCOUNT CREDENTIALS\n===============================================\n\n  Patient ID: PT-2025-00015\n  Password: WTVzyumAKJS9\n\n===============================================\n\nYou can now access your patient portal at:\nhttp://localhost/mhavis/mhavis/patient_login.php\n\nIMPORTANT SECURITY REMINDER:\nPlease change your password immediately after your first login.\n\n-----------------------------------------------\n\nIf you did not request this registration, please contact us immediately.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-16 20:24:02', '2025-11-16 20:24:06', '2025-11-16 12:24:02'),
(28, 'pjsbandal9876@gmail.com', 'Jade Bandal', 'Registration Approved - Your Account Credentials', 'Dear Jade Bandal,\n\nCongratulations! Your patient registration has been approved.\n\n===============================================\nYOUR ACCOUNT CREDENTIALS\n===============================================\n\n  Patient ID: PT-2025-00015\n  Password: pqBAChxn2sZX\n\n===============================================\n\nYou can now access your patient portal at:\nhttp://localhost/mhavis/mhavis/patient_login.php\n\nIMPORTANT SECURITY REMINDER:\nPlease change your password immediately after your first login.\n\n-----------------------------------------------\n\nIf you did not request this registration, please contact us immediately.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-16 20:26:50', '2025-11-16 20:26:53', '2025-11-16 12:26:50'),
(29, 'pjsbandal9876@gmail.com', 'Jade Bandal', '✓ Appointment Approved - Mhavis Medical Center', '\n                        <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\n                            <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\n                                <h2 style=\'margin: 0;\'>✓ Appointment Approved!</h2>\n                            </div>\n                            <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\n                                <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade Bandal</strong>,</p>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    Great news! Your appointment request has been approved by our admin team.\n                                </p>\n                                \n                                <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\n                                    <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\n                                    <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Maria Doe</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Date:</strong> Monday, November 17, 2025</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Time:</strong> 11:00 AM</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Reason:</strong> asasasad</p>\n                                </div>\n                                \n                                <div style=\'background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;\'>\n                                    <p style=\'margin: 0; font-size: 14px; color: #856404;\'>\n                                        ⏰ <strong>Important:</strong> Please arrive 15 minutes early for your appointment to complete any necessary paperwork.\n                                    </p>\n                                </div>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.\n                                </p>\n                                \n                                <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\n                                \n                                <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\n                                    Best regards,<br>\n                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>\n                                    Healthcare Team\n                                </p>\n                            </div>\n                        </div>\n                        ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-11-16 20:32:48', '2025-11-16 20:32:51', '2025-11-16 12:32:48'),
(30, 'pjsbandal9876@gmail.com', 'Jade Bandal', '✓ Appointment Approved - Mhavis Medical Center', '\n                        <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\n                            <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\n                                <h2 style=\'margin: 0;\'>✓ Appointment Approved!</h2>\n                            </div>\n                            <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\n                                <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade Bandal</strong>,</p>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    Great news! Your appointment request has been approved by our admin team.\n                                </p>\n                                \n                                <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\n                                    <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\n                                    <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Maria Doe</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Date:</strong> Monday, November 17, 2025</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Time:</strong> 11:30 AM</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Reason:</strong> sample reason</p>\n                                </div>\n                                \n                                <div style=\'background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;\'>\n                                    <p style=\'margin: 0; font-size: 14px; color: #856404;\'>\n                                        ⏰ <strong>Important:</strong> Please arrive 15 minutes early for your appointment to complete any necessary paperwork.\n                                    </p>\n                                </div>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.\n                                </p>\n                                \n                                <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\n                                \n                                <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\n                                    Best regards,<br>\n                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>\n                                    Healthcare Team\n                                </p>\n                            </div>\n                        </div>\n                        ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-11-16 20:43:47', '2025-11-16 20:43:51', '2025-11-16 12:43:47'),
(31, 'pjsbandal9876@gmail.com', 'Jade Bandal', 'Registration Approved - Your Account Credentials', 'Dear Jade Bandal,\n\nCongratulations! Your patient registration has been approved.\n\n===============================================\nYOUR ACCOUNT CREDENTIALS\n===============================================\n\n  Patient ID: PT-2025-00015\n  Password: 4hzOBRTUvOyO\n\n===============================================\n\nYou can now access your patient portal at:\nhttp://localhost/mhavis/mhavis/patient_login.php\n\nIMPORTANT SECURITY REMINDER:\nPlease change your password immediately after your first login.\n\n-----------------------------------------------\n\nIf you did not request this registration, please contact us immediately.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-25 08:32:49', '2025-11-25 08:32:53', '2025-11-25 00:32:49'),
(32, 'pjsbandal9876@gmail.com', 'Jade Bandal', '✓ Appointment Approved - Mhavis Medical Center', '\n                        <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\n                            <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\n                                <h2 style=\'margin: 0;\'>✓ Appointment Approved!</h2>\n                            </div>\n                            <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\n                                <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade Bandal</strong>,</p>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    Great news! Your appointment request has been approved by our admin team.\n                                </p>\n                                \n                                <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\n                                    <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\n                                    <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Maria Doe</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Date:</strong> Wednesday, November 26, 2025</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Time:</strong> 2:00 PM</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Reason:</strong> sample</p>\n                                </div>\n                                \n                                <div style=\'background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;\'>\n                                    <p style=\'margin: 0; font-size: 14px; color: #856404;\'>\n                                        ⏰ <strong>Important:</strong> Please arrive 15 minutes early for your appointment to complete any necessary paperwork.\n                                    </p>\n                                </div>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.\n                                </p>\n                                \n                                <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\n                                \n                                <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\n                                    Best regards,<br>\n                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>\n                                    Healthcare Team\n                                </p>\n                            </div>\n                        </div>\n                        ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-11-25 08:36:38', '2025-11-25 08:36:41', '2025-11-25 00:36:38'),
(33, 'pjsbandal9876@gmail.com', 'Jade Bandal', 'Appointment Request Status Update - Mhavis Medical Center', '\n                        <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\n                            <div style=\'background-color: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\n                                <h2 style=\'margin: 0;\'>Appointment Request Status Update</h2>\n                            </div>\n                            <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\n                                <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade Bandal</strong>,</p>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    Thank you for your appointment request with Dr. Simeon Daez.\n                                </p>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    Unfortunately, we are unable to accommodate your requested appointment at this time.\n                                </p>\n                                \n                                \n                                \n                                <div style=\'background-color: #d1ecf1; padding: 15px; margin: 20px 0; border-left: 4px solid #0dcaf0; border-radius: 4px;\'>\n                                    <p style=\'margin: 0; font-size: 14px; color: #055160;\'>\n                                        💡 <strong>What\'s Next?</strong><br>\n                                        You can submit a new appointment request with different date/time preferences, or contact us directly for assistance in finding an available slot.\n                                    </p>\n                                </div>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    We apologize for any inconvenience and look forward to serving you soon.\n                                </p>\n                                \n                                <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\n                                \n                                <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\n                                    Best regards,<br>\n                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>\n                                    Healthcare Team\n                                </p>\n                            </div>\n                        </div>\n                        ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-11-25 08:37:20', '2025-11-25 08:37:24', '2025-11-25 00:37:20'),
(34, 'ezekieltallano@gmail.com', 'Allen Candelaria', 'Registration Approved - Your Account Credentials', 'Dear Allen Candelaria,\n\nCongratulations! Your patient registration has been approved.\n\n===============================================\nYOUR ACCOUNT CREDENTIALS\n===============================================\n\n  Patient ID: PT-2025-00017\n  Password: Ru1Tt9jEj9ta\n\n===============================================\n\nYou can now access your patient portal at:\nhttp://localhost/mhavis/mhavis/patient_login.php\n\nIMPORTANT SECURITY REMINDER:\nPlease change your password immediately after your first login.\n\n-----------------------------------------------\n\nIf you did not request this registration, please contact us immediately.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-27 01:34:07', '2025-11-27 01:34:11', '2025-11-26 17:34:07'),
(35, 'pjsbandal9876@gmail.com', 'Jade Bandal', '✓ Appointment Approved - Mhavis Medical Center', '\n                        <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\n                            <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\n                                <h2 style=\'margin: 0;\'>✓ Appointment Approved!</h2>\n                            </div>\n                            <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\n                                <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade Bandal</strong>,</p>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    Great news! Your appointment request has been approved by our admin team.\n                                </p>\n                                \n                                <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\n                                    <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\n                                    <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Maria Doe</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Date:</strong> Monday, December 1, 2025</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Time:</strong> 10:00 AM</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Reason:</strong> sample</p>\n                                </div>\n                                \n                                <div style=\'background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;\'>\n                                    <p style=\'margin: 0; font-size: 14px; color: #856404;\'>\n                                        ⏰ <strong>Important:</strong> Please arrive 15 minutes early for your appointment to complete any necessary paperwork.\n                                    </p>\n                                </div>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.\n                                </p>\n                                \n                                <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\n                                \n                                <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\n                                    Best regards,<br>\n                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>\n                                    Healthcare Team\n                                </p>\n                            </div>\n                        </div>\n                        ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-11-27 02:11:38', '2025-11-27 02:11:41', '2025-11-26 18:11:38'),
(36, 'eddedward45@gmail.com', 'Eduardo Lalu', 'Registration Status Update - Mhavis Medical & Diagnostic Center', 'Dear Eduardo Lalu,\n\nThank you for your interest in registering with Mhavis Medical & Diagnostic Center.\n\n===============================================\nREGISTRATION STATUS: NOT APPROVED\n===============================================\n\nUnfortunately, we are unable to approve your registration at this time.\n\nReason:\nYou can now log in to the Mhavis Patient Portal and make an appointment anytime, anywhere!\n\n-----------------------------------------------\n\nIf you have any questions or would like to discuss this further, please contact us directly.\n\nWe appreciate your understanding.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-27 11:27:27', '2025-11-27 11:27:32', '2025-11-27 03:27:27'),
(37, 'ceraphina.keyl@gmail.com', 'Eduardo Lalu', 'Registration Approved - Your Account Credentials', 'Dear Eduardo Lalu,\n\nCongratulations! Your patient registration has been approved.\n\n===============================================\nYOUR ACCOUNT CREDENTIALS\n===============================================\n\n  Patient ID: PT-2025-00018\n  Password: svakOCbE2L98\n\n===============================================\n\nYou can now access your patient portal at:\nhttp://localhost/mhavis27/mhavis27/mhavis/mhavis/patient_login.php\n\nIMPORTANT SECURITY REMINDER:\nPlease change your password immediately after your first login.\n\n-----------------------------------------------\n\nIf you did not request this registration, please contact us immediately.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-27 11:44:34', '2025-11-27 11:44:40', '2025-11-27 03:44:34'),
(38, 'ceraphina.keyl@gmail.com', 'Eduardo Lalu', '✓ Appointment Approved - Mhavis Medical Center', '\n                        <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\n                            <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\n                                <h2 style=\'margin: 0;\'>✓ Appointment Approved!</h2>\n                            </div>\n                            <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\n                                <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Eduardo Lalu</strong>,</p>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    Great news! Your appointment request has been approved by our admin team.\n                                </p>\n                                \n                                <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\n                                    <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\n                                    <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Simeon Daez</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Date:</strong> Friday, November 28, 2025</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Time:</strong> 11:30 AM</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Reason:</strong> masakit tyan</p>\n                                </div>\n                                \n                                <div style=\'background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;\'>\n                                    <p style=\'margin: 0; font-size: 14px; color: #856404;\'>\n                                        ⏰ <strong>Important:</strong> Please arrive 15 minutes early for your appointment to complete any necessary paperwork.\n                                    </p>\n                                </div>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.\n                                </p>\n                                \n                                <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\n                                \n                                <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\n                                    Best regards,<br>\n                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>\n                                    Healthcare Team\n                                </p>\n                            </div>\n                        </div>\n                        ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-11-27 11:54:03', '2025-11-27 11:54:09', '2025-11-27 03:54:03'),
(39, 'gingerieclover@gmail.com', 'Maileen Nadulas', 'Registration Status Update - Mhavis Medical & Diagnostic Center', 'Dear Maileen Nadulas,\n\nThank you for your interest in registering with Mhavis Medical & Diagnostic Center.\n\n===============================================\nREGISTRATION STATUS: NOT APPROVED\n===============================================\n\nUnfortunately, we are unable to approve your registration at this time.\n\nIf you have any questions or would like to discuss this further, please contact us directly.\n\nWe appreciate your understanding.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-29 11:54:18', '2025-11-29 11:54:21', '2025-11-29 03:54:18'),
(40, 'bandal.princessjades@gmail.com', 'Jade bandal', 'Registration Approved - Your Account Credentials', 'Dear Jade bandal,\n\nCongratulations! Your patient registration has been approved.\n\n===============================================\nYOUR ACCOUNT CREDENTIALS\n===============================================\n\n  Patient ID: PT-2025-00020\n  Password: 2RoQUrH5ZgIW\n\n===============================================\n\nYou can now access your patient portal at:\nhttp://localhost/mhavis/mhavis/patient_login.php\n\nIMPORTANT SECURITY REMINDER:\nPlease change your password immediately after your first login.\n\n-----------------------------------------------\n\nIf you did not request this registration, please contact us immediately.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-11-29 12:15:25', '2025-11-29 12:15:28', '2025-11-29 04:15:25');
INSERT INTO `email_queue` (`id`, `to_email`, `to_name`, `subject`, `body`, `body_type`, `status`, `attempts`, `max_attempts`, `last_attempt`, `error_message`, `scheduled_at`, `sent_at`, `created_at`) VALUES
(41, 'ramos112115mikaela@gmail.com', 'Mikaela Ramos', '✓ Appointment Approved - Mhavis Medical Center', '\n                        <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\n                            <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\n                                <h2 style=\'margin: 0;\'>✓ Appointment Approved!</h2>\n                            </div>\n                            <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\n                                <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Mikaela Ramos</strong>,</p>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    Great news! Your appointment request has been approved by our admin team.\n                                </p>\n                                \n                                <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\n                                    <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\n                                    <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Ma. Maria Doe</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Date:</strong> Monday, December 8, 2025</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Time:</strong> 9:30 AM</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Reason:</strong> sdtyul</p>\n                                </div>\n                                \n                                <div style=\'background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;\'>\n                                    <p style=\'margin: 0; font-size: 14px; color: #856404;\'>\n                                        ⏰ <strong>Important:</strong> Please arrive 15 minutes early for your appointment to complete any necessary paperwork.\n                                    </p>\n                                </div>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.\n                                </p>\n                                \n                                <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\n                                \n                                <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\n                                    Best regards,<br>\n                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>\n                                    Healthcare Team\n                                </p>\n                            </div>\n                        </div>\n                        ', 'html', 'Sent', 2, 3, '2025-12-04 14:06:09', 'Failed to send email to ramos112115mikaela@gmail.com', '2025-12-02 16:50:02', '2025-12-07 00:17:10', '2025-12-02 08:50:02'),
(42, 'bandal.princessjades@gmail.com', 'Jade bandal', 'Registration Approved - Your Account Credentials', 'Dear Jade bandal,\n\nCongratulations! Your patient registration has been approved.\n\n===============================================\nYOUR ACCOUNT CREDENTIALS\n===============================================\n\n  Patient ID: PT-2025-00023\n  Password: T6We7qlaKZwb\n\n===============================================\n\nYou can now access your patient portal at:\nhttp://localhost/mhavis40/mhavis40/mhavis/mhavis/patient_login.php\n\nIMPORTANT SECURITY REMINDER:\nPlease change your password immediately after your first login.\n\n-----------------------------------------------\n\nIf you did not request this registration, please contact us immediately.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 1, 3, '2025-12-04 14:06:09', 'Failed to send email to bandal.princessjades@gmail.com', '2025-12-04 14:06:09', '2025-12-07 00:17:13', '2025-12-04 06:06:09'),
(43, 'pjsbandal9876@gmail.com', 'Jade Bandal', '✓ Appointment Approved - Mhavis Medical Center', '\n                        <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\n                            <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\n                                <h2 style=\'margin: 0;\'>✓ Appointment Approved!</h2>\n                            </div>\n                            <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\n                                <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade Bandal</strong>,</p>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    Great news! Your appointment request has been approved by our admin team.\n                                </p>\n                                \n                                <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\n                                    <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\n                                    <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Ma. Maria Doe</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Date:</strong> Monday, December 1, 2025</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Time:</strong> 11:00 AM</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Reason:</strong> sample</p>\n                                </div>\n                                \n                                <div style=\'background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;\'>\n                                    <p style=\'margin: 0; font-size: 14px; color: #856404;\'>\n                                        ⏰ <strong>Important:</strong> Please arrive 15 minutes early for your appointment to complete any necessary paperwork.\n                                    </p>\n                                </div>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.\n                                </p>\n                                \n                                <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\n                                \n                                <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\n                                    Best regards,<br>\n                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>\n                                    Healthcare Team\n                                </p>\n                            </div>\n                        </div>\n                        ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-12-07 00:17:05', '2025-12-07 00:17:16', '2025-12-06 16:17:05'),
(44, 'pjsbandal9876@gmail.com', 'Jade Bandal', '✓ Appointment Approved - Mhavis Medical Center', '\n                        <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\n                            <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\n                                <h2 style=\'margin: 0;\'>✓ Appointment Approved!</h2>\n                            </div>\n                            <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\n                                <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade Bandal</strong>,</p>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    Great news! Your appointment request has been approved by our admin team.\n                                </p>\n                                \n                                <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\n                                    <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\n                                    <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Ma. Maria Doe</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Date:</strong> Wednesday, December 10, 2025</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Time:</strong> 3:30 PM</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Reason:</strong> sasasas</p>\n                                </div>\n                                \n                                <div style=\'background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;\'>\n                                    <p style=\'margin: 0; font-size: 14px; color: #856404;\'>\n                                        ⏰ <strong>Important:</strong> Please arrive 15 minutes early for your appointment to complete any necessary paperwork.\n                                    </p>\n                                </div>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.\n                                </p>\n                                \n                                <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\n                                \n                                <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\n                                    Best regards,<br>\n                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>\n                                    Healthcare Team\n                                </p>\n                            </div>\n                        </div>\n                        ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-12-07 00:27:07', '2025-12-07 00:27:11', '2025-12-06 16:27:07'),
(45, 'hannahpauline1018@gmail.com', 'Mikaelaa Ramosa', 'Registration Status Update - Mhavis Medical & Diagnostic Center', 'Dear Mikaelaa Ramosa,\n\nThank you for your interest in registering with Mhavis Medical & Diagnostic Center.\n\n===============================================\nREGISTRATION STATUS: NOT APPROVED\n===============================================\n\nUnfortunately, we are unable to approve your registration at this time.\n\nIf you have any questions or would like to discuss this further, please contact us directly.\n\nWe appreciate your understanding.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-12-07 00:33:41', '2025-12-07 00:33:44', '2025-12-06 16:33:41'),
(46, 'pjsbandal9876@gmail.com', 'Ma. Maria Nadulas', 'Registration Status Update - Mhavis Medical & Diagnostic Center', 'Dear Ma. Maria Nadulas,\n\nThank you for your interest in registering with Mhavis Medical & Diagnostic Center.\n\n===============================================\nREGISTRATION STATUS: NOT APPROVED\n===============================================\n\nUnfortunately, we are unable to approve your registration at this time.\n\nIf you have any questions or would like to discuss this further, please contact us directly.\n\nWe appreciate your understanding.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-12-07 00:33:54', '2025-12-07 00:34:00', '2025-12-06 16:33:54'),
(47, 'pjsbandal9876@gmail.com', 'Ma. Maria Daez', 'Registration Status Update - Mhavis Medical & Diagnostic Center', 'Dear Ma. Maria Daez,\n\nThank you for your interest in registering with Mhavis Medical & Diagnostic Center.\n\n===============================================\nREGISTRATION STATUS: NOT APPROVED\n===============================================\n\nUnfortunately, we are unable to approve your registration at this time.\n\nReason:\nYou can now log in to the Mhavis Patient Portal and make an appointment anytime, anywhere!\n\n-----------------------------------------------\n\nIf you have any questions or would like to discuss this further, please contact us directly.\n\nWe appreciate your understanding.\n\nBest regards,\n\nMhavis Medical & Diagnostic Center\nHealthcare Team', 'text', 'Sent', 0, 3, NULL, NULL, '2025-12-07 00:40:06', '2025-12-07 00:40:09', '2025-12-06 16:40:06'),
(48, 'pjsbandal9876@gmail.com', 'Jade bandal', 'Registration Status Update - Mhavis Medical & Diagnostic Center', '\n                    <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\n                        <div style=\'background-color: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\n                            <h2 style=\'margin: 0;\'>Registration Status Update</h2>\n                        </div>\n                        <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\n                            <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade bandal</strong>,</p>\n                            \n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                Thank you for your interest in registering with Mhavis Medical & Diagnostic Center.\n                            </p>\n                            \n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                Unfortunately, we are unable to approve your registration at this time.\n                            </p>\n                            \n                            \n                            \n                            <div style=\'background-color: #d1ecf1; padding: 15px; margin: 20px 0; border-left: 4px solid #0dcaf0; border-radius: 4px;\'>\n                                <p style=\'margin: 0; font-size: 14px; color: #055160;\'>\n                                    💡 <strong>What\'s Next?</strong><br>\n                                    If you have any questions or would like to discuss this further, please contact us directly. We\'re here to help and can assist you with the registration process.\n                                </p>\n                            </div>\n                            \n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                We appreciate your understanding.\n                            </p>\n                            \n                            <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\n                            \n                            <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\n                                Best regards,<br>\n                                <strong>Mhavis Medical & Diagnostic Center</strong><br>\n                                Healthcare Team\n                            </p>\n                        </div>\n                    </div>\n                    ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-12-07 00:42:26', '2025-12-07 00:42:29', '2025-12-06 16:42:26'),
(49, 'bandal.princessjades@gmail.com', 'Ma. Maria Nadulas', '✓ Registration Approved - Your Account Credentials', '\n                        <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\n                            <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\n                                <h2 style=\'margin: 0;\'>✓ Registration Approved!</h2>\n                            </div>\n                            <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\n                                <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Ma. Maria Nadulas</strong>,</p>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    Great news! Your patient registration has been approved by our admin team.\n                                </p>\n                                \n                                <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\n                                    <h3 style=\'margin-top: 0; color: #333;\'>🔑 Account Credentials</h3>\n                                    <p style=\'margin: 10px 0;\'><strong>Patient ID:</strong> PT-2025-00023</p>\n                                    <p style=\'margin: 10px 0;\'><strong>Password:</strong> E5W2FLsFiMQP</p>\n                                </div>\n                                \n                                <div style=\'background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;\'>\n                                    <p style=\'margin: 0; font-size: 14px; color: #856404;\'>\n                                        🔐 <strong>Important:</strong> Please change your password immediately after your first login for security purposes.\n                                    </p>\n                                </div>\n                                \n                                <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                    You can now access your patient portal at: <a href=\'http://localhost/mhavis/mhavis/patient_login.php\' style=\'color: #4CAF50;\'>http://localhost/mhavis/mhavis/patient_login.php</a>\n                                </p>\n                                \n                                <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\n                                \n                                <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\n                                    Best regards,<br>\n                                    <strong>Mhavis Medical & Diagnostic Center</strong><br>\n                                    Healthcare Team\n                                </p>\n                            </div>\n                        </div>\n                        ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-12-07 01:14:57', '2025-12-07 01:15:01', '2025-12-06 17:14:57'),
(50, 'pjsbandal9876@gmail.com', 'Jade Bandal', 'Appointment Status Updated - Mhavis Medical Center', '\r\n                    <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\r\n                        <div style=\'background-color: #f44336; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\r\n                            <h2 style=\'margin: 0;\'>❌ Appointment Status Updated</h2>\r\n                        </div>\r\n                        <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\r\n                            <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade Bandal</strong>,</p>\r\n                            \r\n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                Your appointment status has been updated by our administration team.\r\n                            </p>\r\n                            \r\n                            <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #f44336; border-radius: 4px;\'>\r\n                                <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\r\n                                <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Ma. Maria Doe</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Date:</strong> Monday, December 15, 2025</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Time:</strong> 11:00 AM</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Status:</strong> <span style=\'color: #f44336; font-weight: bold;\'>Cancelled</span></p>\r\n                            </div>\r\n                            \r\n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                If you have any questions or concerns about this status change, please contact us.\r\n                            </p>\r\n                            \r\n                            <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\r\n                            \r\n                            <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\r\n                                Best regards,<br>\r\n                                <strong>Mhavis Medical & Diagnostic Center</strong><br>\r\n                                Healthcare Team\r\n                            </p>\r\n                        </div>\r\n                    </div>\r\n                    ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-12-15 00:23:47', '2025-12-15 00:23:50', '2025-12-14 16:23:47'),
(51, 'pjsbandal9876@gmail.com', 'Jade Bandal', 'Appointment Scheduled - Mhavis Medical Center', '\n                            <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\n                                <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\n                                    <h2 style=\'margin: 0;\'>✅ Appointment Scheduled!</h2>\n                                </div>\n                                <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\n                                    <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade Bandal</strong>,</p>\n                                    \n                                    <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                        Your appointment has been successfully scheduled by our administration team.\n                                    </p>\n                                    \n                                    <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\n                                        <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\n                                        <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Ma. Maria Doe</p>\n                                        <p style=\'margin: 10px 0;\'><strong>Date:</strong> Monday, December 15, 2025</p>\n                                        <p style=\'margin: 10px 0;\'><strong>Time:</strong> 9:00 AM</p>\n                                        <p style=\'margin: 10px 0;\'><strong>Reason:</strong> Consultation</p>\n                                    </div>\n                                    \n                                    <div style=\'background-color: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;\'>\n                                        <p style=\'margin: 0; font-size: 14px; color: #856404;\'>\n                                            ⏰ <strong>Important:</strong> Please arrive 15 minutes early for your appointment to complete any necessary paperwork.\n                                        </p>\n                                    </div>\n                                    \n                                    <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\n                                        If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.\n                                    </p>\n                                    \n                                    <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\n                                    \n                                    <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\n                                        Best regards,<br>\n                                        <strong>Mhavis Medical & Diagnostic Center</strong><br>\n                                        Healthcare Team\n                                    </p>\n                                </div>\n                            </div>\n                            ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-12-15 00:26:22', '2025-12-15 00:26:25', '2025-12-14 16:26:22'),
(52, 'pjsbandal9876@gmail.com', 'Jade Bandal', 'Appointment Status Updated - Mhavis Medical Center', '\r\n                    <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\r\n                        <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\r\n                            <h2 style=\'margin: 0;\'>✅ Appointment Status Updated</h2>\r\n                        </div>\r\n                        <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\r\n                            <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade Bandal</strong>,</p>\r\n                            \r\n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                Your appointment status has been updated by our administration team.\r\n                            </p>\r\n                            \r\n                            <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\r\n                                <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\r\n                                <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Ma. Maria Doe</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Date:</strong> Monday, December 15, 2025</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Time:</strong> 9:00 AM</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Status:</strong> <span style=\'color: #4CAF50; font-weight: bold;\'>Ongoing</span></p>\r\n                            </div>\r\n                            \r\n                            \r\n                            \r\n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                If you have any questions or concerns about this status change, please contact us.\r\n                            </p>\r\n                            \r\n                            <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\r\n                            \r\n                            <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\r\n                                Best regards,<br>\r\n                                <strong>Mhavis Medical & Diagnostic Center</strong><br>\r\n                                Healthcare Team\r\n                            </p>\r\n                        </div>\r\n                    </div>\r\n                    ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-12-15 00:36:32', '2025-12-15 00:36:35', '2025-12-14 16:36:32'),
(53, 'pjsbandal9876@gmail.com', 'Jade Bandal', 'Appointment Status Updated - Mhavis Medical Center', '\r\n                    <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\r\n                        <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\r\n                            <h2 style=\'margin: 0;\'>✅ Appointment Status Updated</h2>\r\n                        </div>\r\n                        <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\r\n                            <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade Bandal</strong>,</p>\r\n                            \r\n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                Your appointment status has been updated by our administration team.\r\n                            </p>\r\n                            \r\n                            <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\r\n                                <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\r\n                                <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Ma. Maria Doe</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Date:</strong> Monday, December 15, 2025</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Time:</strong> 9:00 AM</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Status:</strong> <span style=\'color: #4CAF50; font-weight: bold;\'>Scheduled</span></p>\r\n                            </div>\r\n                            \r\n                            \r\n                            \r\n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                If you have any questions or concerns about this status change, please contact us.\r\n                            </p>\r\n                            \r\n                            <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\r\n                            \r\n                            <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\r\n                                Best regards,<br>\r\n                                <strong>Mhavis Medical & Diagnostic Center</strong><br>\r\n                                Healthcare Team\r\n                            </p>\r\n                        </div>\r\n                    </div>\r\n                    ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-12-15 01:43:41', '2025-12-15 01:43:45', '2025-12-14 17:43:41'),
(54, 'pjsbandal9876@gmail.com', 'Jade Bandal', 'Appointment Status Updated - Mhavis Medical Center', '\r\n                    <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\r\n                        <div style=\'background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\r\n                            <h2 style=\'margin: 0;\'>✅ Appointment Status Updated</h2>\r\n                        </div>\r\n                        <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\r\n                            <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade Bandal</strong>,</p>\r\n                            \r\n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                Your appointment status has been updated by our administration team.\r\n                            </p>\r\n                            \r\n                            <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #4CAF50; border-radius: 4px;\'>\r\n                                <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\r\n                                <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Ma. Maria Doe</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Date:</strong> Monday, December 15, 2025</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Time:</strong> 9:00 AM</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Status:</strong> <span style=\'color: #4CAF50; font-weight: bold;\'>Ongoing</span></p>\r\n                            </div>\r\n                            \r\n                            \r\n                            \r\n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                If you have any questions or concerns about this status change, please contact us.\r\n                            </p>\r\n                            \r\n                            <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\r\n                            \r\n                            <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\r\n                                Best regards,<br>\r\n                                <strong>Mhavis Medical & Diagnostic Center</strong><br>\r\n                                Healthcare Team\r\n                            </p>\r\n                        </div>\r\n                    </div>\r\n                    ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-12-15 01:43:57', '2025-12-15 01:44:00', '2025-12-14 17:43:57'),
(55, 'pjsbandal9876@gmail.com', 'Jade Bandal', 'Appointment Status Updated - Mhavis Medical Center', '\r\n                    <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\r\n                        <div style=\'background-color: #2196F3; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\r\n                            <h2 style=\'margin: 0;\'>✓ Appointment Status Updated</h2>\r\n                        </div>\r\n                        <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\r\n                            <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade Bandal</strong>,</p>\r\n                            \r\n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                Your appointment status has been updated by our administration team.\r\n                            </p>\r\n                            \r\n                            <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #2196F3; border-radius: 4px;\'>\r\n                                <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\r\n                                <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Ma. Maria Doe</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Date:</strong> Monday, December 15, 2025</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Time:</strong> 9:00 AM</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Status:</strong> <span style=\'color: #2196F3; font-weight: bold;\'>Settled</span></p>\r\n                            </div>\r\n                            \r\n                            \r\n                            \r\n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                If you have any questions or concerns about this status change, please contact us.\r\n                            </p>\r\n                            \r\n                            <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\r\n                            \r\n                            <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\r\n                                Best regards,<br>\r\n                                <strong>Mhavis Medical & Diagnostic Center</strong><br>\r\n                                Healthcare Team\r\n                            </p>\r\n                        </div>\r\n                    </div>\r\n                    ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-12-15 01:44:13', '2025-12-15 01:44:16', '2025-12-14 17:44:13'),
(56, 'pjsbandal9876@gmail.com', 'Jade Bandal', 'Appointment Status Updated - Mhavis Medical Center', '\r\n                    <div style=\'font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;\'>\r\n                        <div style=\'background-color: #f44336; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;\'>\r\n                            <h2 style=\'margin: 0;\'>❌ Appointment Status Updated</h2>\r\n                        </div>\r\n                        <div style=\'background-color: white; padding: 30px; border-radius: 0 0 5px 5px;\'>\r\n                            <p style=\'font-size: 16px; color: #333;\'>Dear <strong>Jade Bandal</strong>,</p>\r\n                            \r\n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                Your appointment status has been updated by our administration team.\r\n                            </p>\r\n                            \r\n                            <div style=\'background-color: #f5f5f5; padding: 20px; margin: 20px 0; border-left: 4px solid #f44336; border-radius: 4px;\'>\r\n                                <h3 style=\'margin-top: 0; color: #333;\'>📅 Appointment Details</h3>\r\n                                <p style=\'margin: 10px 0;\'><strong>Doctor:</strong> Dr. Ma. Maria Doe</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Date:</strong> Monday, December 15, 2025</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Time:</strong> 9:00 AM</p>\r\n                                <p style=\'margin: 10px 0;\'><strong>Status:</strong> <span style=\'color: #f44336; font-weight: bold;\'>Cancelled</span></p>\r\n                            </div>\r\n                            \r\n                            \r\n                        <div style=\'background-color: #ffebee; padding: 20px; margin: 20px 0; border-left: 4px solid #f44336; border-radius: 4px;\'>\r\n                            <h3 style=\'margin-top: 0; color: #c62828;\'>❌ Cancellation Reason</h3>\r\n                            <p style=\'margin: 0; font-size: 14px; color: #555; line-height: 1.6;\'>DOCTOR EMERGENCY</p>\r\n                        </div>\r\n                        <div style=\'background-color: #e3f2fd; padding: 15px; margin: 20px 0; border-left: 4px solid #2196F3; border-radius: 4px;\'>\r\n                            <p style=\'margin: 0; font-size: 14px; color: #1565c0;\'>\r\n                                💡 <strong>Want to reschedule?</strong> You can book a new appointment through your patient account or by contacting us directly.\r\n                            </p>\r\n                        </div>\r\n                        \r\n                            \r\n                            <p style=\'font-size: 14px; color: #555; line-height: 1.6;\'>\r\n                                If you have any questions or concerns about this status change, please contact us.\r\n                            </p>\r\n                            \r\n                            <hr style=\'border: none; border-top: 1px solid #eee; margin: 30px 0;\'>\r\n                            \r\n                            <p style=\'font-size: 13px; color: #777; text-align: center; margin: 0;\'>\r\n                                Best regards,<br>\r\n                                <strong>Mhavis Medical & Diagnostic Center</strong><br>\r\n                                Healthcare Team\r\n                            </p>\r\n                        </div>\r\n                    </div>\r\n                    ', 'html', 'Sent', 0, 3, NULL, NULL, '2025-12-15 01:44:30', '2025-12-15 01:44:34', '2025-12-14 17:44:30');

-- --------------------------------------------------------

--
-- Table structure for table `fees`
--

CREATE TABLE `fees` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `opd_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `er_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `inward_amount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fees`
--

INSERT INTO `fees` (`id`, `category_id`, `name`, `amount`, `description`, `is_active`, `created_at`, `updated_at`, `opd_amount`, `er_amount`, `inward_amount`) VALUES
(1, 1, 'General Consultation', 500.00, 'Regular consultation with general physician', 1, '2025-06-04 06:59:26', '2025-11-05 14:36:23', 1000.00, 1500.00, 2000.00),
(2, 1, 'Specialist Consultation', 1500.00, 'Consultation with specialist doctor', 1, '2025-06-04 06:59:26', '2025-06-04 08:29:51', 0.00, 0.00, 0.00),
(3, 2, 'Complete Blood Count', 350.00, 'CBC test', 1, '2025-06-04 06:59:26', '2025-06-04 06:59:26', 0.00, 0.00, 0.00),
(4, 2, 'Urinalysis', 200.00, 'Urine analysis test', 1, '2025-06-04 06:59:26', '2025-06-04 06:59:26', 0.00, 0.00, 0.00),
(5, 2, 'Blood Chemistry', 800.00, 'Basic blood chemistry panel', 1, '2025-06-04 06:59:26', '2025-06-04 06:59:26', 0.00, 0.00, 0.00),
(6, 3, 'Chest X-ray', 800.00, 'Standard chest x-ray', 1, '2025-06-04 06:59:26', '2025-06-04 06:59:26', 0.00, 0.00, 0.00),
(7, 3, 'Ultrasound', 1500.00, 'General ultrasound scan', 1, '2025-06-04 06:59:26', '2025-06-04 06:59:26', 0.00, 0.00, 0.00),
(8, 4, 'Wound Dressing', 300.00, 'Basic wound cleaning and dressing', 1, '2025-06-04 06:59:26', '2025-06-04 06:59:26', 0.00, 0.00, 0.00),
(9, 4, 'ECG', 600.00, 'Electrocardiogram', 1, '2025-06-04 06:59:26', '2025-06-04 06:59:26', 0.00, 0.00, 0.00),
(10, 5, 'Antibiotics', 500.00, 'Basic antibiotic course', 1, '2025-06-04 06:59:26', '2025-06-04 06:59:26', 0.00, 0.00, 0.00),
(11, 5, 'Pain Relievers', 200.00, 'Standard pain medication', 1, '2025-06-04 06:59:26', '2025-06-04 06:59:26', 0.00, 0.00, 0.00),
(12, 2, 'Albumin', 0.00, '', 1, '2025-07-31 01:36:51', '2025-07-31 01:36:51', 210.00, 265.00, 330.00),
(13, 4, 'Tuli', 0.00, '', 1, '2025-07-31 01:37:29', '2025-07-31 01:37:29', 600.00, 1000.00, 1500.00),
(14, 7, 'OGTT (75 grams)', 0.00, '', 1, '2025-11-05 14:27:50', '2025-11-05 14:27:50', 855.00, 1070.00, 1340.00),
(15, 7, '1-Hour PPBG', 0.00, '', 1, '2025-11-05 14:29:23', '2025-11-05 14:29:23', 380.00, 475.00, 600.00),
(16, 7, '2-Hour PPBG', 0.00, '', 1, '2025-11-05 14:30:31', '2025-11-05 14:30:31', 530.00, 665.00, 830.00),
(17, 7, 'Albumin', 0.00, '', 1, '2025-11-05 14:30:51', '2025-11-05 14:30:51', 210.00, 265.00, 330.00),
(18, 7, 'Bilirubin (Direct)', 0.00, '', 1, '2025-11-05 14:31:29', '2025-11-05 14:31:29', 285.00, 360.00, 450.00),
(19, 7, 'Bilirubin (Indirect)', 0.00, '', 1, '2025-11-05 14:32:02', '2025-11-05 14:32:02', 285.00, 360.00, 450.00),
(20, 7, 'Bilirubin (Total)', 0.00, '', 1, '2025-11-05 14:32:32', '2025-11-05 14:32:32', 285.00, 360.00, 450.00),
(21, 7, 'BUA', 0.00, '', 1, '2025-11-05 14:33:07', '2025-11-05 14:33:07', 180.00, 225.00, 285.00),
(22, 7, 'BUN', 0.00, '', 1, '2025-11-05 14:33:26', '2025-11-05 14:33:26', 180.00, 225.00, 285.00),
(23, 7, 'Cholesterol', 0.00, '', 1, '2025-11-05 14:33:50', '2025-11-05 14:33:50', 190.00, 240.00, 300.00),
(24, 7, 'Creatinine', 0.00, '', 1, '2025-11-05 14:34:15', '2025-11-05 14:34:15', 180.00, 225.00, 285.00),
(25, 7, 'FBS', 0.00, '', 1, '2025-11-05 14:34:32', '2025-11-05 14:34:32', 190.00, 240.00, 300.00),
(26, 7, 'HBA1C', 0.00, '', 1, '2025-11-05 14:34:57', '2025-11-05 14:34:57', 900.00, 1125.00, 1410.00),
(27, 7, 'HDL', 0.00, '', 1, '2025-11-05 14:35:25', '2025-11-05 14:35:25', 660.00, 825.00, 1035.00),
(28, 7, 'Lipid Profile', 0.00, '', 1, '2025-11-05 14:35:51', '2025-11-05 14:35:51', 1030.00, 1290.00, 1610.00),
(29, 7, 'RBS', 0.00, '', 1, '2025-11-05 14:36:52', '2025-11-05 14:36:52', 190.00, 240.00, 300.00),
(30, 7, 'TPAG', 0.00, '', 1, '2025-11-05 14:37:12', '2025-11-05 14:37:12', 485.00, 610.00, 760.00),
(31, 7, 'Total Protein', 0.00, '', 1, '2025-11-05 14:37:37', '2025-11-05 14:37:37', 250.00, 315.00, 395.00),
(33, 8, 'Calcium (Ionized)', 0.00, '', 1, '2025-11-05 14:40:18', '2025-11-05 14:40:18', 970.00, 1215.00, 1520.00),
(34, 8, 'Calcium (Total)', 0.00, '', 1, '2025-11-05 14:40:46', '2025-11-05 14:40:46', 230.00, 290.00, 360.00),
(35, 8, 'Chloride (CL)', 0.00, '', 1, '2025-11-05 14:42:00', '2025-11-05 14:42:00', 260.00, 325.00, 410.00),
(36, 8, 'Magnesium (Mg)', 0.00, '', 1, '2025-11-05 14:42:33', '2025-11-05 14:42:33', 730.00, 915.00, 1145.00),
(37, 8, 'Phosphorus', 0.00, '', 1, '2025-11-05 14:43:04', '2025-11-05 14:43:04', 270.00, 340.00, 425.00),
(38, 8, 'Potassium (K)', 0.00, '', 1, '2025-11-05 14:43:29', '2025-11-05 14:43:29', 260.00, 325.00, 410.00),
(39, 8, 'Sodium (Na)', 0.00, '', 1, '2025-11-05 14:43:48', '2025-11-05 14:43:48', 260.00, 325.00, 410.00),
(40, 9, 'ACP', 0.00, '', 1, '2025-11-05 14:45:18', '2025-11-05 14:45:18', 285.00, 360.00, 450.00),
(41, 9, 'ALP', 0.00, '', 1, '2025-11-05 14:45:35', '2025-11-05 14:45:35', 240.00, 300.00, 375.00),
(42, 9, 'ALT/SGPT', 0.00, '', 1, '2025-11-05 14:45:59', '2025-11-05 14:45:59', 340.00, 425.00, 535.00),
(43, 9, 'AST/SGOT', 0.00, '', 1, '2025-11-05 14:46:24', '2025-11-05 14:46:24', 340.00, 425.00, 535.00),
(44, 9, 'AMY', 0.00, '', 1, '2025-11-05 14:46:43', '2025-11-05 14:46:43', 430.00, 540.00, 675.00),
(45, 9, 'CPK-MB', 0.00, '', 1, '2025-11-05 14:47:05', '2025-11-05 14:47:05', 1140.00, 1425.00, 1790.00),
(46, 9, 'CPK-MM', 0.00, '', 1, '2025-11-05 14:47:30', '2025-11-05 14:47:30', 1625.00, 2040.00, 2540.00),
(47, 9, 'GGTP', 0.00, '', 1, '2025-11-05 14:47:51', '2025-11-05 14:47:51', 330.00, 415.00, 520.00),
(48, 9, 'LDH', 0.00, '', 1, '2025-11-05 14:48:11', '2025-11-05 14:48:11', 290.00, 365.00, 455.00),
(49, 9, 'Lipase', 0.00, '', 1, '2025-11-05 14:48:29', '2025-11-05 14:48:29', 535.00, 670.00, 840.00),
(50, 9, 'Total CPK', 0.00, '', 1, '2025-11-05 14:48:48', '2025-11-05 14:48:48', 940.00, 1175.00, 1470.00),
(51, 9, 'Troponin I (Quantitative)', 0.00, '', 1, '2025-11-05 14:49:24', '2025-11-05 14:49:24', 1140.00, 1425.00, 1790.00);

-- --------------------------------------------------------

--
-- Table structure for table `fee_categories`
--

CREATE TABLE `fee_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fee_categories`
--

INSERT INTO `fee_categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Consultation', 'Regular doctor consultation fees', '2025-06-04 06:59:26', '2025-06-04 06:59:26'),
(2, 'Laboratory', 'Various laboratory tests and procedures', '2025-06-04 06:59:26', '2025-06-04 06:59:26'),
(3, 'Imaging', 'X-ray, ultrasound, and other imaging services', '2025-06-04 06:59:26', '2025-06-04 06:59:26'),
(4, 'Procedures', 'Minor surgical and medical procedures', '2025-06-04 06:59:26', '2025-06-04 06:59:26'),
(5, 'Medicine', 'Prescribed medications and supplies', '2025-06-04 06:59:26', '2025-06-04 06:59:26'),
(7, 'Blood Chemistry', 'tests that measure various substances in the blood to assess organ function and overall health.', '2025-11-05 14:26:56', '2025-11-05 14:26:56'),
(8, 'Electrolytes', 'tests that measure essential minerals in the blood to check fluid balance and muscle function.', '2025-11-05 14:39:28', '2025-11-05 14:39:28'),
(9, 'Enzymes', 'tests that detect specific enzyme levels to assess organ activity and possible tissue damage.', '2025-11-05 14:44:35', '2025-11-05 14:44:35'),
(10, 'Hematology', 'tests that evaluate blood cells to detect anemia, infection, and other blood disorders.', '2025-11-05 14:50:47', '2025-11-05 14:50:47'),
(11, 'Iron Studies', 'tests that measure iron levels and related proteins to assess iron status and detect deficiencies or overload.', '2025-11-05 14:51:07', '2025-11-05 14:51:07'),
(12, 'Serology', 'tests that detect antibodies or antigens in the blood to identify infections and immune responses.', '2025-11-05 14:51:21', '2025-11-05 14:51:21'),
(13, 'Microbiology', 'tests that identify and analyze microorganisms causing infections.', '2025-11-05 14:54:48', '2025-11-05 14:54:48'),
(14, 'Thyroid Panels (ELISA Test)', 'tests that measure thyroid hormones and antibodies to assess thyroid function.', '2025-11-05 14:55:38', '2025-11-05 14:55:38'),
(15, 'Hormones', 'tests that evaluate hormone levels to monitor endocrine system function and balance.', '2025-11-05 14:56:00', '2025-11-05 14:56:00'),
(16, 'Histopathology', 'microscopic examination of tissues to diagnose diseases and abnormalities.', '2025-11-05 14:56:42', '2025-11-05 14:56:42'),
(17, 'Tumor Markers', 'tests that detect substances in the blood that may indicate the presence of cancer.', '2025-11-05 14:57:05', '2025-11-05 14:57:05'),
(18, 'POCT', 'rapid tests performed near the patient for immediate diagnostic results.', '2025-11-05 14:57:38', '2025-11-05 14:57:38'),
(19, 'Other ECLIA Tests', 'immunoassay-based tests used to measure various biomarkers with high accuracy.', '2025-11-05 14:57:58', '2025-11-05 14:57:58'),
(20, 'Hepatitis Tests', 'tests that detect hepatitis viruses or antibodies to diagnose liver infections.', '2025-11-05 14:58:17', '2025-11-05 14:58:17'),
(21, 'Clinical Microscopy', 'analysis of body fluids, such as urine or stool, to detect cells, crystals, and microorganisms.', '2025-11-05 14:58:36', '2025-11-05 14:58:36'),
(22, '24-Hour Urine Test', 'a test that measures substances collected in urine over 24 hours to assess kidney function and metabolism.', '2025-11-05 14:58:56', '2025-11-05 14:58:56');

-- --------------------------------------------------------

--
-- Table structure for table `medical_certificates`
--

CREATE TABLE `medical_certificates` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `date_issued` date DEFAULT NULL,
  `doctor_name` varchar(100) DEFAULT NULL,
  `license_no` varchar(50) DEFAULT NULL,
  `ptr_no` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_history`
--

CREATE TABLE `medical_history` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `history_type` enum('allergies','medications','past_history','immunization','procedures','substance','family','menstrual','sexual','obstetric','growth') NOT NULL,
  `history_details` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_history`
--

INSERT INTO `medical_history` (`id`, `patient_id`, `doctor_id`, `history_type`, `history_details`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(2, 34, 18, 'medications', 'Paracetamol, Amoxicillin', 'active', 18, NULL, '2025-11-26 03:24:41', '2025-11-26 03:24:41'),
(3, 34, 18, 'immunization', 'Hepatitis B', 'active', 18, 17, '2025-11-26 03:24:53', '2025-11-26 04:17:00'),
(4, 34, 18, 'allergies', 'Pollen', 'active', 18, NULL, '2025-11-26 03:25:12', '2025-11-26 03:25:12');

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `vitals` text NOT NULL,
  `diagnosis` text NOT NULL,
  `treatment` text NOT NULL,
  `prescription` text NOT NULL,
  `lab_results` text NOT NULL,
  `notes` text NOT NULL,
  `attachments` text DEFAULT NULL,
  `next_appointment_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `history_type` varchar(50) DEFAULT NULL,
  `history_details` text DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_records`
--

INSERT INTO `medical_records` (`id`, `patient_id`, `doctor_id`, `visit_date`, `vitals`, `diagnosis`, `treatment`, `prescription`, `lab_results`, `notes`, `attachments`, `next_appointment_date`, `created_at`, `updated_at`, `history_type`, `history_details`, `status`, `created_by`, `updated_by`) VALUES
(3, 1, 17, '2025-06-09', '', 'dgdfgs', '', 'fgdfhfthtf', '', 'dgfdgdf', NULL, '0000-00-00', '2025-06-09 05:39:28', '2025-06-09 05:39:28', NULL, NULL, NULL, NULL, NULL),
(5, 9, 17, '0000-00-00', '', '', '', '', '', '', NULL, '0000-00-00', '2025-07-31 04:16:35', '2025-07-31 04:16:35', 'allergies', 'Pollen', 'active', NULL, NULL),
(6, 9, 17, '0000-00-00', '', '', '', '', '', '', NULL, '0000-00-00', '2025-07-31 04:16:35', '2025-07-31 04:16:35', 'past_history', 'Asthma', 'active', NULL, NULL),
(7, 9, 17, '0000-00-00', '', '', '', '', '', '', NULL, '0000-00-00', '2025-07-31 04:16:35', '2025-07-31 04:16:35', 'family', 'Heart Disease', 'active', NULL, NULL),
(8, 4, 17, '0000-00-00', '', '', '', '', '', '', NULL, '0000-00-00', '2025-08-17 11:28:12', '2025-08-17 11:28:12', 'medications', 'Metformin', 'active', NULL, NULL),
(9, 4, 17, '0000-00-00', '', '', '', '', '', '', NULL, '0000-00-00', '2025-08-17 11:28:12', '2025-08-17 11:28:12', 'immunization', 'COVID-19', 'active', NULL, NULL),
(12, 28, 18, '2025-11-05', '', 'sample ', 'sample', '', 'none', 'sample', NULL, '0000-00-00', '2025-11-05 00:57:08', '2025-11-05 00:57:08', NULL, NULL, NULL, NULL, NULL),
(25, 34, 18, '2025-11-20', '', 'sampel', 'sampel', '', 'sampel', 'sample....', NULL, '2025-12-01', '2025-11-26 02:25:00', '2025-11-26 04:21:04', NULL, NULL, NULL, 18, 17),
(27, 34, 18, '2025-11-21', '', 'sample', 'sample', '', 'sample', 'sample', NULL, '0000-00-00', '2025-11-26 08:48:21', '2025-11-26 08:48:21', NULL, NULL, NULL, 18, NULL),
(28, 34, 18, '2024-11-20', '', 'sample', 'sample', '', 'sample', 'sample', NULL, '0000-00-00', '2025-11-26 08:48:40', '2025-11-26 08:48:40', NULL, NULL, NULL, 18, NULL),
(30, 34, 18, '2025-10-11', '', 'sample', 'sample', '', 'sample', 'sample', NULL, '0000-00-00', '2025-11-26 09:30:44', '2025-11-26 09:30:44', NULL, NULL, NULL, 17, NULL),
(33, 34, 18, '2025-11-26', 'BP: 120/80 • Temperature: 98.9 °F • Heart Rate: 71 bpm • Respiratory Rate: 17 /min • O2 Saturation: 98 % • Weight: 165 lbs • Height: 68 in', 'sample', 'sample', '', 'sample', 'samp', '[{\"original_name\":\"consent-form-non-tech.pdf\",\"file_path\":\"uploads\\/medical_records\\/693e90ca42ed6_1765707978.pdf\",\"file_type\":\"application\\/pdf\",\"file_size\":178528},{\"original_name\":\"ISO-25010-Evaluation-NON-TECH-EVAL-1.pdf\",\"file_path\":\"uploads\\/medical_records\\/693e959f632b6_1765709215.pdf\",\"file_type\":\"application\\/pdf\",\"file_size\":196776}]', '0000-00-00', '2025-11-26 21:05:13', '2025-12-14 19:26:15', NULL, NULL, NULL, 18, 18),
(34, 28, 18, '2025-12-04', '', 'swedrftgyhujikol', 'tyuim,', '', 'secrvtbynmk,l', 'ertyuim,', '[{\"original_name\":\"IMPLEMENTATION LETTER.pdf\",\"file_path\":\"uploads\\/medical_records\\/6931303104fd0_1764831281.pdf\",\"file_type\":\"application\\/pdf\",\"file_size\":279870}]', '0000-00-00', '2025-12-04 06:54:41', '2025-12-04 06:54:41', NULL, NULL, NULL, 18, NULL),
(35, 28, 18, '2025-12-04', '', 'xcvbbbbbbnnnn', 'xcvb', '', 'fbn', 'cvbn', '[{\"original_name\":\"CEIT R&E Form 6 (1).doc\",\"file_path\":\"uploads\\/medical_records\\/693133e9e98ea_1764832233.doc\",\"file_type\":\"application\\/msword\",\"file_size\":71680},{\"original_name\":\"CEIT R&E Form 6 (bago).pdf\",\"file_path\":\"uploads\\/medical_records\\/693133e9e9c01_1764832233.pdf\",\"file_type\":\"application\\/pdf\",\"file_size\":206788},{\"original_name\":\"IMPLEMENTATION-EVALUATION_LETTER.pdf\",\"file_path\":\"uploads\\/medical_records\\/693133e9e9ec0_1764832233.pdf\",\"file_type\":\"application\\/pdf\",\"file_size\":285081},{\"original_name\":\"IMPLEMENTATION-EVALUATION_LETTER.pdf.docx\",\"file_path\":\"uploads\\/medical_records\\/693133e9ea0db_1764832233.docx\",\"file_type\":\"application\\/vnd.openxmlformats-officedocument.wordprocessingml.document\",\"file_size\":4925327},{\"original_name\":\"IMPLEMENTATION-EVALUATION_LETTER.pdf.pdf\",\"file_path\":\"uploads\\/medical_records\\/693133e9ea2d9_1764832233.pdf\",\"file_type\":\"application\\/pdf\",\"file_size\":500168},{\"original_name\":\"IMPLEMENTATION-LETTER mhavis (2).pdf\",\"file_path\":\"uploads\\/medical_records\\/693133e9ea5a8_1764832233.pdf\",\"file_type\":\"application\\/pdf\",\"file_size\":204955}]', '0000-00-00', '2025-12-04 07:10:33', '2025-12-04 07:10:33', NULL, NULL, NULL, 18, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `monthly_revenue_view`
-- (See below for the actual view)
--
CREATE TABLE `monthly_revenue_view` (
`year` int(4)
,`month` int(2)
,`month_name` varchar(9)
,`department` varchar(100)
,`transaction_count` bigint(21)
,`gross_revenue` decimal(32,2)
,`total_discounts` decimal(32,2)
,`net_revenue` decimal(32,2)
,`average_amount` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `recipient_type` enum('Patient','Admin','Doctor') NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `type` enum('Registration_Approved','Registration_Rejected','Appointment_Approved','Appointment_Rejected','Appointment_Reminder','Appointment_Rescheduled') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_via` enum('Email','SMS','System') NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `recipient_type`, `recipient_id`, `type`, `title`, `message`, `is_read`, `sent_via`, `sent_at`, `created_at`) VALUES
(3, 'Patient', 3, 'Registration_Approved', 'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 1, 'Email', NULL, '2025-10-31 05:22:00'),
(8, 'Patient', 3, 'Appointment_Rejected', 'Appointment Request Rejected', 'Your appointment request has been rejected. Please contact us for more information.', 1, 'Email', NULL, '2025-10-31 07:12:29'),
(9, 'Patient', 3, 'Appointment_Rejected', 'Appointment Request Rejected', 'We regret to inform you that your appointment request could not be approved at this time.\n\nPlease feel free to submit a new request with different preferences or contact us for assistance.', 1, 'System', NULL, '2025-10-31 07:12:29'),
(12, 'Patient', 3, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Maria Doe\nDate: Nov 1, 2025\nTime: 11:00 AM\n\nPlease arrive 15 minutes early for your appointment.', 1, 'System', NULL, '2025-10-31 07:45:21'),
(14, 'Patient', 3, 'Appointment_Approved', 'Appointment Approved', 'Your appointment with Dr. Simeon Daez has been approved for Dec 5, 2025 at 1:00 PM.', 1, 'Email', NULL, '2025-11-01 07:58:44'),
(15, 'Patient', 3, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Simeon Daez\nDate: Dec 5, 2025\nTime: 1:00 PM\n\nPlease arrive 15 minutes early for your appointment.', 1, 'System', NULL, '2025-11-01 07:58:44'),
(20, 'Patient', 8, 'Registration_Approved', 'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 1, 'Email', NULL, '2025-11-01 09:15:40'),
(22, 'Patient', 8, 'Appointment_Approved', 'Appointment Approved', 'Your appointment with Dr. Maria Doe has been approved for Dec 22, 2025 at 10:00 AM.', 1, 'Email', NULL, '2025-11-01 09:17:18'),
(23, 'Patient', 8, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Maria Doe\nDate: Dec 22, 2025\nTime: 10:00 AM\n\nPlease arrive 15 minutes early for your appointment.', 1, 'System', NULL, '2025-11-01 09:17:18'),
(25, 'Patient', 8, 'Appointment_Approved', 'Appointment Approved', 'Your appointment with Dr. Maria Doe has been approved for Dec 5, 2025 at 9:00 AM.', 0, 'Email', NULL, '2025-11-01 09:23:33'),
(26, 'Patient', 8, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Maria Doe\nDate: Dec 5, 2025\nTime: 9:00 AM\n\nPlease arrive 15 minutes early for your appointment.', 0, 'System', NULL, '2025-11-01 09:23:33'),
(28, 'Patient', 8, 'Appointment_Approved', 'Appointment Approved', 'Your appointment with Dr. Simeon Daez has been approved for Dec 1, 2025 at 10:00 AM.', 0, 'Email', NULL, '2025-11-01 09:32:34'),
(29, 'Patient', 8, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Simeon Daez\nDate: Dec 1, 2025\nTime: 10:00 AM\n\nPlease arrive 15 minutes early for your appointment.', 0, 'System', NULL, '2025-11-01 09:32:34'),
(30, 'Patient', 9, 'Registration_Approved', 'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 1, 'Email', NULL, '2025-11-04 22:50:36'),
(32, 'Patient', 9, 'Appointment_Approved', 'Appointment Approved', 'Your appointment with Dr. Maria Doe has been approved for Nov 10, 2025 at 11:00 AM.', 0, 'Email', NULL, '2025-11-05 00:55:21'),
(33, 'Patient', 9, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Maria Doe\nDate: Nov 10, 2025\nTime: 11:00 AM\n\nPlease arrive 15 minutes early for your appointment.', 0, 'System', NULL, '2025-11-05 00:55:21'),
(34, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: jade Bandal\nDoctor: Dr. Simeon Daez\nDate: Nov 14, 2025\nTime: 10:00 AM\nUrgency: Medium\nReason: sample', 0, 'System', NULL, '2025-11-05 01:19:12'),
(35, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Hannah Placio\nEmail: hannahpauline1016@gmail.com\nPhone: 09162036179\nPatient Type: Existing\nSubmitted: Nov 6, 2025 1:15 PM', 0, 'System', NULL, '2025-11-06 05:15:58'),
(36, 'Patient', 10, 'Registration_Approved', 'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 0, 'Email', NULL, '2025-11-06 05:19:22'),
(37, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Edward Lalu\nEmail: eddedward45@gmail.com\nPhone: 091234567891\nPatient Type: New\nSubmitted: Nov 9, 2025 2:03 PM', 0, 'System', NULL, '2025-11-09 06:03:57'),
(38, 'Patient', 3, 'Appointment_Approved', 'Appointment Approved', 'Your appointment with Dr. Simeon Daez has been approved for Nov 14, 2025 at 10:00 AM.', 1, 'Email', NULL, '2025-11-13 06:21:27'),
(39, 'Patient', 3, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Simeon Daez\nDate: Nov 14, 2025\nTime: 10:00 AM\n\nPlease arrive 15 minutes early for your appointment.', 1, 'System', NULL, '2025-11-13 06:21:27'),
(40, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Jade Bandal\nEmail: pjsbandal9876@gmail.com\nPhone: 09055462300\nPatient Type: New\nSubmitted: Nov 16, 2025 7:06 PM', 0, 'System', NULL, '2025-11-16 11:06:56'),
(41, 'Patient', 11, 'Registration_Approved', 'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 0, 'Email', NULL, '2025-11-16 11:08:01'),
(42, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: Jade Bandal\nDoctor: Dr. Maria Doe\nDate: Nov 17, 2025\nTime: 1:00 PM\nUrgency: Medium\nReason: klkklklkijnm', 0, 'System', NULL, '2025-11-16 11:09:05'),
(43, 'Patient', 11, 'Appointment_Approved', 'Appointment Approved', 'Your appointment with Dr. Maria Doe has been approved for Nov 17, 2025 at 1:00 PM.', 0, 'Email', NULL, '2025-11-16 11:10:37'),
(44, 'Patient', 11, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Maria Doe\nDate: Nov 17, 2025\nTime: 1:00 PM\n\nPlease arrive 15 minutes early for your appointment.', 0, 'System', NULL, '2025-11-16 11:10:37'),
(45, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Jade Bandal\nEmail: pjsbandal9876@gmail.com\nPhone: 09055462300\nPatient Type: New\nSubmitted: Nov 16, 2025 8:15 PM', 0, 'System', NULL, '2025-11-16 12:15:53'),
(46, 'Patient', 12, 'Registration_Approved', 'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 0, 'Email', NULL, '2025-11-16 12:16:10'),
(47, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Jade Bandal\nEmail: pjsbandal9876@gmail.com\nPhone: 09217967025\nPatient Type: New\nSubmitted: Nov 16, 2025 8:23 PM', 0, 'System', NULL, '2025-11-16 12:23:51'),
(48, 'Patient', 13, 'Registration_Approved', 'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 0, 'Email', NULL, '2025-11-16 12:24:06'),
(49, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Jade Bandal\nEmail: pjsbandal9876@gmail.com\nPhone: 09055462300\nPatient Type: New\nSubmitted: Nov 16, 2025 8:26 PM', 0, 'System', NULL, '2025-11-16 12:26:20'),
(50, 'Patient', 14, 'Registration_Approved', 'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 0, 'Email', NULL, '2025-11-16 12:26:53'),
(51, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: Jade Bandal\nDoctor: Dr. Maria Doe\nDate: Nov 17, 2025\nTime: 11:00 AM\nUrgency: Medium\nReason: asasasad', 0, 'System', NULL, '2025-11-16 12:32:28'),
(52, 'Patient', 14, 'Appointment_Approved', 'Appointment Approved', 'Your appointment with Dr. Maria Doe has been approved for Nov 17, 2025 at 11:00 AM.', 0, 'Email', NULL, '2025-11-16 12:32:51'),
(53, 'Patient', 14, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Maria Doe\nDate: Nov 17, 2025\nTime: 11:00 AM\n\nPlease arrive 15 minutes early for your appointment.', 0, 'System', NULL, '2025-11-16 12:32:51'),
(54, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: Jade Bandal\nDoctor: Dr. Maria Doe\nDate: Nov 17, 2025\nTime: 11:30 AM\nUrgency: Medium\nReason: sample reason', 0, 'System', NULL, '2025-11-16 12:43:32'),
(55, 'Patient', 14, 'Appointment_Approved', 'Appointment Approved', 'Your appointment with Dr. Maria Doe has been approved for Nov 17, 2025 at 11:30 AM.', 0, 'Email', NULL, '2025-11-16 12:43:51'),
(56, 'Patient', 14, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Maria Doe\nDate: Nov 17, 2025\nTime: 11:30 AM\n\nPlease arrive 15 minutes early for your appointment.', 0, 'System', NULL, '2025-11-16 12:43:51'),
(57, 'Doctor', 18, '', 'New Appointment Assigned', 'You have a new appointment.\n\nPatient: Jade Bandal\nDate: Nov 17, 2025\nTime: 11:30 AM\n\nPlease review the patient record before the visit.', 1, 'System', NULL, '2025-11-16 12:43:51'),
(58, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Jade Bandal\nEmail: pjsbandal9876@gmail.com\nPhone: 09055462300\nPatient Type: New\nSubmitted: Nov 25, 2025 8:32 AM', 0, 'System', NULL, '2025-11-25 00:32:35'),
(59, 'Patient', 15, 'Registration_Approved', 'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 1, 'Email', NULL, '2025-11-25 00:32:53'),
(60, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: Jade Bandal\nDoctor: Dr. Maria Doe\nDate: Nov 26, 2025\nTime: 2:00 PM\nUrgency: Medium\nReason: sample', 0, 'System', NULL, '2025-11-25 00:36:04'),
(61, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: Jade Bandal\nDoctor: Dr. Simeon Daez\nDate: Nov 26, 2025\nTime: 2:00 PM\nUrgency: Medium\nReason: sample', 0, 'System', NULL, '2025-11-25 00:36:25'),
(62, 'Patient', 15, 'Appointment_Approved', 'Appointment Approved', 'Your appointment with Dr. Maria Doe has been approved for Nov 26, 2025 at 2:00 PM.', 1, 'Email', NULL, '2025-11-25 00:36:41'),
(63, 'Patient', 15, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Maria Doe\nDate: Nov 26, 2025\nTime: 2:00 PM\n\nPlease arrive 15 minutes early for your appointment.', 1, 'System', NULL, '2025-11-25 00:36:41'),
(64, 'Doctor', 18, '', 'New Appointment Assigned', 'You have a new appointment.\n\nPatient: Jade Bandal\nDate: Nov 26, 2025\nTime: 2:00 PM\n\nPlease review the patient record before the visit.', 1, 'System', NULL, '2025-11-25 00:36:41'),
(65, 'Patient', 15, 'Appointment_Rejected', 'Appointment Request Rejected', 'Your appointment request has been rejected. Please contact us for more information.', 1, 'Email', NULL, '2025-11-25 00:37:24'),
(66, 'Patient', 15, 'Appointment_Rejected', 'Appointment Request Rejected', 'We regret to inform you that your appointment request could not be approved at this time.\n\nPlease feel free to submit a new request with different preferences or contact us for assistance.', 1, 'System', NULL, '2025-11-25 00:37:24'),
(67, 'Patient', 15, '', 'Medical Record Updated', 'Your medical record from Nov 20, 2025 has been updated by the admin. Please review the changes in your medical records.', 1, 'System', NULL, '2025-11-26 04:21:04'),
(68, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Allen Candelaria\nEmail: ezekieltallano@gmail.com\nPhone: +639123456768\nPatient Type: New\nSubmitted: Nov 27, 2025 1:33 AM', 0, 'System', NULL, '2025-11-26 17:33:55'),
(69, 'Patient', 16, 'Registration_Approved', 'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 0, 'Email', NULL, '2025-11-26 17:34:11'),
(70, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: Jade Bandal\nDoctor: Dr. Maria Doe\nDate: Dec 1, 2025\nTime: 10:00 AM\nUrgency: Medium\nReason: sample', 0, 'System', NULL, '2025-11-26 18:10:36'),
(71, 'Patient', 15, 'Appointment_Approved', 'Appointment Approved', 'Your appointment with Dr. Maria Doe has been approved for Dec 1, 2025 at 10:00 AM.', 1, 'Email', NULL, '2025-11-26 18:11:41'),
(72, 'Patient', 15, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Maria Doe\nDate: Dec 1, 2025\nTime: 10:00 AM\n\nPlease arrive 15 minutes early for your appointment.', 1, 'System', NULL, '2025-11-26 18:11:41'),
(73, 'Doctor', 18, '', 'New Appointment Assigned', 'You have a new appointment.\n\nPatient: Jade Bandal\nDate: Dec 1, 2025\nTime: 10:00 AM\n\nPlease review the patient record before the visit.', 1, 'System', NULL, '2025-11-26 18:11:41'),
(74, 'Patient', 15, '', 'New Prescription Added', 'A new prescription for Adefovir Dipivoxil has been prescribed by Dr. Juan Dela Cruz on Nov 27, 2025. Please check your prescriptions section for details.', 1, 'System', NULL, '2025-11-26 18:57:04'),
(75, 'Patient', 15, '', 'New Prescription Added', 'A new prescription for Abacavir Sulfate has been prescribed by Dr. Juan Dela Cruz on Nov 27, 2024. Please check your prescriptions section for details.', 1, 'System', NULL, '2025-11-26 18:59:29'),
(76, 'Patient', 15, '', 'New Medical Record Added', 'A new medical record for your visit on Jan 25, 2018 has been added by Dr. Maria Doe. Please check your medical records section for details.', 1, 'System', NULL, '2025-11-26 19:00:24'),
(77, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: Jade Bandal\nDoctor: Dr. Maria Doe\nDate: Dec 1, 2025\nTime: 11:00 AM\nUrgency: Medium\nReason: sample', 0, 'System', NULL, '2025-11-26 19:56:47'),
(78, 'Patient', 15, '', 'New Medical Record Added', 'A new medical record for your visit on Nov 26, 2025 has been added by Dr. Maria Doe. Please check your medical records section for details.', 1, 'System', NULL, '2025-11-26 21:05:13'),
(79, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: Mikaela Ramos\nDoctor: Dr. Simeon Daez\nDate: Nov 28, 2025\nTime: 11:00 AM\nReason: check up lang po', 0, 'System', NULL, '2025-11-27 00:01:04'),
(80, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Eduardo Lalu\nEmail: eddedward45@gmail.com\nPhone: +639669363741\nPatient Type: Existing\nSubmitted: Nov 27, 2025 11:15 AM', 0, 'System', NULL, '2025-11-27 03:15:13'),
(81, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Eduardo Lalu\nEmail: ceraphina.keyl@gmail.com\nPhone: +639669363741\nPatient Type: Existing\nSubmitted: Nov 27, 2025 11:31 AM', 0, 'System', NULL, '2025-11-27 03:31:20'),
(82, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Eduardo Lalu\nEmail: ceraphina.keyl@gmail.com\nPhone: +639669363741\nPatient Type: Existing\nSubmitted: Nov 27, 2025 11:44 AM', 0, 'System', NULL, '2025-11-27 03:44:19'),
(83, 'Patient', 17, 'Registration_Approved', 'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 0, 'Email', NULL, '2025-11-27 03:44:40'),
(84, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: Eduardo Lalu\nDoctor: Dr. Simeon Daez\nDate: Nov 28, 2025\nTime: 11:30 AM\nReason: masakit tyan', 0, 'System', NULL, '2025-11-27 03:49:42'),
(85, 'Patient', 17, 'Appointment_Approved', 'Appointment Approved', 'Your appointment with Dr. Simeon Daez has been approved for Nov 28, 2025 at 11:30 AM.', 0, 'Email', NULL, '2025-11-27 03:54:09'),
(86, 'Patient', 17, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Simeon Daez\nDate: Nov 28, 2025\nTime: 11:30 AM\n\nPlease arrive 15 minutes early for your appointment.', 0, 'System', NULL, '2025-11-27 03:54:09'),
(87, 'Doctor', 23, '', 'New Appointment Assigned', 'You have a new appointment.\n\nPatient: Eduardo Lalu\nDate: Nov 28, 2025\nTime: 11:30 AM\n\nPlease review the patient record before the visit.', 0, 'System', NULL, '2025-11-27 03:54:09'),
(88, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Maileen Nadulas\nEmail: gingerieclover@gmail.com\nPhone: +639123456789\nPatient Type: Existing\nSubmitted: Nov 28, 2025 2:55 PM', 0, 'System', NULL, '2025-11-28 06:55:06'),
(89, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Jade bandal\nEmail: bandal.princessjades@gmail.com\nPhone: +639055462300\nPatient Type: Existing\nSubmitted: Nov 29, 2025 12:02 PM', 0, 'System', NULL, '2025-11-29 04:02:36'),
(90, 'Patient', 18, 'Registration_Approved', 'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 0, 'Email', NULL, '2025-11-29 04:15:28'),
(91, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Jade bandal\nEmail: bandal.princessjades@gmail.com\nPhone: +633055462300\nPatient Type: Existing\nSubmitted: Nov 29, 2025 12:17 PM', 0, 'System', NULL, '2025-11-29 04:17:16'),
(92, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: JADE BANDAL\nEmail: BANDAL.PRINCESSJADES@GMAIL.COM\nPhone: +639314569874\nPatient Type: New\nSubmitted: Nov 29, 2025 1:23 PM', 0, 'System', NULL, '2025-11-29 05:23:45'),
(93, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: TESNAME TESNAME\nEmail: BANDAL.PRINCESSJADES@GMAIL.COM\nPhone: +631236547899\nPatient Type: Existing\nSubmitted: Nov 29, 2025 1:25 PM', 0, 'System', NULL, '2025-11-29 05:25:06'),
(94, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: TESNAME TESNAME\nEmail: pjsbandal9876@gmail.com\nPhone: +630312365478\nPatient Type: Existing\nSubmitted: Nov 29, 2025 1:26 PM', 0, 'System', NULL, '2025-11-29 05:26:46'),
(95, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: Mikaela Ramos\nDoctor: Dr. Ma. Maria Doe\nDate: Dec 8, 2025\nTime: 9:30 AM\nReason: sdtyul', 0, 'System', NULL, '2025-12-02 08:45:45'),
(96, 'Patient', 9, 'Appointment_Approved', 'Appointment Approved', 'Your appointment with Dr. Ma. Maria Doe has been approved for Dec 8, 2025 at 9:30 AM.', 0, 'Email', NULL, '2025-12-02 08:50:02'),
(97, 'Patient', 9, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Ma. Maria Doe\nDate: Dec 8, 2025\nTime: 9:30 AM\n\nPlease arrive 15 minutes early for your appointment.', 0, 'System', NULL, '2025-12-02 08:50:02'),
(98, 'Doctor', 18, '', 'New Appointment Assigned', 'You have a new appointment.\n\nPatient: Mikaela Ramos\nDate: Dec 8, 2025\nTime: 9:30 AM\n\nPlease review the patient record before the visit.', 1, 'System', NULL, '2025-12-02 08:50:02'),
(99, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Mikaelaa Ramosa\nEmail: hannahpauline1018@gmail.com\nPhone: +639123456889\nPatient Type: Existing\nSubmitted: Dec 4, 2025 2:02 PM', 0, 'System', NULL, '2025-12-04 06:02:51'),
(100, 'Patient', 19, 'Registration_Approved', 'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 0, 'Email', NULL, '2025-12-04 06:06:09'),
(101, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: Mikaela Ramos\nDoctor: Dr. Simeon Daez\nDate: Dec 15, 2025\nTime: 11:00 AM\nReason: eme', 0, 'System', NULL, '2025-12-04 06:13:54'),
(102, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: Mikaela Ramos\nDoctor: Dr. Simeon Daez\nDate: Dec 15, 2025\nTime: 11:30 AM\nReason: sakit ulo', 0, 'System', NULL, '2025-12-04 06:14:35'),
(103, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: Mikaela Ramos\nDoctor: Dr. Simeon Daez\nDate: Dec 12, 2025\nTime: 1:00 PM\nReason: sdfghjkl', 0, 'System', NULL, '2025-12-04 06:45:55'),
(104, 'Patient', 9, '', 'New Medical Record Added', 'A new medical record for your visit on Dec 4, 2025 has been added by Dr. Ma. Maria Doe. Please check your medical records section for details.', 0, 'System', NULL, '2025-12-04 06:54:41'),
(105, 'Patient', 9, '', 'New Medical Record Added', 'A new medical record for your visit on Dec 4, 2025 has been added by Dr. Ma. Maria Doe. Please check your medical records section for details.', 0, 'System', NULL, '2025-12-04 07:10:33'),
(106, 'Patient', 15, 'Appointment_Approved', 'Appointment Approved', 'Your appointment with Dr. Ma. Maria Doe has been approved for Dec 1, 2025 at 11:00 AM.', 1, 'Email', NULL, '2025-12-06 16:17:16'),
(107, 'Patient', 15, 'Appointment_Approved', 'Appointment Approved', 'Your appointment request has been approved!\n\nDoctor: Dr. Ma. Maria Doe\nDate: Dec 1, 2025\nTime: 11:00 AM\n\nPlease arrive 15 minutes early for your appointment.', 1, 'System', NULL, '2025-12-06 16:17:16'),
(108, 'Doctor', 18, '', 'New Appointment Assigned', 'You have a new appointment.\n\nPatient: Jade Bandal\nDate: Dec 1, 2025\nTime: 11:00 AM\n\nPlease review the patient record before the visit.', 1, 'System', NULL, '2025-12-06 16:17:16'),
(109, 'Admin', 17, 'Appointment_Reminder', 'New Appointment Request', 'A new appointment request has been submitted.\n\nPatient: Jade Bandal\nDoctor: Dr. Ma. Maria Doe\nDate: Dec 10, 2025\nTime: 3:30 PM\nReason: sasasas', 0, 'System', NULL, '2025-12-06 16:22:27'),
(110, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Ma. Maria Nadulas\nEmail: pjsbandal9876@gmail.com\nPhone: +639123456789\nPatient Type: Existing\nSubmitted: Dec 7, 2025 12:24 AM', 0, 'System', NULL, '2025-12-06 16:24:39'),
(114, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Ma. Maria Daez\nEmail: pjsbandal9876@gmail.com\nPhone: +639123456789\nPatient Type: Existing\nSubmitted: Dec 7, 2025 12:35 AM', 0, 'System', NULL, '2025-12-06 16:35:15'),
(115, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Jade bandal\nEmail: pjsbandal9876@gmail.com\nPhone: +639123456789\nPatient Type: Existing\nSubmitted: Dec 7, 2025 12:41 AM', 0, 'System', NULL, '2025-12-06 16:41:56'),
(116, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Ma. Maria Nadulas\nEmail: bandal.princessjades@gmail.com\nPhone: +639123456789\nPatient Type: New\nSubmitted: Dec 7, 2025 1:14 AM', 0, 'System', NULL, '2025-12-06 17:14:35'),
(117, 'Patient', 20, 'Registration_Approved', 'Registration Approved', 'Your registration has been approved. You can now log in to your patient portal.', 0, 'Email', NULL, '2025-12-06 17:15:01'),
(118, 'Admin', 17, 'Appointment_Reminder', 'New Patient Registration Request', 'A new patient registration request has been submitted.\n\nPatient: Simeon Daez\nEmail: daez@gmail.com\nPhone: \nPatient Type: Existing\nSubmitted: Dec 9, 2025 10:02 PM', 0, 'System', NULL, '2025-12-09 14:02:54'),
(119, 'Patient', 15, '', 'Medical Record Updated', 'Your medical record from November 26, 2025 has been updated by your doctor. Please review the changes in your medical records.', 1, 'System', NULL, '2025-12-14 10:26:18'),
(120, 'Patient', 15, '', 'Medical Record Updated', 'Your medical record from November 26, 2025 has been updated by the admin. Please review the changes in your medical records.', 1, 'System', NULL, '2025-12-14 10:44:38'),
(125, 'Patient', 15, '', 'Appointment Status Updated', 'Your appointment with Dr. Ma. Maria Doe on Monday, December 15, 2025 at 11:00 AM has been updated to: Cancelled', 1, 'System', NULL, '2025-12-14 16:23:50'),
(126, 'Doctor', 18, '', 'Appointment Status Updated', 'Appointment with Jade Bandal on Monday, December 15, 2025 at 11:00 AM has been updated to: Cancelled by an administrator.', 0, 'System', NULL, '2025-12-14 16:23:50'),
(127, 'Admin', 17, '', 'Appointment Status Updated', 'You have updated the appointment status for Jade Bandal with Dr. Ma. Maria Doe on Monday, December 15, 2025 at 11:00 AM to: Cancelled', 0, 'System', NULL, '2025-12-14 16:23:50'),
(128, 'Patient', 15, '', 'Appointment Scheduled', 'Your appointment with Dr. Ma. Maria Doe has been scheduled for Monday, December 15, 2025 at 9:00 AM.', 1, 'System', NULL, '2025-12-14 16:26:25'),
(129, 'Doctor', 18, '', 'New Appointment Assigned', 'You have a new appointment.\n\nPatient: Jade Bandal\nDate: Monday, December 15, 2025\nTime: 9:00 AM\n\nThis appointment was scheduled by an administrator.', 0, 'System', NULL, '2025-12-14 16:26:25'),
(130, 'Admin', 17, '', 'Appointment Booked', 'You have successfully booked an appointment for Jade Bandal with Dr. Ma. Maria Doe on Monday, December 15, 2025 at 9:00 AM.', 0, 'System', NULL, '2025-12-14 16:26:25'),
(131, 'Patient', 15, '', 'Appointment Status Updated', 'Your appointment with Dr. Ma. Maria Doe on Monday, December 15, 2025 at 9:00 AM has been updated to: Ongoing', 0, 'System', NULL, '2025-12-14 16:36:36'),
(132, 'Doctor', 18, '', 'Appointment Status Updated', 'Appointment with Jade Bandal on Monday, December 15, 2025 at 9:00 AM has been updated to: Ongoing by an administrator.', 0, 'System', NULL, '2025-12-14 16:36:36'),
(133, 'Admin', 17, '', 'Appointment Status Updated', 'You have updated the appointment status for Jade Bandal with Dr. Ma. Maria Doe on Monday, December 15, 2025 at 9:00 AM to: Ongoing', 0, 'System', NULL, '2025-12-14 16:36:36'),
(134, 'Patient', 15, '', 'Appointment Status Updated', 'Your appointment with Dr. Ma. Maria Doe on Monday, December 15, 2025 at 9:00 AM has been updated to: Scheduled', 0, 'System', NULL, '2025-12-14 17:43:45'),
(135, 'Doctor', 18, '', 'Appointment Status Updated', 'Appointment with Jade Bandal on Monday, December 15, 2025 at 9:00 AM has been updated to: Scheduled by an administrator.', 0, 'System', NULL, '2025-12-14 17:43:45'),
(136, 'Admin', 17, '', 'Appointment Status Updated', 'You have updated the appointment status for Jade Bandal with Dr. Ma. Maria Doe on Monday, December 15, 2025 at 9:00 AM to: Scheduled', 0, 'System', NULL, '2025-12-14 17:43:45'),
(137, 'Patient', 15, '', 'Appointment Status Updated', 'Your appointment with Dr. Ma. Maria Doe on Monday, December 15, 2025 at 9:00 AM has been updated to: Ongoing', 0, 'System', NULL, '2025-12-14 17:44:00'),
(138, 'Doctor', 18, '', 'Appointment Status Updated', 'Appointment with Jade Bandal on Monday, December 15, 2025 at 9:00 AM has been updated to: Ongoing by an administrator.', 0, 'System', NULL, '2025-12-14 17:44:00'),
(139, 'Admin', 17, '', 'Appointment Status Updated', 'You have updated the appointment status for Jade Bandal with Dr. Ma. Maria Doe on Monday, December 15, 2025 at 9:00 AM to: Ongoing', 0, 'System', NULL, '2025-12-14 17:44:00'),
(140, 'Patient', 15, '', 'Appointment Status Updated', 'Your appointment with Dr. Ma. Maria Doe on Monday, December 15, 2025 at 9:00 AM has been updated to: Settled', 0, 'System', NULL, '2025-12-14 17:44:16'),
(141, 'Doctor', 18, '', 'Appointment Status Updated', 'Appointment with Jade Bandal on Monday, December 15, 2025 at 9:00 AM has been updated to: Settled by an administrator.', 0, 'System', NULL, '2025-12-14 17:44:16'),
(142, 'Admin', 17, '', 'Appointment Status Updated', 'You have updated the appointment status for Jade Bandal with Dr. Ma. Maria Doe on Monday, December 15, 2025 at 9:00 AM to: Settled', 0, 'System', NULL, '2025-12-14 17:44:16'),
(143, 'Patient', 15, '', 'Appointment Status Updated', 'Your appointment with Dr. Ma. Maria Doe on Monday, December 15, 2025 at 9:00 AM has been updated to: Cancelled\n\nCancellation Reason: DOCTOR EMERGENCY\n\nYou can book a new appointment through your account or by contacting us.', 0, 'System', NULL, '2025-12-14 17:44:34'),
(144, 'Doctor', 18, '', 'Appointment Status Updated', 'Appointment with Jade Bandal on Monday, December 15, 2025 at 9:00 AM has been updated to: Cancelled by an administrator.\n\nCancellation Reason: DOCTOR EMERGENCY', 0, 'System', NULL, '2025-12-14 17:44:34'),
(145, 'Admin', 17, '', 'Appointment Status Updated', 'You have updated the appointment status for Jade Bandal with Dr. Ma. Maria Doe on Monday, December 15, 2025 at 9:00 AM to: Cancelled\n\nCancellation Reason: DOCTOR EMERGENCY', 0, 'System', NULL, '2025-12-14 17:44:34'),
(146, 'Patient', 15, '', 'Medical Record Updated', 'Your medical record from November 26, 2025 has been updated by your doctor. Please review the changes in your medical records.', 0, 'System', NULL, '2025-12-14 19:26:15');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_otp`
--

CREATE TABLE `password_reset_otp` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `user_type` enum('admin','patient') NOT NULL,
  `user_id` int(11) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_otp`
--

INSERT INTO `password_reset_otp` (`id`, `email`, `otp_code`, `user_type`, `user_id`, `expires_at`, `used`, `created_at`) VALUES
(1, 'ramos112115mikaela@gmail.com', '416706', 'patient', 9, '2025-12-10 00:17:18', 1, '2025-12-09 16:02:18'),
(2, 'ramos112115mikaela@gmail.com', '161769', 'patient', 9, '2025-12-10 00:22:00', 1, '2025-12-09 16:07:00');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `patient_number` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `sex` enum('Male','Female','','') NOT NULL,
  `is_senior_citizen` tinyint(1) NOT NULL DEFAULT 0,
  `senior_citizen_id` varchar(20) NOT NULL,
  `is_pwd` tinyint(1) NOT NULL DEFAULT 0,
  `pwd_id` varchar(20) NOT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `address` text NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `emergency_contact_name` varchar(100) NOT NULL,
  `emergency_contact_phone` varchar(20) NOT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `chief_complaint` text DEFAULT NULL,
  `blood_type` varchar(5) NOT NULL,
  `allergies` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `patient_number`, `first_name`, `middle_name`, `last_name`, `suffix`, `date_of_birth`, `sex`, `is_senior_citizen`, `senior_citizen_id`, `is_pwd`, `pwd_id`, `civil_status`, `address`, `phone`, `email`, `emergency_contact_name`, `emergency_contact_phone`, `relationship`, `chief_complaint`, `blood_type`, `allergies`, `created_at`, `updated_at`) VALUES
(1, 'PT-2025-00001', 'Andrea', NULL, 'Malinis', NULL, '2003-08-15', 'Female', 0, '', 0, '', NULL, 'Dasma Cavite', '+639123456789', 'andrea@gmail.com', '', '', NULL, NULL, 'B+', '', '2025-06-04 08:51:38', '2025-06-04 08:51:38'),
(4, 'PT-2025-00003', 'Simeon', NULL, 'Daez', NULL, '1967-02-24', 'Male', 0, '', 0, '', NULL, 'LANGKAAN I, DASMARIÑAS CITY, CAVITE, REGION IV-A', '+639123456789', 'daez@gmail.com', 'Mrs. Daez', '', NULL, NULL, 'A+', 'none', '2025-06-05 06:40:29', '2025-06-05 06:40:29'),
(9, 'PT-2025-00006', 'Devi', 'Batongbakal', 'Cruz', '', '2001-06-25', 'Female', 0, '', 0, '', 'Single', 'SAN FRANCISCO, GENERAL TRIAS CITY, CAVITE, REGION IV-A', '+639123456789', 'devi@gmail.com', 'Mrs. Cruz', '+639123456789', 'Parent', 'abdominal pain', 'B+', '', '2025-07-31 04:12:46', '2025-07-31 04:12:46'),
(28, 'PT-2025-00013', 'Mikaela', 'Pontalba', 'Ramos', '', '2003-08-15', 'Female', 0, '', 1, 'PWD-NCR-QC-2025-0007', '', 'LANGKAAN II, DASMARIÑAS CITY, CAVITE, REGION IV-A', '+639123456789', 'ramos112115mikaela@gmail.com', 'Geline Ramos', '09123456789', 'Parent', 'Chest pain and head Ache', 'O+', '', '2025-11-04 22:36:43', '2025-11-29 10:46:56'),
(29, 'PT-2025-00014', 'Hannah', 'Roldan', 'Placio', '', '2002-09-16', 'Female', 0, '', 1, 'PWD-INDG-00087', 'Single', 'LANGKAAN I, DASMARIÑAS CITY, CAVITE, REGION IV-A', '+639162036179', 'hannahpauline1016@gmail.com', 'Selwin', '+639123456789', 'Guardian', 'Chest pain and vomiting', 'O+', '', '2025-11-06 05:10:05', '2025-11-06 05:19:16'),
(34, 'PT-2025-00015', 'Jade', '', 'Bandal', NULL, '2004-01-25', 'Female', 0, '', 0, '', NULL, 'DADO, DATU PIANG, MAGUINDANAO, BARMM', '+639055462311', 'pjsbandal9876@gmail.com', 'cristel', '+639087684043', 'Sibling', NULL, 'O+', '', '2025-11-25 00:32:48', '2025-11-30 10:43:51'),
(35, 'PT-2025-00016', 'Devina', '', 'Cruz', '', '2000-11-11', 'Female', 0, '', 0, '', 'Single', 'REAL, MONREAL, MASBATE, REGION V', '+639876543212', 'devinacruz@gmail.com', 'Analyn Cruz', '+639123456787', 'Parent', 'MIGRAINE', 'AB+', '', '2025-11-26 07:40:10', '2025-11-26 07:40:10'),
(37, 'PT-2025-00018', 'Eduardo', 'Roño', 'Lalu', 'II', '2003-10-05', 'Male', 0, '', 0, '', 'Single', 'CALUMPANG CERCA, INDANG, CAVITE, REGION IV-A', '+639669363741', 'ceraphina.keyl@gmail.com', 'Ruby', '+639123456789', 'Parent', 'masakit tyan', 'O+', '', '2025-11-27 03:13:47', '2025-11-27 04:30:16'),
(55, 'PT-2025-00023', 'Ma. Maria', '', 'Nadulas', '', '2000-01-01', 'Female', 0, '', 0, '', 'Single', 'PLARIDEL, SANTA ELENA, CAMARINES NORTE, REGION V', '+639123456789', 'bandal.princessjades@gmail.com', 'Keycee batongbak', '+639123456789', 'Spouse', NULL, 'AB+', '', '2025-12-06 17:14:57', '2025-12-06 17:14:57');

-- --------------------------------------------------------

--
-- Table structure for table `patient_registration_requests`
--

CREATE TABLE `patient_registration_requests` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `sex` enum('Male','Female','Other') NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `emergency_contact_name` varchar(100) NOT NULL,
  `emergency_contact_phone` varchar(20) NOT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `patient_type` enum('New','Existing') NOT NULL,
  `existing_patient_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_registration_requests`
--

INSERT INTO `patient_registration_requests` (`id`, `first_name`, `last_name`, `middle_name`, `date_of_birth`, `sex`, `phone`, `email`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `blood_type`, `allergies`, `medical_history`, `patient_type`, `existing_patient_id`, `username`, `password`, `status`, `admin_notes`, `processed_by`, `processed_at`, `created_at`, `updated_at`) VALUES
(38, 'Simeon', 'Daez', '', '1967-02-24', '', '', 'daez@gmail.com', '', '', '', NULL, NULL, '{\"suffix\":\"\",\"civil_status\":\"\",\"is_senior_citizen\":0,\"senior_citizen_id\":\"\",\"is_pwd\":0,\"pwd_id\":\"\",\"emergency_contact_relationship\":\"\",\"chief_complaint\":null}', 'Existing', NULL, '', 'cZcwIjA2aNi5', 'Pending', NULL, NULL, NULL, '2025-12-09 14:02:54', '2025-12-09 14:02:54');

-- --------------------------------------------------------

--
-- Table structure for table `patient_sessions`
--

CREATE TABLE `patient_sessions` (
  `id` int(11) NOT NULL,
  `patient_user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_users`
--

CREATE TABLE `patient_users` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('Pending','Active','Suspended','Rejected') DEFAULT 'Pending',
  `verification_token` varchar(255) DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `profile_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_users`
--

INSERT INTO `patient_users` (`id`, `patient_id`, `username`, `password`, `email`, `phone`, `status`, `verification_token`, `verification_expires`, `last_login`, `login_attempts`, `locked_until`, `created_at`, `updated_at`, `profile_image`) VALUES
(9, 28, 'mklrms', '$2y$10$0LXWT9Rhd09j7YElI0DesOXfA/Vrm3/XEWh8al5HVj7c3NQwE.qRS', 'ramos112115mikaela@gmail.com', '+639123456789', 'Active', NULL, NULL, '2025-12-10 00:07:44', 0, NULL, '2025-11-04 22:50:32', '2025-12-09 16:07:44', 'uploads/patient_profile_9_1762348731.jpg'),
(10, 29, 'hnnhpwln', '$2y$10$RBScDqNTgdptCV/s3wnyZu1UtYrPcrUyUf4NDhTpc0dl63s5NHY7K', 'hannahpauline1016@gmail.com', '09162036179', 'Active', NULL, NULL, '2025-11-06 16:14:33', 0, NULL, '2025-11-06 05:19:17', '2025-11-06 08:14:46', 'uploads/patient_profile_10_1762416886_690c58f67e3fa.jpg'),
(15, 34, 'PT-2025-00015', '$2y$10$Evxb7FXhWlvVNkvn3zpsIeXutXGFpp9D8EcCcFIrViHYg1SaWqcOK', 'pjsbandal9876@gmail.com', '+639055462311', 'Active', NULL, NULL, '2025-12-14 18:58:38', 0, NULL, '2025-11-25 00:32:49', '2025-12-14 10:58:38', 'uploads/patient_profile_15_1764140664_6926a678351d8.jpg'),
(17, 37, 'PT-2025-00018', '$2y$10$ax.ilhbAlu1DbFd/69/.7.clzbW0caLUw2sLL8McLO215qONEopVq', 'ceraphina.keyl@gmail.com', '+639669363741', 'Active', NULL, NULL, '2025-11-27 12:52:44', 0, NULL, '2025-11-27 03:44:34', '2025-11-27 04:52:44', NULL),
(20, 55, 'PT-2025-00023', '$2y$10$shXUN65NcY3z.1Hqrp9Fg.wtgPO2m3kb.P0lm/SK4Sipg1jbQv6A2', 'bandal.princessjades@gmail.com', '+639123456789', 'Suspended', NULL, NULL, NULL, 0, NULL, '2025-12-06 17:14:57', '2025-12-14 18:55:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `patient_vitals`
--

CREATE TABLE `patient_vitals` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `respiratory_rate` int(11) DEFAULT NULL,
  `temperature` float DEFAULT NULL,
  `oxygen_saturation` int(11) DEFAULT NULL,
  `weight` float DEFAULT NULL,
  `height` float DEFAULT NULL,
  `bmi` float DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_vitals`
--

INSERT INTO `patient_vitals` (`id`, `patient_id`, `visit_date`, `blood_pressure`, `heart_rate`, `respiratory_rate`, `temperature`, `oxygen_saturation`, `weight`, `height`, `bmi`, `notes`, `created_at`) VALUES
(1, 9, '2025-08-01', '120/70', 75, 16, 97, 98, 168, 52, 43.7, 'none', '2025-08-01 05:51:48'),
(2, 8, '2025-08-01', '120/70', 75, 16, 97, 98, 165, 70, 23.7, '', '2025-08-01 09:11:59'),
(3, 34, '2025-11-26', '120/80', 72, 16, 98, 98, 165, 68, 25.1, '', '2025-11-26 03:21:19');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `medication_name` varchar(255) NOT NULL,
  `dosage` varchar(100) NOT NULL,
  `frequency` varchar(100) NOT NULL,
  `duration` varchar(100) NOT NULL,
  `instructions` text DEFAULT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `date_prescribed` date NOT NULL DEFAULT curdate(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `patient_id`, `doctor_id`, `medication_name`, `dosage`, `frequency`, `duration`, `instructions`, `status`, `date_prescribed`, `created_at`) VALUES
(1, 9, 18, 'Amlodipine Besylate', '5mg 1 tablet', 'Twice daily', '7 days', 'sample instructions', 'active', '2025-07-31', '2025-07-31 04:15:03'),
(2, 28, 17, 'Hydrocodone Bitartrate and Acetaminophen', '500g', 'Every 6 hours', '5 days', 'Return if pain persists &gt;3 days or worsens', 'active', '2025-11-05', '2025-11-05 00:44:15'),
(3, 28, 18, 'Amlodipine Besylate', '5mg', 'Once daily', 'Maintainance', 'Drink it after meal', 'active', '2025-11-05', '2025-11-05 00:58:48'),
(5, 34, 17, 'Parafon Forte', '500mg', 'Twice daily', '7 days', '', 'active', '2025-11-26', '2025-11-26 06:49:21'),
(6, 34, 17, 'Adapalene', '500mg', 'Twice daily', '7 days', '', 'completed', '2025-11-27', '2025-11-26 18:18:06'),
(7, 34, 17, 'Adefovir Dipivoxil', '500mg', 'Twice daily', '2 weeks', '', 'active', '2025-11-27', '2025-11-26 18:57:04'),
(8, 34, 17, 'Abacavir Sulfate', '2mg', 'Three times daily', '2 weeks', '', 'active', '2024-11-27', '2025-11-26 18:59:29');

-- --------------------------------------------------------

--
-- Table structure for table `system_config`
--

CREATE TABLE `system_config` (
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_config`
--

INSERT INTO `system_config` (`config_key`, `config_value`, `updated_at`) VALUES
('last_monthly_cleanup', '2025-11-29 14:26:14', '2025-11-29 06:26:14'),
('last_registration_cleanup', '2025-12-14 15:45:17', '2025-12-14 07:45:17'),
('last_weekly_cleanup', '2025-12-14 15:45:17', '2025-12-14 07:45:17');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `fee_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_type` varchar(20) NOT NULL,
  `net_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','Gcash') NOT NULL DEFAULT 'Cash',
  `payment_status` enum('Pending','Completed','Refunded') NOT NULL DEFAULT 'Pending',
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `doctor_id` int(11) DEFAULT NULL,
  `transaction_time` time DEFAULT NULL,
  `id_no` varchar(100) DEFAULT NULL,
  `patient_name` varchar(255) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `sex` varchar(10) DEFAULT NULL,
  `patient_phone` varchar(20) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `labs_done` text DEFAULT NULL,
  `medications_supply` text DEFAULT NULL,
  `procedure_done` text DEFAULT NULL,
  `procedure_notes` text DEFAULT NULL,
  `other_fees_description` text DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `gross_amount` decimal(10,2) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `patient_id`, `fee_id`, `appointment_id`, `amount`, `discount_type`, `net_amount`, `payment_method`, `payment_status`, `transaction_date`, `created_by`, `notes`, `created_at`, `updated_at`, `doctor_id`, `transaction_time`, `id_no`, `patient_name`, `age`, `sex`, `patient_phone`, `diagnosis`, `labs_done`, `medications_supply`, `procedure_done`, `procedure_notes`, `other_fees_description`, `department`, `status`, `reference_number`, `gross_amount`, `discount_amount`) VALUES
(2, 1, 2, 3, 1500.00, '0.00', 1500.00, '', 'Completed', '2025-06-04 12:18:32', 17, '', '2025-06-04 12:18:32', '2025-06-04 12:18:49', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 4, 5, 4, 800.00, '0.00', 800.00, 'Cash', 'Completed', '2025-06-05 06:49:39', 17, '', '2025-06-05 06:49:39', '2025-06-05 06:49:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 1, 12, NULL, 265.00, 'pwd', 0.00, 'Cash', 'Pending', '2025-07-31 10:21:59', 17, '0', '2025-07-31 10:21:59', '2025-07-31 10:21:59', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 53.00),
(12, 9, 13, NULL, 600.00, 'none', 0.00, 'Cash', 'Completed', '2025-08-01 04:57:04', 17, '0', '2025-08-01 04:57:04', '2025-08-01 04:57:04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00),
(13, 9, 12, NULL, 210.00, 'none', 0.00, 'Cash', 'Completed', '2025-08-03 07:34:14', 17, '0', '2025-08-03 07:34:14', '2025-08-03 07:34:14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00),
(15, 28, 1, NULL, 2350.00, 'pwd', 0.00, 'Cash', 'Completed', '2025-11-05 15:02:07', 17, '0', '2025-11-05 15:02:07', '2025-11-05 15:02:07', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 470.00),
(17, 34, 1, NULL, 2000.00, 'none', 0.00, 'Cash', 'Completed', '2025-11-26 16:30:15', 17, '0', '2025-11-26 16:30:15', '2025-11-26 16:30:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00),
(18, 37, 24, NULL, 1370.00, 'none', 0.00, 'Cash', 'Completed', '2025-11-29 10:39:58', 25, '0', '2025-11-29 10:39:58', '2025-11-29 10:39:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `transaction_items`
--

CREATE TABLE `transaction_items` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `fee_id` int(11) NOT NULL,
  `fee_name` varchar(255) NOT NULL,
  `department` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transaction_items`
--

INSERT INTO `transaction_items` (`id`, `transaction_id`, `fee_id`, `fee_name`, `department`, `quantity`, `unit_price`, `total_price`, `total_amount`, `created_at`) VALUES
(1, 10, 13, '', '0', 1, 600.00, 600.00, 0.00, '2025-07-31 10:20:39'),
(2, 11, 12, '', '0', 1, 265.00, 265.00, 0.00, '2025-07-31 10:21:59'),
(3, 12, 13, '', '0', 1, 600.00, 600.00, 0.00, '2025-08-01 04:57:04'),
(4, 13, 12, '', '0', 1, 210.00, 210.00, 0.00, '2025-08-03 07:34:14'),
(6, 15, 1, '', '0', 1, 1000.00, 1000.00, 0.00, '2025-11-05 15:02:07'),
(7, 15, 26, '', '0', 1, 900.00, 900.00, 0.00, '2025-11-05 15:02:07'),
(8, 15, 38, '', '0', 1, 260.00, 260.00, 0.00, '2025-11-05 15:02:07'),
(9, 15, 23, '', '0', 1, 190.00, 190.00, 0.00, '2025-11-05 15:02:07'),
(10, 16, 18, '', '0', 1, 450.00, 450.00, 0.00, '2025-11-06 05:37:15'),
(11, 16, 23, '', '0', 1, 300.00, 300.00, 0.00, '2025-11-06 05:37:15'),
(12, 16, 13, '', '0', 1, 1500.00, 1500.00, 0.00, '2025-11-06 05:37:15'),
(13, 16, 51, '', '0', 1, 1790.00, 1790.00, 0.00, '2025-11-06 05:37:15'),
(14, 17, 1, '', '0', 1, 2000.00, 2000.00, 0.00, '2025-11-26 16:30:15'),
(15, 18, 24, '', '0', 1, 180.00, 180.00, 0.00, '2025-11-29 10:39:58'),
(16, 18, 23, '', '0', 1, 190.00, 190.00, 0.00, '2025-11-29 10:39:58'),
(17, 18, 1, '', '0', 1, 1000.00, 1000.00, 0.00, '2025-11-29 10:39:58');

-- --------------------------------------------------------

--
-- Stand-in structure for view `transaction_report_view`
-- (See below for the actual view)
--
CREATE TABLE `transaction_report_view` (
`transaction_id` int(11)
,`transaction_date` timestamp
,`transaction_time` time
,`id_no` varchar(100)
,`patient_name` varchar(255)
,`age` int(11)
,`sex` varchar(10)
,`patient_phone` varchar(20)
,`diagnosis` text
,`labs_done` text
,`medications_supply` text
,`procedure_done` text
,`procedure_notes` text
,`other_fees_description` text
,`amount` decimal(10,2)
,`gross_amount` decimal(10,2)
,`discount_amount` decimal(10,2)
,`net_amount` decimal(10,2)
,`mop` enum('Cash','Gcash')
,`payment_status` enum('Pending','Completed','Refunded')
,`department` varchar(100)
,`status` varchar(50)
,`reference_number` varchar(100)
,`notes` text
,`fee_name` varchar(100)
,`opd_amount` decimal(10,2)
,`er_amount` decimal(10,2)
,`inward_amount` decimal(10,2)
,`category_name` varchar(100)
,`category_id` int(11)
,`category_description` text
,`doctor_name` varchar(511)
,`doctor_id` int(11)
,`transaction_year` int(4)
,`transaction_month` int(2)
,`transaction_day` int(2)
,`transaction_date_only` date
,`department_full_name` varchar(100)
,`created_by_name` varchar(511)
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('Admin','Doctor') NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('Active','Inactive','','') NOT NULL DEFAULT 'Active',
  `profile_image` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `license_number` varchar(50) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `prc_number` varchar(50) DEFAULT NULL,
  `license_type` varchar(50) DEFAULT NULL,
  `prc_id_document` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `username`, `email`, `phone`, `address`, `role`, `department_id`, `specialization`, `password`, `status`, `profile_image`, `created_at`, `updated_at`, `license_number`, `is_available`, `prc_number`, `license_type`, `prc_id_document`, `last_login`) VALUES
(17, 'Juan', 'Dela Cruz', 'admin', 'admin@gmail.com', '+639123456788', 'GUINABOT, MIDSALIP, ZAMBOANGA DEL SUR, REGION IX', 'Admin', NULL, 'Cardiology', '$2y$10$c3bKo4IDYVN.wcbyXx3lqOcjtvWjLmJ05EvIWz3ZHG0pvpbgNu5by', 'Active', 'uploads/profile_17_1749025878.jpg', '2025-06-04 04:14:12', '2025-11-30 12:42:50', NULL, 1, NULL, NULL, NULL, '2025-12-15 00:22:21'),
(18, 'Ma. Maria', 'Doe', 'doctor', 'mariako@gmail.com', '+639999882823', 'MAYBOCOG, MAYDOLONG, EASTERN SAMAR, REGION VIII', 'Doctor', 3, 'Internal Medicine', '$2y$10$i9vVYhyx7nZ5paIuDopGCOGk7VuklEWFKr2pu28cWrhTQVd2iOchm', 'Active', 'uploads/profile_18_1764145081.jpg', '2025-06-04 04:50:20', '2025-11-30 11:57:01', NULL, 1, '0987654', 'RN', NULL, '2025-12-15 01:47:09'),
(25, 'Super', 'admin', 'superadmin', 'superadmin@gmail.com', '+639112333474', 'AMPAWID, LAAK (SAN VICENTE), COMPOSTELA VALLEY, REGION XI', 'Admin', NULL, '', '$2y$10$89wl3dojwTmSl1vG9F7Jseb9zMm6cLeHG3kb3PrvHI6vb7xplTjp2', 'Active', 'uploads/profile_25_1764494281.jpg', '2025-11-29 06:58:37', '2025-12-14 07:43:25', NULL, 1, NULL, NULL, NULL, '2025-12-09 23:06:08'),
(30, 'ryza mae', 'dizon', 'ryzamae', 'ryzamaedizon@gmail.com', '+639123456789', 'CASIGAYAN, TABUK CITY, KALINGA, CAR', 'Doctor', 7, 'Psychiatry', '$2y$10$BItcEZwpNjP1w5vGAg6N.e4B7moRwmHwDSAdgVoqudEyuROTLQ5Vq', 'Active', 'uploads/default-profile.png', '2025-12-06 17:42:48', '2025-12-06 17:42:48', NULL, 1, '098889', 'RPT', 'uploads/prc_1765042968_69346b1830527.jpg', NULL),
(31, 'Jack', 'Collin', 'docCollin', 'collinjoe@gmail.com', '+639123456789', 'WAWA I, ROSARIO, CAVITE, REGION IV-A', 'Doctor', 8, 'Surgery', '$2y$10$daEiZATFl6mKMOfdM9REEuVj7hnq8rXwDJxhd.bq0gWG1z3j7/ziy', 'Active', 'uploads/profile_1765289686_69382ed67d73f.jpeg', '2025-12-09 14:14:46', '2025-12-09 14:14:46', NULL, 1, '2023-123456', 'MD', 'uploads/prc_1765289686_69382ed67de6a.jpg', NULL);

-- --------------------------------------------------------

--
-- Structure for view `category_revenue_view`
--
DROP TABLE IF EXISTS `category_revenue_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `category_revenue_view`  AS SELECT `fc`.`id` AS `category_id`, `fc`.`name` AS `category_name`, `fc`.`description` AS `category_description`, count(`t`.`id`) AS `transaction_count`, sum(coalesce(`t`.`gross_amount`,`t`.`amount`)) AS `gross_revenue`, sum(coalesce(`t`.`discount_amount`,0)) AS `total_discounts`, sum(coalesce(`t`.`net_amount`,`t`.`amount`)) AS `net_revenue`, avg(coalesce(`t`.`net_amount`,`t`.`amount`)) AS `average_revenue`, count(distinct `t`.`fee_id`) AS `fees_used` FROM ((`fee_categories` `fc` left join `fees` `f` on(`fc`.`id` = `f`.`category_id`)) left join `transactions` `t` on(`f`.`id` = `t`.`fee_id` and `t`.`payment_status` = 'Completed')) GROUP BY `fc`.`id`, `fc`.`name`, `fc`.`description` ;

-- --------------------------------------------------------

--
-- Structure for view `department_performance_view`
--
DROP TABLE IF EXISTS `department_performance_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `department_performance_view`  AS SELECT `transactions`.`department` AS `department`, CASE WHEN `transactions`.`department` = 'opd' THEN 'Outpatient Department' WHEN `transactions`.`department` = 'er' THEN 'Emergency Room' WHEN `transactions`.`department` = 'inward' THEN 'Inpatient/Ward' ELSE `transactions`.`department` END AS `department_name`, count(0) AS `total_transactions`, sum(coalesce(`transactions`.`gross_amount`,`transactions`.`amount`)) AS `gross_revenue`, sum(coalesce(`transactions`.`discount_amount`,0)) AS `total_discounts`, sum(coalesce(`transactions`.`net_amount`,`transactions`.`amount`)) AS `net_revenue`, avg(coalesce(`transactions`.`net_amount`,`transactions`.`amount`)) AS `average_revenue`, count(distinct `transactions`.`fee_id`) AS `unique_services_used` FROM `transactions` WHERE `transactions`.`payment_status` = 'Completed' GROUP BY `transactions`.`department` ;

-- --------------------------------------------------------

--
-- Structure for view `doctor_performance_view`
--
DROP TABLE IF EXISTS `doctor_performance_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `doctor_performance_view`  AS SELECT `u`.`id` AS `doctor_id`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `doctor_name`, count(`t`.`id`) AS `total_transactions`, sum(coalesce(`t`.`gross_amount`,`t`.`amount`)) AS `gross_revenue`, sum(coalesce(`t`.`discount_amount`,0)) AS `total_discounts`, sum(coalesce(`t`.`net_amount`,`t`.`amount`)) AS `net_revenue`, avg(coalesce(`t`.`net_amount`,`t`.`amount`)) AS `average_revenue`, count(distinct cast(`t`.`transaction_date` as date)) AS `active_days` FROM (`users` `u` left join `transactions` `t` on(`u`.`id` = `t`.`doctor_id` and `t`.`payment_status` = 'Completed')) WHERE `u`.`role` in ('admin','staff') GROUP BY `u`.`id`, `u`.`first_name`, `u`.`last_name` ;

-- --------------------------------------------------------

--
-- Structure for view `monthly_revenue_view`
--
DROP TABLE IF EXISTS `monthly_revenue_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `monthly_revenue_view`  AS SELECT year(`transactions`.`transaction_date`) AS `year`, month(`transactions`.`transaction_date`) AS `month`, monthname(`transactions`.`transaction_date`) AS `month_name`, `transactions`.`department` AS `department`, count(0) AS `transaction_count`, sum(coalesce(`transactions`.`gross_amount`,`transactions`.`amount`)) AS `gross_revenue`, sum(coalesce(`transactions`.`discount_amount`,0)) AS `total_discounts`, sum(coalesce(`transactions`.`net_amount`,`transactions`.`amount`)) AS `net_revenue`, avg(coalesce(`transactions`.`net_amount`,`transactions`.`amount`)) AS `average_amount` FROM `transactions` WHERE `transactions`.`payment_status` = 'Completed' GROUP BY year(`transactions`.`transaction_date`), month(`transactions`.`transaction_date`), `transactions`.`department` ;

-- --------------------------------------------------------

--
-- Structure for view `transaction_report_view`
--
DROP TABLE IF EXISTS `transaction_report_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `transaction_report_view`  AS SELECT `t`.`id` AS `transaction_id`, `t`.`transaction_date` AS `transaction_date`, `t`.`transaction_time` AS `transaction_time`, `t`.`id_no` AS `id_no`, `t`.`patient_name` AS `patient_name`, `t`.`age` AS `age`, `t`.`sex` AS `sex`, `t`.`patient_phone` AS `patient_phone`, `t`.`diagnosis` AS `diagnosis`, `t`.`labs_done` AS `labs_done`, `t`.`medications_supply` AS `medications_supply`, `t`.`procedure_done` AS `procedure_done`, `t`.`procedure_notes` AS `procedure_notes`, `t`.`other_fees_description` AS `other_fees_description`, `t`.`amount` AS `amount`, coalesce(`t`.`gross_amount`,`t`.`amount`) AS `gross_amount`, coalesce(`t`.`discount_amount`,0) AS `discount_amount`, coalesce(`t`.`net_amount`,`t`.`amount`) AS `net_amount`, `t`.`payment_method` AS `mop`, `t`.`payment_status` AS `payment_status`, `t`.`department` AS `department`, `t`.`status` AS `status`, `t`.`reference_number` AS `reference_number`, `t`.`notes` AS `notes`, `f`.`name` AS `fee_name`, `f`.`opd_amount` AS `opd_amount`, `f`.`er_amount` AS `er_amount`, `f`.`inward_amount` AS `inward_amount`, `fc`.`name` AS `category_name`, `fc`.`id` AS `category_id`, `fc`.`description` AS `category_description`, coalesce(concat(`u`.`first_name`,' ',`u`.`last_name`),'N/A') AS `doctor_name`, `u`.`id` AS `doctor_id`, year(`t`.`transaction_date`) AS `transaction_year`, month(`t`.`transaction_date`) AS `transaction_month`, dayofmonth(`t`.`transaction_date`) AS `transaction_day`, cast(`t`.`transaction_date` as date) AS `transaction_date_only`, CASE WHEN `t`.`department` = 'opd' THEN 'Outpatient Department' WHEN `t`.`department` = 'er' THEN 'Emergency Room' WHEN `t`.`department` = 'inward' THEN 'Inpatient/Ward' ELSE `t`.`department` END AS `department_full_name`, coalesce(concat(`creator`.`first_name`,' ',`creator`.`last_name`),'System') AS `created_by_name`, `t`.`created_at` AS `created_at`, `t`.`updated_at` AS `updated_at` FROM ((((`transactions` `t` left join `fees` `f` on(`t`.`fee_id` = `f`.`id`)) left join `fee_categories` `fc` on(`f`.`category_id` = `fc`.`id`)) left join `users` `u` on(`t`.`doctor_id` = `u`.`id`)) left join `users` `creator` on(`t`.`created_by` = `creator`.`id`)) WHERE `t`.`payment_status` <> 'Cancelled' ORDER BY `t`.`transaction_date` DESC, `t`.`transaction_time` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_appointments_department` (`department_id`),
  ADD KEY `fk_appointments_doctor` (`doctor_id`),
  ADD KEY `idx_status_updated_at` (`status_updated_at`);

--
-- Indexes for table `appointment_requests`
--
ALTER TABLE `appointment_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_user_id` (`patient_user_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `idx_appointment_requests_status` (`status`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `doctor_departments`
--
ALTER TABLE `doctor_departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `doctor_department_unique` (`doctor_id`,`department_id`),
  ADD KEY `idx_doctor_id` (`doctor_id`),
  ADD KEY `idx_department_id` (`department_id`);

--
-- Indexes for table `doctor_leaves`
--
ALTER TABLE `doctor_leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doctor_id` (`doctor_id`),
  ADD KEY `idx_dates` (`start_date`,`end_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `doctor_day_unique` (`doctor_id`,`day_of_week`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_queue_status` (`status`,`scheduled_at`);

--
-- Indexes for table `fees`
--
ALTER TABLE `fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_fees_amounts` (`opd_amount`,`er_amount`,`inward_amount`);

--
-- Indexes for table `fee_categories`
--
ALTER TABLE `fee_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `medical_certificates`
--
ALTER TABLE `medical_certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `medical_history`
--
ALTER TABLE `medical_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_doctor_id` (`doctor_id`),
  ADD KEY `idx_history_type` (`history_type`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipient_id` (`recipient_id`),
  ADD KEY `idx_notifications_recipient` (`recipient_type`,`recipient_id`),
  ADD KEY `idx_notifications_unread` (`is_read`,`created_at`);

--
-- Indexes for table `password_reset_otp`
--
ALTER TABLE `password_reset_otp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_otp` (`email`,`otp_code`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `patient_number` (`patient_number`),
  ADD UNIQUE KEY `patient_number_2` (`patient_number`),
  ADD KEY `idx_patients_name` (`last_name`,`first_name`),
  ADD KEY `idx_patient_number` (`patient_number`);

--
-- Indexes for table `patient_registration_requests`
--
ALTER TABLE `patient_registration_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `existing_patient_id` (`existing_patient_id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_registration_requests_status` (`status`);

--
-- Indexes for table `patient_sessions`
--
ALTER TABLE `patient_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_user_id` (`patient_user_id`),
  ADD KEY `idx_session_token` (`session_token`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `patient_users`
--
ALTER TABLE `patient_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `unique_patient_email` (`patient_id`,`email`),
  ADD KEY `idx_patient_users_status` (`status`),
  ADD KEY `idx_patient_users_email` (`email`),
  ADD KEY `idx_patient_status` (`status`);

--
-- Indexes for table `patient_vitals`
--
ALTER TABLE `patient_vitals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`config_key`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `fee_id` (`fee_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_transactions_date_status` (`transaction_date`,`payment_status`),
  ADD KEY `idx_transactions_doctor_date` (`doctor_id`,`transaction_date`);

--
-- Indexes for table `transaction_items`
--
ALTER TABLE `transaction_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_fee_id` (`fee_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `appointment_requests`
--
ALTER TABLE `appointment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `doctor_departments`
--
ALTER TABLE `doctor_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `doctor_leaves`
--
ALTER TABLE `doctor_leaves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `fees`
--
ALTER TABLE `fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `fee_categories`
--
ALTER TABLE `fee_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `medical_certificates`
--
ALTER TABLE `medical_certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_history`
--
ALTER TABLE `medical_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT for table `password_reset_otp`
--
ALTER TABLE `password_reset_otp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `patient_registration_requests`
--
ALTER TABLE `patient_registration_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `patient_sessions`
--
ALTER TABLE `patient_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_users`
--
ALTER TABLE `patient_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `patient_vitals`
--
ALTER TABLE `patient_vitals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `transaction_items`
--
ALTER TABLE `transaction_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointments_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_appointments_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_requests`
--
ALTER TABLE `appointment_requests`
  ADD CONSTRAINT `appointment_requests_ibfk_1` FOREIGN KEY (`patient_user_id`) REFERENCES `patient_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_requests_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_requests_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_requests_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointment_requests_ibfk_5` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `doctors_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `doctor_departments`
--
ALTER TABLE `doctor_departments`
  ADD CONSTRAINT `fk_doctor_departments_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_doctor_departments_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD CONSTRAINT `doctor_schedules_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fees`
--
ALTER TABLE `fees`
  ADD CONSTRAINT `fees_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `fee_categories` (`id`),
  ADD CONSTRAINT `fk_fees_category` FOREIGN KEY (`category_id`) REFERENCES `fee_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_certificates`
--
ALTER TABLE `medical_certificates`
  ADD CONSTRAINT `medical_certificates_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_history`
--
ALTER TABLE `medical_history`
  ADD CONSTRAINT `fk_medical_history_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_medical_history_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_registration_requests`
--
ALTER TABLE `patient_registration_requests`
  ADD CONSTRAINT `patient_registration_requests_ibfk_1` FOREIGN KEY (`existing_patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `patient_registration_requests_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
