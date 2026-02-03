<?php
/**
 * Migration: Allow NULL for end_date, work_start_time, work_end_time in employee_schedule_overrides
 * Supports open-ended overrides and partial time overrides
 */
return [
  'up' => function(PDO $pdo) {
    $sqls = [];
    
    // Allow NULL for end_date (open-ended overrides)
    $sqls[] = "ALTER TABLE `employee_schedule_overrides` 
               MODIFY COLUMN `end_date` DATE NULL";
    
    // Allow NULL for work_start_time and work_end_time (partial overrides)
    $sqls[] = "ALTER TABLE `employee_schedule_overrides` 
               MODIFY COLUMN `work_start_time` TIME NULL";
    
    $sqls[] = "ALTER TABLE `employee_schedule_overrides` 
               MODIFY COLUMN `work_end_time` TIME NULL";
    
    foreach($sqls as $s){ 
      try {
        $pdo->exec($s); 
      } catch(PDOException $e) {
        // Log but continue if column already nullable
        error_log("Migration warning: " . $e->getMessage());
      }
    }
  },

  'down' => function(PDO $pdo) {
    // Rollback: set fields back to NOT NULL (will fail if NULL values exist)
    $sqls = [];
    $sqls[] = "ALTER TABLE `employee_schedule_overrides` 
               MODIFY COLUMN `end_date` DATE NOT NULL";
    $sqls[] = "ALTER TABLE `employee_schedule_overrides` 
               MODIFY COLUMN `work_start_time` TIME NOT NULL";
    $sqls[] = "ALTER TABLE `employee_schedule_overrides` 
               MODIFY COLUMN `work_end_time` TIME NOT NULL";
    
    foreach($sqls as $s){ 
      try {
        $pdo->exec($s); 
      } catch(PDOException $e) {
        error_log("Migration rollback warning: " . $e->getMessage());
      }
    }
  }
];
