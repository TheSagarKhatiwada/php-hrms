<?php
// Simple non-interactive migration runner
// Ensure DB connection, then run migrations
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/MigrationManager.php';

// Pass the PDO explicitly to avoid any runtime environment quirks
$manager = new MigrationManager($pdo ?? null);

$status = $manager->getStatus();
echo "Total migrations: {$status['total']}\n";
echo "Executed: {$status['executed']}\n";
echo "Pending: {$status['pending']}\n";

if ($status['pending'] > 0) {
    echo "Running pending migrations...\n";
    $ok = $manager->migrate();
    echo $ok ? "Migrations completed successfully.\n" : "Migration failed. See logs/migrations.log for details.\n";
} else {
    echo "No pending migrations.\n";
}
