<?php
/**
 * Migration: drop_entry_type_from_attendance_requests
 * Description: Removes the redundant entry_type column now that all requests are clock-in only.
 * Created: 2025-12-06 09:00:00
 */

return [
    'up' => function($pdo) {
        $pdo->exec("ALTER TABLE attendance_requests DROP COLUMN entry_type");
    },

    'down' => function($pdo) {
        $pdo->exec("ALTER TABLE attendance_requests ADD COLUMN entry_type ENUM('in','out') NOT NULL DEFAULT 'in' AFTER request_time");
    }
];
