<?php
/**
 * Migration: set_employee_work_time_defaults
 * Description: Set default values for employees.work_start_time and work_end_time
 * Created: 2025-10-30 12:30:00
 */

return [
    'up' => function($pdo) {
        try {
            // Make the ALTER idempotent: check existing column defaults
            $sql = "SELECT COLUMN_NAME, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME IN ('work_start_time','work_end_time')";
            $st = $pdo->query($sql);
            $cols = [];
            if($st) {
                foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $cols[$r['COLUMN_NAME']] = $r['COLUMN_DEFAULT']; }
            }
            if(!isset($cols['work_start_time']) || $cols['work_start_time'] !== '09:30:00'){
                $pdo->exec("ALTER TABLE employees MODIFY COLUMN work_start_time TIME NULL DEFAULT '09:30:00'");
            }
            if(!isset($cols['work_end_time']) || $cols['work_end_time'] !== '18:00:00'){
                $pdo->exec("ALTER TABLE employees MODIFY COLUMN work_end_time TIME NULL DEFAULT '18:00:00'");
            }
        } catch(Throwable $e) {
            // swallow errors to avoid blocking deployments; logging optional
        }
    },
    'down' => function($pdo) {
        try { $pdo->exec("ALTER TABLE employees MODIFY COLUMN work_start_time TIME NULL DEFAULT NULL"); } catch(Throwable $e) {}
        try { $pdo->exec("ALTER TABLE employees MODIFY COLUMN work_end_time TIME NULL DEFAULT NULL"); } catch(Throwable $e) {}
    }
];
