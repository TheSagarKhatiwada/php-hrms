-- =============================================
-- MODIFIED BACKUP FILE FOR NEW HRMS SYSTEM
-- =============================================
-- This file contains the data from your server backup
-- but modified to match your new application structure
-- =============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- =============================================
-- EMPLOYEES TABLE - NEW STRUCTURE
-- =============================================

CREATE TABLE `employees` (
  `emp_id` varchar(20) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `office_email` varchar(100) DEFAULT NULL,
  `office_phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `hire_date` date NOT NULL,
  `join_date` date DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `exit_note` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `designation_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `designation` int(11) DEFAULT NULL,
  `branch` varchar(10) NOT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `role_id` int(11) NOT NULL DEFAULT 2,
  `board_position_id` int(11) DEFAULT NULL,
  `mach_id` varchar(20) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `user_image` varchar(255) DEFAULT NULL,
  `login_access` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive','terminated') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `gender` enum('male','female','other') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert employee data with transformed structure
INSERT INTO `employees` (`emp_id`, `employee_name`, `first_name`, `middle_name`, `last_name`, `email`, `password`, `phone`, `office_email`, `office_phone`, `date_of_birth`, `hire_date`, `join_date`, `exit_date`, `exit_note`, `designation`, `branch`, `role_id`, `mach_id`, `user_image`, `login_access`, `gender`, `created_at`, `updated_at`) VALUES
('101', 'Sagar  Khatiwada', 'Sagar', '', 'Khatiwada', 'sagar.khatiwada@primeexpress.com.np', '$2y$10$JfH35uINMHZhnZ./2x06puMUvJmWWaajkZwph8LdMSuKs0XnzAFKe', '9863629512', 'sagar.khatiwada@primeexpress.com.np', '', '1995-08-11', '2017-10-27', '2017-10-27', NULL, NULL, 26, '1', 1, '2', 'resources/userimg/uploads/683588179457d.png', 1, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('102', 'Pawan Kumar Karki', 'Pawan', 'Kumar', 'Karki', 'pawan.karki@primeexpress.com.np', '', '9851273944', NULL, '', '2000-01-01', '2025-01-25', '2025-01-25', NULL, NULL, 27, '1', 4, '3', 'resources/userimg/uploads/683c255dcddf4.png', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('103', 'Rama  GC', 'Rama', '', 'GC', 'rama.gc@primeexpress.com.np', '', '9800000000', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', NULL, NULL, 28, '1', 3, '4', 'resources/userimg/default-image.jpg', 0, 'female', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('104', 'Dilliram  Kadariya', 'Dilliram', '', 'Kadariya', 'dilliram.kadariya@primeexpress.com.np', '', '9851273953', NULL, NULL, '1968-08-24', '2025-01-25', '2025-01-25', NULL, NULL, 29, '1', 3, '5', 'resources/userimg/default-image.jpg', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('105', 'Bikash  Karki', 'Bikash', '', 'Karki', 'bikash.karki@primeexpress.com.np', '', '9800000000', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', NULL, NULL, 30, '1', 3, '7', 'resources/userimg/default-image.jpg', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('106', 'Parbata  Niroula', 'Parbata', '', 'Niroula', 'parbata.niroula@primeexpress.com.np', '', '9851273974', NULL, '', '1991-06-07', '2023-06-21', '2023-06-21', NULL, NULL, 31, '1', 3, '8', 'resources/userimg/uploads/68383e44355b5.png', 0, 'female', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('107', 'Ram  Chandra  Baral', 'Ram ', 'Chandra ', 'Baral', 'ramchandra.baral@primeexpress.com.np', '$2y$10$SVNBeCc6lwv0N6mlCpHRduXi569tmoKy9VND91sfg77bwYiqq7PbG', '9851273941', NULL, '', '2000-01-01', '2025-01-25', '2025-01-25', NULL, NULL, 32, '1', 1, '9', 'resources/userimg/uploads/683c2586a4616.png', 1, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('108', 'Samjhana  Bhandari', 'Samjhana', '', 'Bhandari', 'samjhana.bhandari@primeexpress.com.np', '', '9851273969', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', NULL, NULL, 33, '1', 3, '10', 'resources/userimg/default-image.jpg', 0, 'female', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('109', 'Nirajan  Karki', 'Nirajan', '', 'Karki', 'nirajan.karki@primeexpress.com.np', '', '9800000000', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', NULL, NULL, 34, '1', 3, '11', 'resources/userimg/default-image.jpg', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('110', 'Mahesh  Thapa', 'Mahesh', '', 'Thapa', 'mahesh.thapa@primeexpress.com.np', '', '9851273950', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', NULL, NULL, 35, '1', 3, '12', 'resources/userimg/default-image.jpg', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('111', 'Imesh Raj Bhandari', 'Imesh', 'Raj', 'Bhandari', 'imeshraj.bhandari@primeexpress.com.np', '', '9851273969', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', '2025-03-21', 'Resignation', 33, '1', 3, '13', 'resources/userimg/default-image.jpg', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('112', 'Krishna  Rawal', 'Krishna', '', 'Rawal', 'krishna.rawal@primeexpress.com.np', '', '9851273952', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', NULL, NULL, 36, '1', 3, '15', 'resources/userimg/default-image.jpg', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('113', 'Krishna Bahadur Raut', 'Krishna', 'Bahadur', 'Raut', 'krishna.rawat@primeexpress.com.np', '', '9851273968', NULL, NULL, '1980-05-17', '2025-01-25', '2025-01-25', NULL, NULL, 34, '1', 3, '18', 'resources/userimg/default-image.jpg', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('114', 'Tej  Limbu', 'Tej', '', 'Limbu', 'tej.limbu@primeexpress.com.np', '', '9800000000', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', '2025-02-21', 'New opportunity', 34, '1', 3, '19', 'resources/userimg/default-image.jpg', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('115', 'Bhupendra  Karki', 'Bhupendra', '', 'Karki', 'bhupendra.karki@primeexpress.com.np', '', '9800000000', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', NULL, NULL, 34, '1', 3, '20', 'resources/userimg/default-image.jpg', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('116', 'Sagar  Basnet', 'Sagar', '', 'Basnet', 'sagar.basnet@primeexpress.com.np', '', '9851273980', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', NULL, NULL, 36, '1', 3, '22', 'resources/userimg/default-image.jpg', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('117', 'Samjhana  Dahal', 'Samjhana', '', 'Dahal', 'samjhana.dahal@primeexpress.com.np', '', '9851273976', NULL, '', '1993-02-08', '2024-07-02', '2024-07-02', '2025-06-13', 'Due to personal reasons. ', 33, '1', 3, '24', 'resources/userimg/default-image.jpg', 0, 'female', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('118', 'Durga Prasad Dhimal', 'Durga', 'Prasad', 'Dhimal', 'durga.dhimal@primeexpress.com.np', '', '9851412944', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', NULL, NULL, 36, '1', 3, '21', 'resources/userimg/default-image.jpg', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('119', 'Sundar  Giri', 'Sundar', '', 'Giri', 'sundar.giri@primeexpress.com.np', '', '9800000000', NULL, '', '1973-05-11', '2025-01-25', '2025-01-25', NULL, NULL, 36, '1', 3, '23', 'resources/userimg/default-image.jpg', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('120', 'Prakash  Raut', 'Prakash', '', 'Raut', 'prakash.raut@primeexpress.com.np', '$2y$10$TU36B7UodRL40EoFtqW.mexJB0yEEUfYCoOJ7yiOSvibV0IiyQCEK', '9851002610', NULL, '', '1995-06-08', '2013-01-01', '2013-01-01', NULL, NULL, 38, '1', 4, '1', 'resources/userimg/uploads/68383ef30991f.png', 1, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('121', 'Nabina  Baral', 'Nabina', '', 'Baral', 'Support@primeexpress.com.np', '', '9851273976', 'Support@primeexpress.com.np', '9851273976', '2000-11-18', '2025-05-04', '2025-05-04', NULL, NULL, 33, '1', 4, '25', 'resources/userimg/default-image.jpg', 1, 'female', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('122', 'Kristina  Raut', 'Kristina', '', 'Raut', 'rautkristina127@gmail.com', '', '9702787307', '', '', '2006-12-25', '2025-05-18', '2025-05-18', NULL, NULL, 39, '1', 6, '0', 'resources/userimg/uploads/68383da17aab8.png', 0, 'female', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('501', 'Ganga  Basnet', 'Ganga', '', 'Basnet', 'ganga.basnet@primeexpress.com.np', '', '9851360941', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', NULL, NULL, 37, '5', 3, '0', 'resources/userimg/default-image.jpg', 0, 'female', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('502', 'Sukra Raj Limbu', 'Sukra', 'Raj', 'Limbu', 'sukraraj.limbu@primeexpress.com.np', '', '9851360942', NULL, NULL, '2000-02-28', '2025-01-25', '2025-01-25', NULL, NULL, 37, '5', 3, '0', 'resources/userimg/default-image.jpg', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('601', 'Prakash  Gurung', 'Prakash', '', 'Gurung', 'prakash.gurung@primeexpress.com.np', '', '9851359976', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', '2025-02-27', '', 37, '6', 3, '0', 'resources/userimg/default-image.jpg', 0, 'male', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('602', 'Indrakala  KC', 'Indrakala', '', 'KC', 'indrakala.kc@primeexpress.com.np', '', '9851359676', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', NULL, NULL, 37, '6', 3, '0', 'resources/userimg/default-image.jpg', 0, 'female', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
('603', 'Sita  Rijal', 'Sita', '', 'Rijal', 'sita.rijal@primeexpress.com.np', '', '9851360947', NULL, NULL, '2000-01-01', '2025-01-25', '2025-01-25', NULL, NULL, 37, '6', 3, '0', 'resources/userimg/default-image.jpg', 0, 'female', '2025-06-17 03:51:00', '2025-06-17 03:51:00');

-- Add PRIMARY KEY
ALTER TABLE `employees` ADD PRIMARY KEY (`emp_id`);

-- Add indexes
ALTER TABLE `employees` ADD UNIQUE KEY `email` (`email`);
ALTER TABLE `employees` ADD KEY `idx_branch` (`branch`);
ALTER TABLE `employees` ADD KEY `idx_role_id` (`role_id`);
ALTER TABLE `employees` ADD KEY `idx_status` (`status`);

-- =============================================
-- BRANCHES TABLE
-- =============================================

CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `branches` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'Main Branch Kathmandu', '2020-08-13 07:36:43', '2024-12-17 16:03:30'),
(2, 'Prime Express Butwal', '2020-08-13 07:36:43', '2024-11-06 20:25:30'),
(3, 'Prime Express Chitwan', '2020-09-11 19:54:05', '2024-05-14 15:53:09'),
(4, 'Prime Express Birtamod', '2020-09-11 19:55:02', '2025-01-20 17:26:33'),
(5, 'Prime Express Damak', '2020-09-11 19:55:26', '2024-03-20 16:07:39'),
(6, 'Prime Express Pokhara', '2021-01-23 09:55:50', '2024-02-27 21:29:05'),
(7, 'Prime Express Int\'l', '2021-09-22 11:18:43', '2024-12-17 16:04:15'),
(8, 'Prime Express Damauli', '2022-09-08 17:23:09', '2024-12-17 16:04:46'),
(9, 'Prime Express Biratnagar', '2022-10-14 17:25:46', '2024-02-27 21:27:28'),
(10, 'Prime Express Thamel', '2023-04-23 16:58:32', '2024-02-27 21:27:05'),
(11, 'Prime Express Nepalgunj', '2023-06-25 16:54:55', '2024-02-27 21:26:44'),
(12, 'Prime Express Dhangadi', '2023-06-25 17:15:25', '2023-12-24 12:29:47');

-- =============================================
-- DESIGNATIONS TABLE
-- =============================================

CREATE TABLE `designations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `department_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `designations` (`id`, `title`, `description`, `department_id`, `created_at`, `updated_at`) VALUES
(26, 'MIS Manager', NULL, NULL, '2025-05-20 00:30:00', '2025-05-20 00:30:00'),
(27, 'Operation Manager', NULL, NULL, '2025-05-20 00:30:00', '2025-05-20 00:30:00'),
(28, 'Office Helper', NULL, NULL, '2025-05-20 00:30:00', '2025-05-20 00:30:00'),
(29, 'Marketing Manager', NULL, NULL, '2025-05-20 00:30:00', '2025-05-20 00:30:00'),
(30, 'Cashier', NULL, NULL, '2025-05-20 00:30:00', '2025-05-20 00:30:00'),
(31, 'Account Officer', NULL, NULL, '2025-05-20 00:30:00', '2025-05-20 00:30:00'),
(32, 'Account Manager', NULL, NULL, '2025-05-20 00:30:00', '2025-05-20 00:30:00'),
(33, 'Customer Relationship Officer', NULL, NULL, '2025-05-20 00:30:00', '2025-05-20 00:30:00'),
(34, 'Delivery Assistant', NULL, NULL, '2025-05-20 00:30:00', '2025-05-20 00:30:00'),
(35, 'Market Representative', NULL, NULL, '2025-05-20 00:30:00', '2025-05-20 00:30:00'),
(36, 'Driver', NULL, NULL, '2025-05-20 00:30:00', '2025-05-20 00:30:00'),
(37, 'Sales Officer', NULL, NULL, '2025-05-20 00:30:00', '2025-05-20 00:30:00'),
(38, 'General Manager', NULL, NULL, '2025-05-20 00:30:00', '2025-05-20 00:30:00'),
(39, 'Intern', 'Learning how things were done at company.', NULL, '2025-05-29 10:47:26', '2025-05-29 10:47:26');

-- =============================================
-- ROLES TABLE
-- =============================================

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'System Administrator', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
(2, 'Employee', 'Regular Employee', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
(3, 'Employee', 'Regular Employee', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
(4, 'Manager', 'Manager Role', '2025-06-17 03:51:00', '2025-06-17 03:51:00'),
(6, 'Intern', 'Intern Role', '2025-06-17 03:51:00', '2025-06-17 03:51:00');

-- =============================================
-- ATTENDANCE_LOGS TABLE - UPDATED STRUCTURE
-- =============================================

CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mach_sn` int(11) NOT NULL,
  `mach_id` int(5) NOT NULL,
  `emp_Id` varchar(20) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `method` enum('0','1','2') NOT NULL DEFAULT '0',
  `manual_reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_emp_date` (`emp_Id`, `date`),
  KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Note: Attendance logs data would need to be transformed from int emp_id to varchar
-- This would be handled during actual migration

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
