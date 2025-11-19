<?php
require_once __DIR__ . '/../includes/db_connection.php';
if (isset($pdo) && $pdo instanceof PDO) {
    echo "OK: PDO connected\n";
    $stmt = $pdo->query('SELECT 1');
    echo 'Query result: ' . ($stmt ? 'OK' : 'FAIL') . "\n";
} else {
    echo "FAIL: No PDO instance\n";
}
