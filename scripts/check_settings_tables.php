<?php
chdir(__DIR__ . '/..');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connection.php';

if (!isset($pdo) || !$pdo) { echo "No DB connection\n"; exit(1); }

try {
    $tables = [];
    $q = $pdo->query("SHOW TABLES LIKE 'settings'");
    $tables['settings'] = (bool)$q->fetch();
    $q2 = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    $tables['system_settings'] = (bool)$q2->fetch();
    echo json_encode($tables) . "\n";
} catch(Exception $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}
