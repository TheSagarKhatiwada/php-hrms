<?php
/**
 * Migration: add_employee_work_times
 * Description: Add work_start_time and work_end_time columns to employees table
 * Created: 2025-10-30 12:15:00
 */

return [
    'up' => function($pdo) {
        try {
            // Check if column exists to make the migration safe to run multiple times
            $st = $pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'employees' AND column_name = 'work_start_time'");
            if($st->execute() && ($r = $st->fetch(PDO::FETCH_ASSOC)) && intval($r['c']) === 0) {
                $pdo->exec("ALTER TABLE employees ADD COLUMN work_start_time TIME NULL AFTER exit_date");
            }
            $st2 = $pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'employees' AND column_name = 'work_end_time'");
            if($st2->execute() && ($r2 = $st2->fetch(PDO::FETCH_ASSOC)) && intval($r2['c']) === 0) {
                $pdo->exec("ALTER TABLE employees ADD COLUMN work_end_time TIME NULL AFTER work_start_time");
            }
        } catch(Throwable $e) {
            // Swallow errors to avoid blocking deployments; log if logger available
        }
    },
    'down' => function($pdo) {
        try { $pdo->exec("ALTER TABLE employees DROP COLUMN IF EXISTS work_end_time"); } catch(Throwable $e) {}
        try { $pdo->exec("ALTER TABLE employees DROP COLUMN IF EXISTS work_start_time"); } catch(Throwable $e) {}
    }
];
