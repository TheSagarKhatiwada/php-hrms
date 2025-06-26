-- =====================================================
-- PHP HRMS Database Schema
-- Generated on: June 18, 2025
-- Updated to match actual database structure
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =====================================================
-- Core Tables
-- =====================================================

-- Table: branches
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `address` text,
  `phone` varchar(20),
  `email` varchar(100),
  `manager_id` varchar(20),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `code` (`code`),
  KEY `manager_id` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: departments
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `manager_id` varchar(20),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `manager_id` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: designations
CREATE TABLE `designations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` text,
  `department_id` int(11),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: roles
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `permissions` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Employees Table (Core Entity)
-- =====================================================

-- Table: employees
CREATE TABLE `employees` (
  `emp_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `office_email` varchar(100) DEFAULT NULL,
  `office_phone` varchar(20) DEFAULT NULL,
  `address` text,
  `date_of_birth` date DEFAULT NULL,
  `hire_date` date NOT NULL,
  `join_date` date DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `exit_note` text,
  `department_id` int(11) DEFAULT NULL,
  `designation_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `designation` int(11) DEFAULT NULL,
  `branch` varchar(10) NOT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `role_id` int(11) NOT NULL DEFAULT '2',
  `board_position_id` int(11) DEFAULT NULL,
  `mach_id` varchar(20) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `user_image` varchar(255) DEFAULT NULL,
  `login_access` tinyint(1) DEFAULT '1',
  `status` enum('active','inactive','terminated') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `gender` enum('male','female','other') DEFAULT NULL,
  PRIMARY KEY (`emp_id`),
  UNIQUE KEY `email` (`email`),
  KEY `department_id` (`department_id`),
  KEY `designation_id` (`designation_id`),
  KEY `branch_id` (`branch_id`),
  KEY `designation` (`designation`),
  KEY `branch` (`branch`),
  KEY `manager_id` (`manager_id`),
  KEY `supervisor_id` (`supervisor_id`),
  KEY `role_id` (`role_id`),
  KEY `board_position_id` (`board_position_id`),
  KEY `mach_id` (`mach_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Attendance System
-- =====================================================

-- Table: attendance_logs
CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(20) DEFAULT NULL,
  `mach_sn` varchar(50) DEFAULT NULL,
  `mach_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `method` int(11) DEFAULT '0',
  `manual_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `emp_id` (`emp_id`),
  KEY `mach_sn` (`mach_sn`),
  KEY `mach_id` (`mach_id`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Leave Management System
-- =====================================================

-- Table: leave_types
CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  `description` text,
  `default_days` int(11) NOT NULL DEFAULT '0',
  `color` varchar(7) DEFAULT '#007bff',
  `is_paid` tinyint(1) NOT NULL DEFAULT '1',
  `requires_approval` tinyint(1) NOT NULL DEFAULT '1',
  `can_carry_forward` tinyint(1) NOT NULL DEFAULT '0',
  `max_carry_forward` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: leave_requests
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11),
  `days_requested` int(11),
  `reason` text,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `applied_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` varchar(20) DEFAULT NULL,
  `approved_date` timestamp NULL DEFAULT NULL,
  `reviewed_date` timestamp NULL DEFAULT NULL,
  `rejection_reason` text,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  KEY `status` (`status`),
  KEY `start_date` (`start_date`),
  KEY `approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: leave_balances
CREATE TABLE `leave_balances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` year(4) NOT NULL,
  `allocated_days` int(11) NOT NULL DEFAULT '0',
  `used_days` int(11) NOT NULL DEFAULT '0',
  `carried_forward` int(11) NOT NULL DEFAULT '0',
  `remaining_days` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_leave_year` (`employee_id`, `leave_type_id`, `year`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: leave_accruals
CREATE TABLE `leave_accruals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `accrual_date` date NOT NULL,
  `days_accrued` decimal(4,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  KEY `accrual_date` (`accrual_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: leaves (legacy table)
CREATE TABLE `leaves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `leave_type` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: holidays
CREATE TABLE `holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `type` enum('national','religious','company') NOT NULL DEFAULT 'national',
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `date` (`date`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Asset Management System
-- =====================================================

-- Table: assetcategories
CREATE TABLE `assetcategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: assets (modern assets table)
CREATE TABLE `assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `serial_number` varchar(100),
  `category_id` int(11),
  `model` varchar(100),
  `purchase_date` date,
  `purchase_price` decimal(10,2),
  `status` enum('Available','Assigned','Maintenance','Disposed') NOT NULL DEFAULT 'Available',
  `location` varchar(255),
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: fixedassets (legacy assets table)
CREATE TABLE `fixedassets` (
  `AssetID` int(11) NOT NULL AUTO_INCREMENT,
  `AssetName` varchar(255) NOT NULL,
  `AssetSerial` varchar(100),
  `AssetCategory` varchar(100),
  `AssetModel` varchar(100),
  `PurchaseDate` date,
  `PurchasePrice` decimal(10,2),
  `Status` enum('Available','Assigned','Maintenance','Disposed') NOT NULL DEFAULT 'Available',
  `Location` varchar(255),
  `Description` text,
  `CreatedDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`AssetID`),
  KEY `Status` (`Status`),
  KEY `AssetCategory` (`AssetCategory`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: asset_assignments (modern assignments table)
CREATE TABLE `asset_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `assignment_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `assignment_note` text,
  `return_note` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  KEY `employee_id` (`employee_id`),
  KEY `assignment_date` (`assignment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: assetassignments (legacy assignments table)
CREATE TABLE `assetassignments` (
  `AssignmentID` int(11) NOT NULL AUTO_INCREMENT,
  `AssetID` int(11) NOT NULL,
  `EmployeeID` varchar(20) NOT NULL,
  `AssignmentDate` date NOT NULL,
  `ReturnDate` date DEFAULT NULL,
  `AssignmentNote` text,
  `ReturnNote` text,
  `CreatedDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`AssignmentID`),
  KEY `AssetID` (`AssetID`),
  KEY `EmployeeID` (`EmployeeID`),
  KEY `AssignmentDate` (`AssignmentDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: asset_maintenance (modern maintenance table)
CREATE TABLE `asset_maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `maintenance_date` date NOT NULL,
  `maintenance_type` varchar(100),
  `description` text,
  `cost` decimal(10,2),
  `performed_by` varchar(255),
  `status` enum('Scheduled','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  KEY `maintenance_date` (`maintenance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: assetmaintenance (legacy maintenance table)
CREATE TABLE `assetmaintenance` (
  `MaintenanceID` int(11) NOT NULL AUTO_INCREMENT,
  `AssetID` int(11) NOT NULL,
  `MaintenanceDate` date NOT NULL,
  `MaintenanceType` varchar(100),
  `Description` text,
  `Cost` decimal(10,2),
  `MaintenancePerformBy` varchar(255),
  `MaintenanceStatus` enum('Scheduled','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
  `CreatedDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`MaintenanceID`),
  KEY `AssetID` (`AssetID`),
  KEY `MaintenanceDate` (`MaintenanceDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Board of Directors Management
-- =====================================================

-- Table: board_positions
CREATE TABLE `board_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `level` int(11) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: board_of_directors
CREATE TABLE `board_of_directors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20),
  `position_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `term_end_date` date,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `position_id` (`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: board_committees
CREATE TABLE `board_committees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `chairman_id` varchar(20),
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `chairman_id` (`chairman_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: board_committee_members
CREATE TABLE `board_committee_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `committee_id` int(11) NOT NULL,
  `board_member_id` int(11) NOT NULL,
  `role` enum('Chairman','Member','Secretary') NOT NULL DEFAULT 'Member',
  `joined_date` date NOT NULL,
  `left_date` date,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `committee_id` (`committee_id`),
  KEY `board_member_id` (`board_member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: board_meetings
CREATE TABLE `board_meetings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `committee_id` int(11),
  `meeting_date` datetime NOT NULL,
  `location` varchar(255),
  `agenda` text,
  `minutes` text,
  `status` enum('Scheduled','Completed','Cancelled','Postponed') NOT NULL DEFAULT 'Scheduled',
  `created_by` varchar(20),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `committee_id` (`committee_id`),
  KEY `meeting_date` (`meeting_date`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: notifications
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(20) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `is_global` tinyint(1) NOT NULL DEFAULT '0',
  `url` varchar(255),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `emp_id` (`emp_id`),
  KEY `is_read` (`is_read`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- SMS Management System
-- =====================================================

-- Table: sms_config
CREATE TABLE `sms_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) NOT NULL,
  `api_key` varchar(255),
  `api_secret` varchar(255),
  `sender_id` varchar(20),
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: sms_sender_identities
CREATE TABLE `sms_sender_identities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` varchar(20) NOT NULL,
  `description` varchar(255),
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sender_id` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: sms_templates
CREATE TABLE `sms_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `variables` text,
  `category` varchar(100),
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: sms_campaigns
CREATE TABLE `sms_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `template_id` int(11),
  `sender_id` varchar(20),
  `message` text NOT NULL,
  `scheduled_at` datetime,
  `sent_at` datetime,
  `status` enum('Draft','Scheduled','Sending','Sent','Failed') NOT NULL DEFAULT 'Draft',
  `total_recipients` int(11) NOT NULL DEFAULT '0',
  `sent_count` int(11) NOT NULL DEFAULT '0',
  `failed_count` int(11) NOT NULL DEFAULT '0',
  `created_by` varchar(20),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: sms_logs
CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11),
  `recipient` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('Pending','Sent','Failed','Delivered') NOT NULL DEFAULT 'Pending',
  `response` text,
  `cost` decimal(8,4) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `recipient` (`recipient`),
  KEY `status` (`status`),
  KEY `sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Contact Management
-- =====================================================

-- Table: contact_groups
CREATE TABLE `contact_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: contacts
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100),
  `email` varchar(150),
  `phone` varchar(20),
  `title` varchar(100),
  `organization` varchar(255),
  `contact_group_id` int(11),
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_group` (`contact_group_id`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- System Settings
-- =====================================================

-- Table: system_settings
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` varchar(50) DEFAULT 'text',
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Foreign Key Constraints
-- =====================================================

-- Employees table constraints
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_employees_branch` FOREIGN KEY (`branch`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_employees_designation` FOREIGN KEY (`designation`) REFERENCES `designations` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_employees_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_employees_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `employees` (`emp_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_employees_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT;

-- Attendance logs constraints
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `fk_attendance_emp_id` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE;

-- Leave requests constraints
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `fk_leave_requests_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_leave_requests_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_leave_requests_approver` FOREIGN KEY (`approved_by`) REFERENCES `employees` (`emp_id`) ON DELETE SET NULL;

-- Asset assignments constraints
ALTER TABLE `assetassignments`
  ADD CONSTRAINT `fk_asset_assignments_asset` FOREIGN KEY (`AssetID`) REFERENCES `fixedassets` (`AssetID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_asset_assignments_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE;

-- Asset maintenance constraints
ALTER TABLE `assetmaintenance`
  ADD CONSTRAINT `fk_asset_maintenance_asset` FOREIGN KEY (`AssetID`) REFERENCES `fixedassets` (`AssetID`) ON DELETE CASCADE;

-- Notifications constraints
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_emp_id` FOREIGN KEY (`emp_id`) REFERENCES `employees` (`emp_id`) ON DELETE CASCADE;

-- Contacts constraints
ALTER TABLE `contacts`
  ADD CONSTRAINT `fk_contacts_group` FOREIGN KEY (`contact_group_id`) REFERENCES `contact_groups` (`id`) ON DELETE SET NULL;

-- Designations constraints
ALTER TABLE `designations`
  ADD CONSTRAINT `fk_designations_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

-- Departments constraints
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_departments_manager` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`emp_id`) ON DELETE SET NULL;

-- =====================================================
-- Insert Default Data
-- =====================================================

-- Default roles
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Administrator', 'Full system access'),
(2, 'HR Manager', 'Human resources management'),
(3, 'Employee', 'Standard employee access'),
(4, 'Supervisor', 'Team supervision access');

-- Default leave types
INSERT INTO `leave_types` (`name`, `code`, `description`, `default_days`, `color`, `is_paid`) VALUES
('Annual Leave', 'AL', 'Annual vacation leave', 21, '#007bff', 1),
('Sick Leave', 'SL', 'Medical leave', 10, '#dc3545', 1),
('Casual Leave', 'CL', 'Casual/personal leave', 12, '#28a745', 1),
('Maternity Leave', 'ML', 'Maternity leave', 90, '#e83e8c', 1),
('Paternity Leave', 'PL', 'Paternity leave', 14, '#17a2b8', 1);

-- Default contact group
INSERT INTO `contact_groups` (`name`, `description`) VALUES
('General', 'General contacts'),
('Vendors', 'Supplier and vendor contacts'),
('Clients', 'Client contacts');

-- Default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('company_name', 'Your Company Name', 'text', 'Company name for the system'),
('work_start_time', '09:00', 'time', 'Standard work start time'),
('work_end_time', '18:00', 'time', 'Standard work end time'),
('timezone', 'UTC', 'text', 'System timezone'),
('notification_enabled', '1', 'boolean', 'Enable system notifications');

-- =====================================================
-- Indexes for Performance
-- =====================================================

-- Additional performance indexes
CREATE INDEX `idx_employees_status_branch` ON `employees` (`status`, `branch`);
CREATE INDEX `idx_attendance_emp_date_time` ON `attendance_logs` (`emp_id`, `date`, `time`);
CREATE INDEX `idx_leave_requests_dates` ON `leave_requests` (`start_date`, `end_date`);
CREATE INDEX `idx_notifications_unread` ON `notifications` (`emp_id`, `is_read`, `created_at`);

COMMIT;

-- =====================================================
-- End of Schema
-- =====================================================
