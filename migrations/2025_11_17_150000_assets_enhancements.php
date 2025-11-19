<?php
/**
 * Migration: assets_enhancements
 * Description: Adds soft-delete fields to fixedassets and creates asset_audit table.
 * Created: 2025-11-17 15:00:00
 */

return [
    'up' => function ($pdo) {
        // Add soft delete columns to fixedassets
        try { $pdo->exec("ALTER TABLE fixedassets ADD COLUMN deleted_at DATETIME NULL AFTER Status"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE fixedassets ADD COLUMN deleted_by INT NULL AFTER deleted_at"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE fixedassets ADD COLUMN deleted_reason VARCHAR(255) NULL AFTER deleted_by"); } catch (Throwable $e) {}
        try { $pdo->exec("CREATE INDEX idx_fixedassets_deleted_at ON fixedassets(deleted_at)"); } catch (Throwable $e) {}

        // Create audit table for asset changes
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS asset_audit (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                asset_id INT NULL,
                action VARCHAR(100) NOT NULL,
                summary TEXT NULL,
                before_snapshot LONGTEXT NULL,
                after_snapshot LONGTEXT NULL,
                performed_by INT NULL,
                ip_address VARCHAR(45) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_asset_audit_asset (asset_id),
                INDEX idx_asset_audit_performer (performed_by),
                INDEX idx_asset_audit_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {}
    },

    'down' => function ($pdo) {
        try { $pdo->exec("DROP INDEX idx_fixedassets_deleted_at ON fixedassets"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE fixedassets DROP COLUMN deleted_reason"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE fixedassets DROP COLUMN deleted_by"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE fixedassets DROP COLUMN deleted_at"); } catch (Throwable $e) {}
        try { $pdo->exec("DROP TABLE IF EXISTS asset_audit"); } catch (Throwable $e) {}
    }
];
