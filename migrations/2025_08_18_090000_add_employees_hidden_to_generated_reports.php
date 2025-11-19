<?php
/**
 * Migration: add_employees_hidden_to_generated_reports
 * Description: Store hidden employee names for generated reports so the UI can show detailed tooltips.
 * Created: 2025-08-18 09:00:00
 */

return [
    'up' => function($pdo) {
        try {
            $col = $pdo->query("SHOW COLUMNS FROM generated_reports LIKE 'employees_hidden'")->fetch();
        } catch(Throwable $e) {
            $col = null;
        }
        if(!$col) {
            $pdo->exec("ALTER TABLE generated_reports ADD COLUMN employees_hidden TEXT NULL AFTER employees_label");
        }
    },
    'down' => function($pdo) {
        try { $pdo->exec("ALTER TABLE generated_reports DROP COLUMN employees_hidden"); } catch(Throwable $e) {}
    }
];
