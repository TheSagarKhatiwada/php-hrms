<?php
/**
 * Migration: fix_notifications_type_column
 * Description: Modifies the notifications table type column to be VARCHAR(20) to support various notification types.
 * Created: 2025-12-06 10:00:00
 */

return [
    'up' => function($pdo) {
        // Check if table exists first
        $stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
        if ($stmt->rowCount() > 0) {
            // Modify the column to be VARCHAR(20) to accommodate 'success', 'danger', 'warning', 'info'
            // Using VARCHAR instead of ENUM is more flexible for future types
            $pdo->exec("ALTER TABLE notifications MODIFY COLUMN type VARCHAR(20) NOT NULL DEFAULT 'info'");
        }
    },

    'down' => function($pdo) {
        // Revert to ENUM if needed, assuming the original was ENUM('info','warning','alert') or similar small set
        // But since we don't know the exact original state, we'll just leave it as VARCHAR or try to restrict it back
        // Best effort revert:
        // $pdo->exec("ALTER TABLE notifications MODIFY COLUMN type ENUM('info','warning','alert') NOT NULL DEFAULT 'info'");
    }
];
