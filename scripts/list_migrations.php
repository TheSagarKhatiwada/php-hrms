<?php
require_once __DIR__ . '/../includes/db_connection.php';
try {
    $stmt = $pdo->query('SELECT migration_name, executed_at FROM db_migrations ORDER BY executed_at');
    if ($stmt) {
        foreach ($stmt as $r) {
            echo ($r['migration_name'] ?? $r['migration'] ?? $r['name']) . ' | ' . ($r['executed_at'] ?? '') . PHP_EOL;
        }
    } else {
        echo "No rows in db_migrations or query failed\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
