<?php
define('INCLUDE_CHECK', true);
require_once 'includes/config.php';

$config = getDBConfig();
$pdo = new PDO("mysql:host={$config['host']};dbname={$config['name']}", $config['user'], $config['pass']);

echo "=== ATTENDANCE_LOGS TABLE STRUCTURE ===\n";
$columns = $pdo->query('DESCRIBE attendance_logs')->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $col) {
    echo $col['Field'] . ' - ' . $col['Type'] . "\n";
}

echo "\n=== ATTENDANCE TABLE STRUCTURE ===\n";
$columns = $pdo->query('DESCRIBE attendance')->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $col) {
    echo $col['Field'] . ' - ' . $col['Type'] . "\n";
}
?>
