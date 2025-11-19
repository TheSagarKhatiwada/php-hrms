<?php
/**
 * Migration: drop_holidays_date
 * Description: Remove legacy `date` column from holidays table.
 * Created: 2025-10-30 10:40:00
 */

return [
    'up' => function($pdo) {
        try {
            // Drop index on date if exists
            try { $pdo->exec("DROP INDEX idx_holidays_date ON holidays"); } catch (Throwable $e) {}
            // Drop the legacy column
            try { $pdo->exec("ALTER TABLE holidays DROP COLUMN `date`"); } catch (Throwable $e) {}
        } catch (Throwable $e) {
            // ignore errors
        }
    },

    'down' => function($pdo) {
        try {
            // Recreate the legacy column (nullable) and index
            try { $pdo->exec("ALTER TABLE holidays ADD COLUMN `date` DATE NULL"); } catch (Throwable $e) {}
            try { $pdo->exec("CREATE INDEX idx_holidays_date ON holidays(`date`)"); } catch (Throwable $e) {}
        } catch (Throwable $e) {
            // ignore
        }
    }
];
