<?php
/**
 * Migration: add_product_serial
 * Description: Adds ProductSerial to fixedassets for vendor serial tracking.
 * Created: 2026-02-04 09:00:00
 */

return [
    'up' => function ($pdo) {
        try { $pdo->exec("ALTER TABLE fixedassets ADD COLUMN ProductSerial VARCHAR(100) NULL AFTER AssetSerial"); } catch (Throwable $e) {}
        try { $pdo->exec("CREATE INDEX idx_fixedassets_product_serial ON fixedassets(ProductSerial)"); } catch (Throwable $e) {}
    },

    'down' => function ($pdo) {
        try { $pdo->exec("DROP INDEX idx_fixedassets_product_serial ON fixedassets"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE fixedassets DROP COLUMN ProductSerial"); } catch (Throwable $e) {}
    }
];
