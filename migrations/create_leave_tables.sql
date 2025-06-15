-- Leave Management Module Database Tables
-- Run this SQL to create the leave management tables

-- 1. Leave Types Table
CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL UNIQUE,
  `description` text,
  `days_allowed_per_year` int(11) DEFAULT 0,
  `is_paid` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `requires_approval` tinyint(1) DEFAULT 1,
  `max_consecutive_days` int(11) DEFAULT NULL,
  `min_notice_days` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Leave Requests Table
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `applied_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` timestamp NULL DEFAULT NULL,
  `rejection_reason` text,
  `emergency_contact` varchar(255) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Leave Balances Table
CREATE TABLE IF NOT EXISTS `leave_balances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `allocated_days` int(11) DEFAULT 0,
  `used_days` int(11) DEFAULT 0,
  `pending_days` int(11) DEFAULT 0,
  `remaining_days` int(11) DEFAULT 0,
  `carried_forward` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_employee_leave_year` (`employee_id`, `leave_type_id`, `year`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Leave Accruals Table (Progressive Leave Earning)
CREATE TABLE IF NOT EXISTS `leave_accruals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `accrued_days` decimal(4,2) DEFAULT 0.00,
  `total_accrued` decimal(4,2) DEFAULT 0.00,
  `processed_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `employment_months` int(11) DEFAULT 0,
  `pro_rata_factor` decimal(4,3) DEFAULT 1.000,
  `notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_employee_leave_year_month` (`employee_id`, `leave_type_id`, `year`, `month`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Leave Comments/History Table
CREATE TABLE IF NOT EXISTS `leave_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `leave_request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `action` enum('comment','approved','rejected','cancelled','resubmitted') DEFAULT 'comment',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default leave types
INSERT INTO `leave_types` (`name`, `code`, `description`, `days_allowed_per_year`, `is_paid`, `requires_approval`, `max_consecutive_days`, `min_notice_days`) VALUES
('Annual Leave', 'AL', 'Annual vacation leave for rest and recreation', 21, 1, 1, 15, 7),
('Sick Leave', 'SL', 'Medical leave for illness or injury', 10, 1, 0, 5, 0),
('Emergency Leave', 'EL', 'Emergency leave for urgent family matters', 5, 1, 1, 3, 0),
('Maternity Leave', 'ML', 'Maternity leave for new mothers', 90, 1, 1, 90, 30),
('Paternity Leave', 'PL', 'Paternity leave for new fathers', 10, 1, 1, 10, 7),
('Study Leave', 'STL', 'Educational leave for professional development', 15, 0, 1, 10, 14),
('Bereavement Leave', 'BL', 'Leave for family bereavement', 3, 1, 1, 3, 0),
('Personal Leave', 'PRL', 'Personal leave for individual matters', 5, 0, 1, 2, 2);

-- Create indexes for better performance
CREATE INDEX idx_leave_requests_employee ON leave_requests(employee_id);
CREATE INDEX idx_leave_requests_status ON leave_requests(status);
CREATE INDEX idx_leave_requests_dates ON leave_requests(start_date, end_date);
CREATE INDEX idx_leave_balances_employee_year ON leave_balances(employee_id, year);
CREATE INDEX idx_leave_accruals_employee_year ON leave_accruals(employee_id, year);
CREATE INDEX idx_leave_accruals_processed ON leave_accruals(processed_date);
CREATE INDEX idx_leave_comments_request ON leave_comments(leave_request_id);
