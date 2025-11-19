<?php
/**
 * Migration: backfill_holidays_date
 * Description: Ensure legacy `date` column is populated from `start_date` when missing.
 * Created: 2025-10-30 10:20:00
 */

return [
    'up' => function($pdo) {
        try {
            // Backfill date from start_date where date is NULL/empty/zero-date
            $pdo->exec("UPDATE holidays SET date = start_date WHERE (date IS NULL OR date = '' OR date = '0000-00-00') AND start_date IS NOT NULL");
        } catch (Throwable $e) {
            // ignore errors but log if migration manager captures them
        }
    },

    'down' => function($pdo) {
        // no-op: we do not revert data changes to avoid accidental data loss
    }
];
