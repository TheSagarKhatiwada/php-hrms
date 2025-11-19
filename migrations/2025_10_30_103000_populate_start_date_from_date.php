<?php
/**
 * Migration: populate_start_date_from_date
 * Description: Populate `start_date` from legacy `date` column when missing.
 * Created: 2025-10-30 10:30:00
 */

return [
    'up' => function($pdo) {
        try {
            // For rows where start_date is empty but legacy date exists, copy it into start_date
            $pdo->exec("UPDATE holidays SET start_date = date WHERE (start_date IS NULL OR start_date = '' OR start_date = '0000-00-00') AND (date IS NOT NULL AND date <> '' AND date <> '0000-00-00')");
        } catch (Throwable $e) {
            // ignore errors
        }
    },

    'down' => function($pdo) {
        // no-op: reversing this would risk losing data
    }
];
