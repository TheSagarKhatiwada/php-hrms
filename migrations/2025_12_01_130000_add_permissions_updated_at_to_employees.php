<?php
/**
 * Migration: add_permissions_updated_at_to_employees
 * Adds permissions_updated_at datetime column to employees table used to signal per-user permission changes
 */
return [
    'up' => function (PDO $pdo) {
        $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'permissions_updated_at'");
        $columnExists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$columnExists) {
            $pdo->exec('ALTER TABLE employees ADD COLUMN permissions_updated_at DATETIME NULL DEFAULT NULL');
        }
    },

    'down' => function (PDO $pdo) {
        $stmt = $pdo->query("SHOW COLUMNS FROM employees LIKE 'permissions_updated_at'");
        $columnExists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);

        if ($columnExists) {
            $pdo->exec('ALTER TABLE employees DROP COLUMN permissions_updated_at');
        }
    },
];
