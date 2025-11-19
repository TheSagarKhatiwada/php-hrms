<?php
require_once __DIR__.'/../includes/db_connection.php';
if (isset($pdo) && $pdo instanceof PDO) {
    echo "PDO OK\n";
    try {
        $r = $pdo->query('SELECT 1')->fetch();
        var_export($r);
    } catch (Exception $e) { echo "Query failed: " . $e->getMessage() . "\n"; }
} else {
    echo "PDO NOT SET\n";
}
