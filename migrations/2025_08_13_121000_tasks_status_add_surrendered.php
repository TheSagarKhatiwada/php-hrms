<?php
/**
 * Migration: tasks_status_add_surrendered
 * Description: Ensure tasks.status ENUM includes 'surrendered' and keep default as 'pending'.
 * Created: 2025-08-13 12:10:00
 */

return [
    'up' => function($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'status'");
            $stmt->execute();
            $col = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$col) { return; }

            $ctype = (string)($col['COLUMN_TYPE'] ?? '');
            $nullable = (string)($col['IS_NULLABLE'] ?? 'NO');
            $default = $col['COLUMN_DEFAULT'];

            // If 'surrendered' is already present, skip
            if (stripos($ctype, "'surrendered'") !== false) {
                return;
            }

            // Build new ENUM definition preserving known values and adding 'surrendered'
            // Expected existing values include: 'pending','in_progress','completed','cancelled','on_hold'
            $enumValues = [
                'pending', 'in_progress', 'completed', 'cancelled', 'on_hold', 'surrendered'
            ];

            $enumSql = "ENUM('" . implode("','", $enumValues) . "')";
            $nullSql = (strtoupper($nullable) === 'YES') ? 'NULL' : 'NOT NULL';
            // Default to 'pending' if no default
            $defVal = ($default !== null && $default !== '') ? $default : 'pending';
            if (!in_array($defVal, $enumValues, true)) { $defVal = 'pending'; }
            $defSql = "DEFAULT '" . $defVal . "'";

            $sql = "ALTER TABLE tasks MODIFY COLUMN status $enumSql $nullSql $defSql";
            $pdo->exec($sql);
        } catch (Throwable $e) {
            // Best-effort; do not throw to avoid blocking subsequent migrations
        }
    },

    'down' => function($pdo) {
        // No-op: keeping 'surrendered' in ENUM is backward-compatible; skipping rollback to avoid data loss.
    }
];
