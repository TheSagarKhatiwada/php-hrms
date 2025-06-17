-- =============================================
-- COMPLETE LEAVE MANAGEMENT SETUP
-- Run this AFTER restoring the main backup
-- =============================================

-- Create leave_types table and insert default types
CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL UNIQUE,
  `description` text,
  `days_allowed_per_year` int(11) DEFAULT 0,
  `color` varchar(7) DEFAULT '#007bff',
  `days_allowed` int(11) DEFAULT 0,
  `is_paid` tinyint(1) DEFAULT 1,
  `requires_approval` tinyint(1) DEFAULT 1,
  `max_consecutive_days` int(11) DEFAULT NULL,
  `min_notice_days` int(11) DEFAULT 0,
  `carry_forward` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `leave_types` (`name`, `code`, `description`, `days_allowed_per_year`, `color`, `is_paid`, `requires_approval`) VALUES
('Annual Leave', 'AL', 'Annual vacation leave', 21, '#28a745', 1, 1),
('Sick Leave', 'SL', 'Medical leave for illness', 10, '#dc3545', 1, 0),
('Casual Leave', 'CL', 'Short-term personal leave', 7, '#17a2b8', 1, 1),
('Maternity Leave', 'ML', 'Maternity leave for female employees', 90, '#e83e8c', 1, 1),
('Paternity Leave', 'PL', 'Paternity leave for male employees', 15, '#6f42c1', 1, 1);

-- Create leave_requests table
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL,
  `days_requested` int(11) NOT NULL,
  `reason` text,
  `emergency_contact` varchar(100),
  `emergency_phone` varchar(20),
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` varchar(20) DEFAULT NULL,
  `approved_date` timestamp NULL DEFAULT NULL,
  `rejection_reason` text,
  `reviewed_date` timestamp NULL DEFAULT NULL,
  `is_half_day` tinyint(1) DEFAULT 0,
  `half_day_period` enum('morning','afternoon') DEFAULT NULL,
  `applied_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_leave_type_id` (`leave_type_id`),
  KEY `idx_status` (`status`),
  KEY `idx_date_range` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create leave_accruals table
CREATE TABLE IF NOT EXISTS `leave_accruals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `accrual_month` int(2) NOT NULL,
  `accrual_year` int(4) NOT NULL,
  `accrued_days` decimal(5,2) NOT NULL DEFAULT 0.00,
  `processed_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_accrual` (`employee_id`, `leave_type_id`, `accrual_month`, `accrual_year`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_leave_type_id` (`leave_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create leave_balances table
CREATE TABLE IF NOT EXISTS `leave_balances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `allocated_days` decimal(5,2) NOT NULL DEFAULT 0.00,
  `used_days` decimal(5,2) NOT NULL DEFAULT 0.00,
  `pending_days` decimal(5,2) NOT NULL DEFAULT 0.00,
  `remaining_days` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_balance` (`employee_id`, `leave_type_id`, `year`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_leave_type_id` (`leave_type_id`),
  KEY `idx_year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Initialize leave balances for all active employees for current year
INSERT IGNORE INTO `leave_balances` (`employee_id`, `leave_type_id`, `year`, `allocated_days`, `remaining_days`)
SELECT 
  e.emp_id,
  lt.id,
  YEAR(CURDATE()),
  lt.days_allowed_per_year,
  lt.days_allowed_per_year
FROM employees e
CROSS JOIN leave_types lt
WHERE e.status = 'active' AND lt.is_active = 1;

-- Set proper status for employees based on exit_date
UPDATE employees 
SET status = 'terminated' 
WHERE exit_date IS NOT NULL AND exit_date <= CURDATE();

UPDATE employees 
SET status = 'active' 
WHERE exit_date IS NULL OR exit_date > CURDATE();

SELECT 'Leave management setup completed!' as status;
SELECT COUNT(*) as active_employees FROM employees WHERE status = 'active';
SELECT COUNT(*) as leave_types_count FROM leave_types;
SELECT COUNT(*) as leave_balances_initialized FROM leave_balances;
