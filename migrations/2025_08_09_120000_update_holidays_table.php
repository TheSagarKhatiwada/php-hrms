<?php
/**
 * Migration: update_holidays_table
 * Description: Add is_recurring and branch_id to holidays table if missing; add indexes.
 * Created: 2025-08-09 12:00:00
 */

return [
    'up' => function($pdo) {
    // Ensure columns exist (MySQL doesn't support IF NOT EXISTS for ADD COLUMN universally)
    try { $pdo->exec("ALTER TABLE holidays ADD COLUMN is_recurring TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE holidays ADD COLUMN branch_id INT NULL"); } catch (Throwable $e) {}
        // Add indexes if not present
        try { $pdo->exec("CREATE INDEX idx_holidays_date ON holidays(date)"); } catch (Throwable $e) {}
        try { $pdo->exec("CREATE INDEX idx_holidays_type ON holidays(type)"); } catch (Throwable $e) {}
        try { $pdo->exec("CREATE INDEX idx_holidays_branch ON holidays(branch_id)"); } catch (Throwable $e) {}
        // Optional: add FK if branches exists
        try { $pdo->exec("ALTER TABLE holidays ADD CONSTRAINT fk_holidays_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL"); } catch (Throwable $e) {}
    },

    'down' => function($pdo) {
        // Best-effort rollback (some engines don't support IF EXISTS for drop column)
        try { $pdo->exec("ALTER TABLE holidays DROP FOREIGN KEY fk_holidays_branch"); } catch (Throwable $e) {}
        try { $pdo->exec("DROP INDEX idx_holidays_branch ON holidays"); } catch (Throwable $e) {}
        try { $pdo->exec("DROP INDEX idx_holidays_type ON holidays"); } catch (Throwable $e) {}
        try { $pdo->exec("DROP INDEX idx_holidays_date ON holidays"); } catch (Throwable $e) {}
        // Not dropping columns to avoid data loss.
    }
];
