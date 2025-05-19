-- Migration: Complete Database Schema Reference
-- Created at: 20250506120000

-- UP
-- Complete database schema from db-backup.sql

-- Table structure for table `assetassignments`
CREATE TABLE IF NOT EXISTS `assetassignments` (
  `AssignmentID` int(11) NOT NULL AUTO_INCREMENT,
  `AssetID` int(11) NOT NULL,
  `EmployeeID` int(11) NOT NULL,
  `AssignmentDate` date NOT NULL,
  `ExpectedReturnDate` date DEFAULT NULL,
  `ReturnDate` date DEFAULT NULL,
  `Notes` varchar(150) NOT NULL,
  `ReturnNotes` varchar(150) NOT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`AssignmentID`),
  KEY `AssetID` (`AssetID`),
  KEY `EmployeeID` (`EmployeeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `assetcategories`
CREATE TABLE IF NOT EXISTS `assetcategories` (
  `CategoryID` int(11) NOT NULL AUTO_INCREMENT,
  `CategoryShortCode` varchar(5) NOT NULL,
  `CategoryName` varchar(100) NOT NULL,
  `Description` text DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`CategoryID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `assetmaintenance`
CREATE TABLE IF NOT EXISTS `assetmaintenance` (
  `RecordID` int(11) NOT NULL AUTO_INCREMENT,
  `AssetID` int(11) NOT NULL,
  `MaintenanceDate` date NOT NULL,
  `MaintenanceType` enum('Preventive','Corrective') NOT NULL,
  `MaintenancePerformBy` varchar(75) NOT NULL,
  `Description` text DEFAULT NULL,
  `Cost` decimal(10,2) DEFAULT NULL,
  `MaintenanceStatus` enum('Scheduled','In Progress','Completed','Not Required') NOT NULL,
  `MaintainanceRemarks` text NOT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ActualCost` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`RecordID`),
  KEY `AssetID` (`AssetID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `attendance_logs`
CREATE TABLE IF NOT EXISTS `attendance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mach_sn` int(11) NOT NULL,
  `mach_id` int(11) NOT NULL,
  `emp_Id` bigint(20) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `method` tinyint(1) DEFAULT 0,
  `manual_reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `branches`
CREATE TABLE IF NOT EXISTS `branches` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `departments`
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `manager_id` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `designations`
CREATE TABLE IF NOT EXISTS `designations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `employees`
CREATE TABLE IF NOT EXISTS `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(50) NOT NULL,
  `mach_id` varchar(50) DEFAULT NULL,
  `branch` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `gender` varchar(1) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(60) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `dob` date NOT NULL DEFAULT '2000-01-01',
  `join_date` date NOT NULL,
  `designation` varchar(50) DEFAULT NULL,
  `office_phone` varchar(20) DEFAULT NULL,
  `office_email` varchar(100) DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `exit_note` varchar(150) NOT NULL,
  `login_access` tinyint(1) DEFAULT 0,
  `role` int(2) NOT NULL DEFAULT 3,
  `user_image` varchar(255) DEFAULT 'resources/userimg/default-image.jpg',
  `role_id` int(11) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `fixedassets`
CREATE TABLE IF NOT EXISTS `fixedassets` (
  `AssetID` int(11) NOT NULL AUTO_INCREMENT,
  `AssetSerial` varchar(25) NOT NULL,
  `AssetName` varchar(100) NOT NULL,
  `AssetImage` varchar(255) NOT NULL,
  `AssetsDescription` varchar(256) NOT NULL,
  `CategoryID` int(11) DEFAULT NULL,
  `PurchaseDate` date NOT NULL,
  `PurchaseCost` int(55) NOT NULL,
  `WarrantyEndDate` date DEFAULT NULL,
  `AssetCondition` varchar(50) DEFAULT NULL,
  `AssetLocation` varchar(100) DEFAULT NULL,
  `Status` enum('Available','Maintenance','Retired','Assigned') NOT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`AssetID`),
  UNIQUE KEY `AssetName` (`AssetName`,`PurchaseDate`),
  KEY `CategoryID` (`CategoryID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `login_attempts`
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ip_address` (`ip_address`),
  KEY `attempt_time` (`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `roles`
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `settings`
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(255) NOT NULL,
  `value` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `modified_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `permissions`
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `role_permissions`
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permission_unique` (`role_id`, `permission_id`),
  KEY `permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add foreign key constraints
ALTER TABLE `assetassignments`
  ADD CONSTRAINT `assetassignments_ibfk_1` FOREIGN KEY (`AssetID`) REFERENCES `fixedassets` (`AssetID`) ON DELETE CASCADE,
  ADD CONSTRAINT `assetassignments_ibfk_2` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

ALTER TABLE `assetmaintenance`
  ADD CONSTRAINT `assetmaintenance_ibfk_1` FOREIGN KEY (`AssetID`) REFERENCES `fixedassets` (`AssetID`) ON DELETE CASCADE;

ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

ALTER TABLE `designations`
  ADD CONSTRAINT `designations_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

ALTER TABLE `fixedassets`
  ADD CONSTRAINT `fixedassets_ibfk_1` FOREIGN KEY (`CategoryID`) REFERENCES `assetcategories` (`CategoryID`) ON DELETE SET NULL;

ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

-- Insert default permissions
INSERT IGNORE INTO `permissions` (`name`, `code`, `description`) VALUES
('View Dashboard', 'view_dashboard', 'Access to view the main dashboard'),
('Manage Employees', 'manage_employees', 'Add, edit, and delete employee records'),
('View Employees', 'view_employees', 'View employee records'),
('Manage Attendance', 'manage_attendance', 'Record and modify attendance data'),
('View Attendance', 'view_attendance', 'View attendance records'),
('Manage Departments', 'manage_departments', 'Add, edit, and delete departments'),
('Manage Designations', 'manage_designations', 'Add, edit, and delete job designations'),
('Manage Roles', 'manage_roles', 'Add, edit, and delete user roles'),
('Manage System Settings', 'manage_settings', 'Modify system configuration settings'),
('Manage Assets', 'manage_assets', 'Add, edit, and delete company assets'),
('View Assets', 'view_assets', 'View company assets'),
('Generate Reports', 'generate_reports', 'Create and download system reports');

-- END UP

-- DOWN
-- In a real situation, you might not want to drop all tables
-- But for completeness, this is how you would revert this migration
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `fixedassets`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `designations`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `branches`;
DROP TABLE IF EXISTS `attendance_logs`;
DROP TABLE IF EXISTS `assetmaintenance`;
DROP TABLE IF EXISTS `assetcategories`;
DROP TABLE IF EXISTS `assetassignments`;
-- END DOWN