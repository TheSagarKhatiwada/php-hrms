<?php
/**
 * Migration: add_holiday_date_range
 * Description: Add start_date, end_date, branch_id, is_recurring to holidays table if missing.
 * Created: 2025-10-30 10:00:00
 */

return [
    'up' => function($pdo) {
        try { $pdo->exec("ALTER TABLE holidays ADD COLUMN start_date DATE NULL"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE holidays ADD COLUMN end_date DATE NULL"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE holidays ADD COLUMN branch_id INT NULL"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE holidays ADD COLUMN is_recurring TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
        // Add indexes if not present
        try { $pdo->exec("CREATE INDEX idx_holidays_start_date ON holidays(start_date)"); } catch (Throwable $e) {}
        try { $pdo->exec("CREATE INDEX idx_holidays_end_date ON holidays(end_date)"); } catch (Throwable $e) {}
        try { $pdo->exec("CREATE INDEX idx_holidays_branch ON holidays(branch_id)"); } catch (Throwable $e) {}
        // Add FK to branches if possible
        try { $pdo->exec("ALTER TABLE holidays ADD CONSTRAINT fk_holidays_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL"); } catch (Throwable $e) {}
    },

    'down' => function($pdo) {
        try { $pdo->exec("ALTER TABLE holidays DROP FOREIGN KEY fk_holidays_branch"); } catch (Throwable $e) {}
        try { $pdo->exec("DROP INDEX idx_holidays_branch ON holidays"); } catch (Throwable $e) {}
        try { $pdo->exec("DROP INDEX idx_holidays_end_date ON holidays"); } catch (Throwable $e) {}
        try { $pdo->exec("DROP INDEX idx_holidays_start_date ON holidays"); } catch (Throwable $e) {}
        // Not dropping columns to avoid data loss in rollback
    }
];
