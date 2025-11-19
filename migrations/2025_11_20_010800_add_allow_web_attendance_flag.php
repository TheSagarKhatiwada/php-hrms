<?php
/**
 * Migration: add_allow_web_attendance_flag
 * Description: Adds allow_web_attendance flag to employees table to gate web-based attendance actions.
 * Created: 2025-11-20 01:08:00
 */

return [
    'up' => function($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'allow_web_attendance'");
            $stmt->execute();
            $exists = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) > 0;
            if (!$exists) {
                $pdo->exec("ALTER TABLE employees ADD COLUMN allow_web_attendance TINYINT(1) NOT NULL DEFAULT 0 AFTER login_access");
            }
        } catch (Throwable $e) {
            // ignore to keep migration chain resilient
        }
    },

    'down' => function($pdo) {
        try {
            $pdo->exec("ALTER TABLE employees DROP COLUMN allow_web_attendance");
        } catch (Throwable $e) {
            // ignore
        }
    }
];
