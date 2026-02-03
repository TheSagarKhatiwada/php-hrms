<?php
require_once __DIR__ . '/../includes/db_connection.php';
try {
    $stmt = $pdo->query('DESCRIBE permissions');
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "PERMISSIONS TABLE COLUMNS:\n";
    foreach ($cols as $c) {
        printf("%s  %s\n", $c['Field'], $c['Type']);
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
