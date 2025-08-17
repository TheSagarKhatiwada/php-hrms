<?php
/**
 * Migration: add_soft_delete_to_generated_reports
 * Description: Adds deleted_at and purge_at columns for soft deletion lifecycle on generated_reports.
 * Created: 2025-08-17 15:00:00
 */
return [
    'up' => function($pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM generated_reports LIKE 'deleted_at'")->fetch();
        if(!$cols){
            $pdo->exec("ALTER TABLE generated_reports ADD COLUMN deleted_at DATETIME NULL AFTER generated_at, ADD COLUMN purge_at DATETIME NULL AFTER deleted_at, ADD INDEX idx_deleted_at (deleted_at)");
        }
    },
    'down' => function($pdo) {
        // Typically we would not drop these columns, but provided for completeness.
        try { $pdo->exec("ALTER TABLE generated_reports DROP COLUMN purge_at, DROP COLUMN deleted_at"); } catch(Throwable $e) {}
    }
];
