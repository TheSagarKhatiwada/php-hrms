-- HRMS Database Schema
-- Installation Schema for PHP HRMS System
-- Created: 2025-06-15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Database: `hrms`

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('admin','hr','employee','manager') NOT NULL DEFAULT 'employee',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `emp_id` varchar(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `gender` enum('M','F','Other') DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `office_email` varchar(100) DEFAULT NULL,
  `office_phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `hire_date` date NOT NULL,
  `join_date` date DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `designation_id` int(11) DEFAULT NULL,
  `designation` int(11) DEFAULT NULL,
  `branch` int(11) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `board_position_id` int(11) DEFAULT NULL,
  `mach_id` varchar(20) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `user_image` varchar(255) DEFAULT NULL,
  `login_access` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive','terminated') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  UNIQUE KEY `emp_id` (`emp_id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `designation_id` (`designation_id`),
  KEY `designation` (`designation`),
  KEY `branch` (`branch`),
  KEY `manager_id` (`manager_id`),
  KEY `supervisor_id` (`supervisor_id`),
  KEY `role_id` (`role_id`),
  KEY `board_position_id` (`board_position_id`),
  KEY `mach_id` (`mach_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `manager_id` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `designations`
--

CREATE TABLE `designations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `break_time` int(11) DEFAULT 0,
  `total_hours` decimal(4,2) DEFAULT NULL,
  `status` enum('present','absent','late','half_day','holiday') NOT NULL DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_date` (`employee_id`, `date`),
  KEY `date` (`date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leaves`
--

CREATE TABLE `leaves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  KEY `status` (`status`),
  KEY `start_date` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `days_allowed` int(11) NOT NULL DEFAULT 0,
  `carry_forward` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('national','religious','company') NOT NULL DEFAULT 'company',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `date` (`date`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `type` enum('text','number','boolean','json') NOT NULL DEFAULT 'text',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `db_migrations`
--

CREATE TABLE `db_migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `migration_name` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL DEFAULT 1,
  `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `execution_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration_name` (`migration_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL,
  `reason` text NOT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` varchar(20) DEFAULT NULL,
  `approved_date` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `reviewed_date` timestamp NULL DEFAULT NULL,
  `is_half_day` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  KEY `status` (`status`),
  KEY `start_date` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`),
  KEY `manager_id` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permission` (`role_id`, `permission_id`),
  KEY `role_id` (`role_id`),
  KEY `permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `board_of_directors`
--

CREATE TABLE `board_of_directors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL,
  `position_level` int(11) NOT NULL DEFAULT 1,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `appointment_date` date DEFAULT NULL,
  `is_independent` tinyint(1) NOT NULL DEFAULT 0,
  `expertise_areas` text DEFAULT NULL,
  `status` enum('active','inactive','retired') NOT NULL DEFAULT 'active',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `board_positions`
--

CREATE TABLE `board_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `level` int(11) NOT NULL DEFAULT 1,
  `responsibilities` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `board_committees`
--

CREATE TABLE `board_committees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `chairman_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `chairman_id` (`chairman_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `board_committee_members`
--

CREATE TABLE `board_committee_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `committee_id` int(11) NOT NULL,
  `board_member_id` int(11) NOT NULL,
  `role` enum('chairman','member','secretary') NOT NULL DEFAULT 'member',
  `appointed_date` date DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `committee_member` (`committee_id`, `board_member_id`),
  KEY `committee_id` (`committee_id`),
  KEY `board_member_id` (`board_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `board_meetings`
--

CREATE TABLE `board_meetings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `meeting_date` datetime NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `agenda` text DEFAULT NULL,
  `minutes` text DEFAULT NULL,
  `status` enum('scheduled','ongoing','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `meeting_date` (`meeting_date`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mach_sn` varchar(50) DEFAULT NULL,
  `mach_id` int(11) DEFAULT NULL,
  `emp_Id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `method` int(11) DEFAULT 0,
  `manual_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `emp_Id` (`emp_Id`),
  KEY `date` (`date`),
  KEY `mach_id` (`mach_id`),
  KEY `mach_sn` (`mach_sn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assetcategories`
--

CREATE TABLE `assetcategories` (
  `CategoryID` int(11) NOT NULL AUTO_INCREMENT,
  `CategoryShortCode` varchar(10) NOT NULL,
  `CategoryName` varchar(100) NOT NULL,
  `Description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`CategoryID`),
  UNIQUE KEY `CategoryShortCode` (`CategoryShortCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fixedassets`
--

CREATE TABLE `fixedassets` (
  `AssetID` int(11) NOT NULL AUTO_INCREMENT,
  `AssetName` varchar(200) NOT NULL,
  `CategoryID` int(11) NOT NULL,
  `AssetSerial` varchar(50) NOT NULL,
  `PurchaseDate` date NOT NULL,
  `PurchaseCost` decimal(10,2) NOT NULL,
  `WarrantyEndDate` date DEFAULT NULL,
  `AssetCondition` enum('Excellent','Good','Fair','Poor') DEFAULT 'Good',
  `AssetLocation` varchar(200) DEFAULT NULL,
  `AssetsDescription` text DEFAULT NULL,
  `Status` enum('Available','Assigned','Maintenance','Retired') DEFAULT 'Available',
  `AssetImage` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`AssetID`),
  UNIQUE KEY `AssetSerial` (`AssetSerial`),
  KEY `CategoryID` (`CategoryID`),
  FOREIGN KEY (`CategoryID`) REFERENCES `assetcategories` (`CategoryID`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assetassignments`
--

CREATE TABLE `assetassignments` (
  `AssignmentID` int(11) NOT NULL AUTO_INCREMENT,
  `AssetID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `AssignmentDate` date NOT NULL,
  `ExpectedReturnDate` date DEFAULT NULL,
  `ReturnDate` date DEFAULT NULL,
  `Notes` text DEFAULT NULL,
  `ReturnNotes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`AssignmentID`),
  KEY `AssetID` (`AssetID`),
  KEY `EmployeeID` (`EmployeeID`),
  FOREIGN KEY (`AssetID`) REFERENCES `fixedassets` (`AssetID`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assetmaintenance`
--

CREATE TABLE `assetmaintenance` (
  `RecordID` int(11) NOT NULL AUTO_INCREMENT,
  `AssetID` int(11) NOT NULL,
  `MaintenanceDate` date NOT NULL,
  `MaintenanceType` enum('Preventive','Corrective','Emergency','Routine') NOT NULL,
  `Description` text NOT NULL,
  `Cost` decimal(10,2) DEFAULT 0.00,
  `MaintenancePerformBy` varchar(200) DEFAULT NULL,
  `MaintenanceStatus` enum('Scheduled','In Progress','Completed','Cancelled') DEFAULT 'Scheduled',
  `CompletionDate` date DEFAULT NULL,
  `CompletionNotes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`RecordID`),
  KEY `AssetID` (`AssetID`),
  FOREIGN KEY (`AssetID`) REFERENCES `fixedassets` (`AssetID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_config`
--

CREATE TABLE `sms_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `sender` varchar(50) NOT NULL,
  `status` enum('pending','sent','failed','delivered') NOT NULL DEFAULT 'pending',
  `message_id` varchar(100) DEFAULT NULL,
  `response_data` json DEFAULT NULL,
  `cost` decimal(10,4) DEFAULT 0.0000,
  `employee_id` int(11) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `campaign_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `phone_number` (`phone_number`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  KEY `employee_id` (`employee_id`),
  KEY `template_id` (`template_id`),
  KEY `campaign_id` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_templates`
--

CREATE TABLE `sms_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `content` text DEFAULT NULL,
  `variables` json DEFAULT NULL,
  `category` enum('attendance','payroll','general','alerts','reminders') NOT NULL DEFAULT 'general',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `is_active` (`is_active`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_campaigns`
--

CREATE TABLE `sms_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `recipient_type` enum('all','department','designation','custom') NOT NULL DEFAULT 'custom',
  `recipient_criteria` json DEFAULT NULL,
  `status` enum('draft','scheduled','sending','completed','failed') NOT NULL DEFAULT 'draft',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `total_recipients` int(11) NOT NULL DEFAULT 0,
  `sent_count` int(11) NOT NULL DEFAULT 0,
  `failed_count` int(11) NOT NULL DEFAULT 0,
  `total_cost` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_sender_identities`
--

CREATE TABLE `sms_sender_identities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identity` varchar(11) NOT NULL,
  `description` varchar(200) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identity` (`identity`),
  KEY `is_default` (`is_default`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Add Foreign Key Constraints
--

ALTER TABLE `employees`
  ADD CONSTRAINT `employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_designation_id_foreign` FOREIGN KEY (`designation_id`) REFERENCES `designations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_designation_foreign` FOREIGN KEY (`designation`) REFERENCES `designations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_branch_foreign` FOREIGN KEY (`branch`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_supervisor_id_foreign` FOREIGN KEY (`supervisor_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_board_position_id_foreign` FOREIGN KEY (`board_position_id`) REFERENCES `board_positions` (`id`) ON DELETE SET NULL;

ALTER TABLE `departments`
  ADD CONSTRAINT `departments_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

ALTER TABLE `designations`
  ADD CONSTRAINT `designations_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

ALTER TABLE `branches`
  ADD CONSTRAINT `branches_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

ALTER TABLE `leaves`
  ADD CONSTRAINT `leaves_leave_type_id_foreign` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE;

ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_leave_type_id_foreign` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE;

ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

ALTER TABLE `board_committees`
  ADD CONSTRAINT `board_committees_chairman_id_foreign` FOREIGN KEY (`chairman_id`) REFERENCES `board_of_directors` (`id`) ON DELETE SET NULL;

ALTER TABLE `board_committee_members`
  ADD CONSTRAINT `board_committee_members_committee_id_foreign` FOREIGN KEY (`committee_id`) REFERENCES `board_committees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `board_committee_members_board_member_id_foreign` FOREIGN KEY (`board_member_id`) REFERENCES `board_of_directors` (`id`) ON DELETE CASCADE;

ALTER TABLE `board_meetings`
  ADD CONSTRAINT `board_meetings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `sms_logs`
  ADD CONSTRAINT `sms_logs_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sms_logs_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `sms_templates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sms_logs_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `sms_campaigns` (`id`) ON DELETE SET NULL;

ALTER TABLE `sms_templates`
  ADD CONSTRAINT `sms_templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `sms_campaigns`
  ADD CONSTRAINT `sms_campaigns_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `sms_templates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sms_campaigns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- --------------------------------------------------------

--
-- Insert Default Data
--

-- Default Leave Types
INSERT INTO `leave_types` (`name`, `code`, `description`, `days_allowed`, `carry_forward`, `is_active`, `status`) VALUES
('Annual Leave', 'annual', 'Yearly vacation leave', 21, 1, 1, 'active'),
('Sick Leave', 'sick', 'Medical leave for illness', 10, 0, 1, 'active'),
('Personal Leave', 'personal', 'Personal time off', 5, 0, 1, 'active'),
('Casual Leave', 'casual', 'Casual time off', 12, 0, 1, 'active'),
('Maternity Leave', 'maternity', 'Maternity leave for new mothers', 90, 0, 1, 'active'),
('Paternity Leave', 'paternity', 'Paternity leave for new fathers', 15, 0, 1, 'active'),
('Emergency Leave', 'emergency', 'Emergency situations', 3, 0, 1, 'active');

-- Default Departments
INSERT INTO `departments` (`name`, `description`, `status`) VALUES
('Human Resources', 'Human Resources and Administration', 'active'),
('Information Technology', 'IT Development and Support', 'active'),
('Finance', 'Finance and Accounting', 'active'),
('Marketing', 'Marketing and Sales', 'active'),
('Operations', 'Operations and Management', 'active');

-- Default Designations
INSERT INTO `designations` (`title`, `description`, `department_id`, `status`) VALUES
('CEO', 'Chief Executive Officer', 5, 'active'),
('HR Manager', 'Human Resources Manager', 1, 'active'),
('IT Manager', 'Information Technology Manager', 2, 'active'),
('Finance Manager', 'Finance and Accounting Manager', 3, 'active'),
('Marketing Manager', 'Marketing and Sales Manager', 4, 'active'),
('Software Developer', 'Software Development Engineer', 2, 'active'),
('HR Executive', 'Human Resources Executive', 1, 'active'),
('Accountant', 'Finance Accountant', 3, 'active'),
('Marketing Executive', 'Marketing Executive', 4, 'active'),
('Operations Executive', 'Operations Executive', 5, 'active');

-- Default Roles
INSERT INTO `roles` (`name`, `description`, `permissions`, `status`) VALUES
('admin', 'System Administrator', '["all"]', 'active'),
('hr', 'Human Resources', '["employees", "attendance", "leaves", "reports"]', 'active'),
('manager', 'Department Manager', '["attendance", "leaves", "reports"]', 'active'),
('employee', 'Regular Employee', '["profile", "attendance", "leaves"]', 'active');

-- Default Settings
INSERT INTO `settings` (`setting_key`, `value`, `description`, `type`) VALUES
('app_name', 'HRMS', 'Application Name', 'text'),
('company_name', 'Your Company', 'Company Name', 'text'),
('company_full_name', 'Your Company Full Name', 'Company Full Name', 'text'),
('timezone', 'UTC', 'Default Timezone', 'text'),
('date_format', 'Y-m-d', 'Date Format', 'text'),
('time_format', 'H:i:s', 'Time Format', 'text'),
('working_hours_per_day', '8', 'Standard Working Hours Per Day', 'number'),
('working_days_per_week', '5', 'Standard Working Days Per Week', 'number'),
('currency', 'USD', 'Default Currency', 'text'),
('attendance_grace_period', '15', 'Grace Period for Late Arrival (minutes)', 'number'),
('auto_punch_out', '18:00', 'Automatic Punch Out Time', 'text'),
('leave_approval_required', '1', 'Leave Approval Required', 'boolean'),
('backup_retention_days', '30', 'Backup Retention Period (days)', 'number');

-- Default Branches
INSERT INTO `branches` (`name`, `code`, `address`, `status`) VALUES
('Head Office', 'HO', 'Main Corporate Office', 'active'),
('Branch Office 1', 'BR1', 'Regional Branch Office', 'active'),
('Branch Office 2', 'BR2', 'Secondary Branch Office', 'active');

-- Default Permissions
INSERT INTO `permissions` (`name`, `description`, `category`) VALUES
('view_employees', 'View employee records', 'employees'),
('add_employees', 'Add new employees', 'employees'),
('edit_employees', 'Edit employee records', 'employees'),
('delete_employees', 'Delete employee records', 'employees'),
('view_attendance', 'View attendance records', 'attendance'),
('manage_attendance', 'Manage attendance records', 'attendance'),
('view_leaves', 'View leave requests', 'leaves'),
('manage_leaves', 'Manage leave requests', 'leaves'),
('view_reports', 'View system reports', 'reports'),
('manage_system', 'Manage system settings', 'system'),
('view_notifications', 'View notifications', 'notifications'),
('send_notifications', 'Send notifications', 'notifications');

-- Default Board Positions
INSERT INTO `board_positions` (`title`, `description`, `level`) VALUES
('Chairman', 'Board Chairman', 1),
('Vice Chairman', 'Board Vice Chairman', 2),
('Board Member', 'General Board Member', 3),
('Independent Director', 'Independent Board Director', 3),
('Executive Director', 'Executive Board Director', 2);

-- Default Asset Categories
INSERT INTO `assetcategories` (`CategoryShortCode`, `CategoryName`, `Description`) VALUES
('IT', 'Information Technology', 'Computers, laptops, servers, networking equipment'),
('FURN', 'Furniture', 'Office furniture, chairs, desks, cabinets'),
('VEH', 'Vehicles', 'Company vehicles, cars, trucks, motorcycles'),
('ELEC', 'Electronics', 'Printers, scanners, projectors, audio/visual equipment'),
('OFF', 'Office Equipment', 'Photocopiers, fax machines, shredders, general office equipment'),
('TOOL', 'Tools & Equipment', 'Specialized tools, machinery, equipment'),
('SEC', 'Security', 'Security cameras, access control systems, safes'),
('COMM', 'Communication', 'Phones, mobile devices, radio equipment');

-- Default SMS Configuration
INSERT INTO `sms_config` (`config_key`, `config_value`, `description`) VALUES
('api_token', '', 'SparrowSMS API Token (Required - Get from sparrowsms.com dashboard)'),
('sender_identity', '', 'Sender Identity provided by SparrowSMS (Required)'),
('api_endpoint', 'https://api.sparrowsms.com/v2/', 'SparrowSMS API Endpoint URL');

-- Default SMS Templates
INSERT INTO `sms_templates` (`name`, `subject`, `message`, `category`, `variables`) VALUES
('Welcome Message', 'Welcome to HRMS', 'Welcome {employee_name} to our company! Your employee ID is {emp_id}. For any queries, contact HR.', 'general', '["employee_name", "emp_id"]'),
('Attendance Reminder', 'Attendance Reminder', 'Dear {employee_name}, please mark your attendance for today. Time: {current_time}', 'attendance', '["employee_name", "current_time"]'),
('Leave Approved', 'Leave Request Approved', 'Dear {employee_name}, your leave request from {start_date} to {end_date} has been approved.', 'general', '["employee_name", "start_date", "end_date"]'),
('Leave Rejected', 'Leave Request Rejected', 'Dear {employee_name}, your leave request from {start_date} to {end_date} has been rejected. Reason: {reason}', 'alerts', '["employee_name", "start_date", "end_date", "reason"]'),
('Birthday Wishes', 'Happy Birthday', 'Happy Birthday {employee_name}! Wishing you a wonderful year ahead. Best regards from {company_name}.', 'general', '["employee_name", "company_name"]');

-- Default SMS Sender Identity
INSERT INTO `sms_sender_identities` (`identity`, `description`, `is_default`, `is_active`) VALUES
('HRMS', 'Default HRMS Sender Identity', 1, 1);

-- --------------------------------------------------------

--
-- Sample Admin User Data
--

-- Insert sample admin user (password: admin123)
INSERT INTO `users` (`employee_id`, `username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`) VALUES
('EMP001', 'admin', 'admin@hrms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', 'admin', 'active');

-- Insert corresponding employee record
INSERT INTO `employees` (`employee_id`, `emp_id`, `user_id`, `first_name`, `last_name`, `email`, `password`, `hire_date`, `join_date`, `department_id`, `designation_id`, `designation`, `branch`, `role_id`, `login_access`, `status`) VALUES
('EMP001', 'EMP001', 1, 'System', 'Administrator', 'admin@hrms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', CURDATE(), CURDATE(), 1, 1, 1, 1, 1, 1, 'active');

COMMIT;
