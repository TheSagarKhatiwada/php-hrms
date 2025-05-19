-- Migration: Add Employee Status Column
-- Created at: 20250506121500

-- UP
-- Add a status column to employees table to track active/inactive status
ALTER TABLE `employees` 
ADD COLUMN `status` ENUM('active', 'inactive', 'on_leave', 'terminated') NOT NULL DEFAULT 'active' 
AFTER `role_id`;

-- Add an index to improve queries filtering by status
CREATE INDEX `idx_employee_status` ON `employees`(`status`);

-- END UP

-- DOWN
-- Remove the status column and index if we need to roll back
DROP INDEX IF EXISTS `idx_employee_status` ON `employees`;
ALTER TABLE `employees` DROP COLUMN IF EXISTS `status`;

-- END DOWN