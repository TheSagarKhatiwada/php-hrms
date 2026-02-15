<?php
/**
 * Migration: add_branch_geofence
 * Description: Adds geofence fields to branches for location-based web attendance.
 * Created: 2026-02-04 12:30:00
 */

return [
    'up' => function ($pdo) {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM branches")->fetchAll(PDO::FETCH_ASSOC);
            $existing = array_column($cols, 'Field');

            if (!in_array('latitude', $existing, true)) {
                $pdo->exec("ALTER TABLE branches ADD COLUMN latitude DECIMAL(10,7) NULL AFTER name");
            }
            if (!in_array('longitude', $existing, true)) {
                $pdo->exec("ALTER TABLE branches ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude");
            }
            if (!in_array('radius_m', $existing, true)) {
                $pdo->exec("ALTER TABLE branches ADD COLUMN radius_m INT NULL DEFAULT 200 AFTER longitude");
            }
            if (!in_array('geofence_enabled', $existing, true)) {
                $pdo->exec("ALTER TABLE branches ADD COLUMN geofence_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER radius_m");
            }
        } catch (Throwable $e) {
            // keep migration resilient
        }
    },

    'down' => function ($pdo) {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM branches")->fetchAll(PDO::FETCH_ASSOC);
            $existing = array_column($cols, 'Field');

            if (in_array('geofence_enabled', $existing, true)) {
                $pdo->exec("ALTER TABLE branches DROP COLUMN geofence_enabled");
            }
            if (in_array('radius_m', $existing, true)) {
                $pdo->exec("ALTER TABLE branches DROP COLUMN radius_m");
            }
            if (in_array('longitude', $existing, true)) {
                $pdo->exec("ALTER TABLE branches DROP COLUMN longitude");
            }
            if (in_array('latitude', $existing, true)) {
                $pdo->exec("ALTER TABLE branches DROP COLUMN latitude");
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
];
