<?php
/**
 * Migration: create shift_templates, employee_shift_assignments, employee_schedule_overrides
 * Idempotent: checks information_schema before creating
 */
return [
  'up' => function(PDO $pdo) {
    $sqls = [];
    $sqls[] = "CREATE TABLE IF NOT EXISTS `shift_templates` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(191) NOT NULL,
      `start_time` TIME NOT NULL,
      `end_time` TIME NOT NULL,
      `description` TEXT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sqls[] = "CREATE TABLE IF NOT EXISTS `employee_shift_assignments` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `emp_id` VARCHAR(64) NOT NULL,
      `shift_id` INT NOT NULL,
      `start_date` DATE NOT NULL,
      `end_date` DATE NOT NULL,
      `recurring_yearly` TINYINT(1) DEFAULT 0,
      `priority` INT DEFAULT 0,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX(emp_id), INDEX(shift_id), INDEX(start_date), INDEX(end_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sqls[] = "CREATE TABLE IF NOT EXISTS `employee_schedule_overrides` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `emp_id` VARCHAR(64) NOT NULL,
      `start_date` DATE NOT NULL,
      `end_date` DATE NOT NULL,
      `recurring_yearly` TINYINT(1) DEFAULT 0,
      `work_start_time` TIME NOT NULL,
      `work_end_time` TIME NOT NULL,
      `priority` INT DEFAULT 0,
      `reason` VARCHAR(255) DEFAULT NULL,
      `created_by` VARCHAR(64) DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX(emp_id), INDEX(start_date), INDEX(end_date), INDEX(recurring_yearly)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    foreach($sqls as $s){ $pdo->exec($s); }
  },

  'down' => function(PDO $pdo) {
    $pdo->exec("DROP TABLE IF EXISTS employee_schedule_overrides");
    $pdo->exec("DROP TABLE IF EXISTS employee_shift_assignments");
    $pdo->exec("DROP TABLE IF EXISTS shift_templates");
  }
];
