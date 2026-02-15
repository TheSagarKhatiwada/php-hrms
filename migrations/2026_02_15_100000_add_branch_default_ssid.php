<?php
/**
 * Migration: add_branch_default_ssid
 * Description: Adds default_ssid column to branches for mobile attendance Wi-Fi validation.
 * Created: 2026-02-15 10:00:00
 */

return [
    'up' => function ($pdo) {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM branches")->fetchAll(PDO::FETCH_ASSOC);
            $existing = array_column($cols, 'Field');

            if (!in_array('default_ssid', $existing, true)) {
                $pdo->exec("ALTER TABLE branches ADD COLUMN default_ssid VARCHAR(191) NULL AFTER geofence_enabled");
            }
        } catch (Throwable $e) {
            // keep migration resilient
        }
    },

    'down' => function ($pdo) {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM branches")->fetchAll(PDO::FETCH_ASSOC);
            $existing = array_column($cols, 'Field');

            if (in_array('default_ssid', $existing, true)) {
                $pdo->exec("ALTER TABLE branches DROP COLUMN default_ssid");
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
];
