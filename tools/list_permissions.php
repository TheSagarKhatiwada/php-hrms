<?php
require_once __DIR__ . '/../includes/db_connection.php';
try {
    $stmt = $pdo->query('SELECT id, name, description, category FROM permissions ORDER BY id LIMIT 50');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        printf("%d | %s | %s | %s\n", $r['id'], $r['name'], substr($r['description'], 0, 80), $r['category']);
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
