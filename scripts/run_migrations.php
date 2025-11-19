<?php
/**
 * CLI migration runner (scripts/run_migrations.php)
 * Usage: php scripts/run_migrations.php
 */

chdir(__DIR__ . '/..'); // make project root the working dir

// Explicitly bootstrap DB connection in CLI and pass it to MigrationManager to avoid
// environment-specific inclusion issues when MigrationManager tries to require the DB file itself.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/MigrationManager.php';

// At this point $pdo should be set by db_connection.php
if (!isset($pdo) || !$pdo) {
    fwrite(STDERR, "ERROR: Unable to establish database connection in CLI.\n");
    exit(1);
}

$manager = new MigrationManager($pdo);
$ok = $manager->migrate();
exit($ok ? 0 : 1);
