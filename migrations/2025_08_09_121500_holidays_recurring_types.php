<?php
/**
 * Migration: holidays_recurring_types
 * Description: Add recurring_type and recurring_day_of_week to holidays; backfill from is_recurring.
 * Created: 2025-08-09 12:15:00
 */

return [
    'up' => function($pdo) {
        // Add columns if missing
        try { $pdo->exec("ALTER TABLE holidays ADD COLUMN recurring_type ENUM('none','weekly','monthly','quarterly','annually') NOT NULL DEFAULT 'none'"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE holidays ADD COLUMN recurring_day_of_week TINYINT NULL COMMENT '1=Mon..7=Sun'"); } catch (Throwable $e) {}
        try { $pdo->exec("CREATE INDEX idx_holidays_recurring_type ON holidays(recurring_type)"); } catch (Throwable $e) {}

        // Backfill: legacy is_recurring=1 -> annually
        try {
            $pdo->exec("UPDATE holidays SET recurring_type = 'annually' WHERE COALESCE(is_recurring, 0) = 1 AND recurring_type = 'none'");
        } catch (Throwable $e) {}
    },

    'down' => function($pdo) {
        // Keep columns; no destructive rollback.
        // Optionally drop index
        try { $pdo->exec("DROP INDEX idx_holidays_recurring_type ON holidays"); } catch (Throwable $e) {}
    }
];
