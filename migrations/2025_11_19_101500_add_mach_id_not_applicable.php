<?php
/**
 * Migration: add_mach_id_not_applicable
 * Description: Adds mach_id_not_applicable flag to employees table to identify staff without biometric IDs.
 * Created: 2025-11-19 10:15:00
 */

return [
    'up' => function($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'mach_id_not_applicable'");
            $stmt->execute();
            $exists = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) > 0;
            if (!$exists) {
                $pdo->exec("ALTER TABLE employees ADD COLUMN mach_id_not_applicable TINYINT(1) NOT NULL DEFAULT 0 AFTER mach_id");
            }
        } catch (Throwable $e) {
            // best effort; do not halt migration chain
        }
    },

    'down' => function($pdo) {
        try {
            $pdo->exec("ALTER TABLE employees DROP COLUMN mach_id_not_applicable");
        } catch (Throwable $e) {
            // ignore
        }
    }
];
