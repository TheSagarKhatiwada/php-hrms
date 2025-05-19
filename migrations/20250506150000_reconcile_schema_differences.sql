-- Migration: Reconcile Schema Differences
-- Created at: 20250506150000

-- UP
-- 1. Add the missing status column to employees table
ALTER TABLE `employees` 
ADD COLUMN IF NOT EXISTS `status` ENUM('active', 'inactive', 'on_leave', 'terminated') NOT NULL DEFAULT 'active' 
AFTER `role_id`;

-- Add an index to improve queries filtering by status
CREATE INDEX IF NOT EXISTS `idx_employee_status` ON `employees`(`status`);

-- 2. Update notifications table - rename user_id to employee_id if needed
-- First check if we have user_id column and employee_id doesn't exist
-- This is a conditional migration that checks current state
SET @column_exists = 0;
SELECT 1 INTO @column_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'notifications' 
AND COLUMN_NAME = 'user_id';

SET @column_missing = 0;
SELECT 1 INTO @column_missing
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'notifications' 
AND COLUMN_NAME = 'employee_id';

-- Only run this if we have user_id but not employee_id
SET @sql = IF(@column_exists = 1 AND @column_missing = 1, 
    'ALTER TABLE `notifications` CHANGE COLUMN `user_id` `employee_id` int NOT NULL',
    'SELECT "Column already correct or missing, no action taken"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Ensure proper foreign key exists on notifications table
-- First check if the constraint exists
SET @constraint_exists = 0;
SELECT 1 INTO @constraint_exists
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'notifications' 
AND CONSTRAINT_NAME = 'notifications_ibfk_1';

-- Only add the constraint if it doesn't exist and employee_id column exists
SET @sql = IF(@constraint_exists = 0 AND @column_missing = 0, 
    'ALTER TABLE `notifications` ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE',
    'SELECT "Constraint already exists or column missing, no action taken"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- END UP

-- DOWN
-- 1. Remove the employee status column and index
DROP INDEX IF EXISTS `idx_employee_status` ON `employees`;
ALTER TABLE `employees` DROP COLUMN IF EXISTS `status`;

-- 2. Revert employee_id back to user_id if needed
SET @column_exists = 0;
SELECT 1 INTO @column_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'notifications' 
AND COLUMN_NAME = 'employee_id';

SET @sql = IF(@column_exists = 1, 
    'ALTER TABLE `notifications` CHANGE COLUMN `employee_id` `user_id` int NOT NULL',
    'SELECT "Column not found, no action taken"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Drop the foreign key constraint if it exists
SET @constraint_exists = 0;
SELECT 1 INTO @constraint_exists
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'notifications' 
AND CONSTRAINT_NAME = 'notifications_ibfk_1';

SET @sql = IF(@constraint_exists = 1, 
    'ALTER TABLE `notifications` DROP FOREIGN KEY `notifications_ibfk_1`',
    'SELECT "Constraint not found, no action taken"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- END DOWN