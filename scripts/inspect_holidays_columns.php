<?php
define('INCLUDE_CHECK', true);
require_once __DIR__ . '/../includes/db_connection.php';

echo "Holidays table columns:\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM holidays");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo $c['Field'] . "\t" . $c['Type'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
