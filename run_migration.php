<?php
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/MigrationManager.php';

$mm = new MigrationManager($pdo);
$status = $mm->migrate();

if ($status) {
    echo "Migration successful!\n";
} else {
    echo "Migration failed!\n";
}